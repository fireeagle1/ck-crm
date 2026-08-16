<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\FulfilmentService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopOrderController extends Controller
{
    public function __construct(
        private FulfilmentService $fulfilmentService,
        private StripeService $stripeService,
    ) {
    }

    /**
     * Display all orders with filters and revenue summaries.
     *
     * Requirements: 5.2, 10.1, 10.2, 10.3
     */
    public function index(Request $request): View
    {
        $query = Order::with('customer', 'items');

        // Filter by fulfilment status
        if ($request->filled('fulfilment_status')) {
            $query->where('fulfilment_status', $request->input('fulfilment_status'));
        }

        // Filter by customer
        if ($request->filled('customer')) {
            $query->where('company_id', $request->input('customer'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Filter by product type (via order items)
        if ($request->filled('product_type')) {
            $productType = $request->input('product_type');
            $query->whereHas('items', function ($q) use ($productType) {
                $q->where('product_type', $productType);
            });
        }

        $orders = $query->orderByDesc('created_at')->paginate(20);

        // Revenue summaries grouped by product type
        $revenueSummary = OrderItem::selectRaw('product_type, SUM(price) as total_revenue, COUNT(*) as item_count')
            ->groupBy('product_type')
            ->get()
            ->keyBy('product_type');

        $customers = Customer::orderBy('company_name')->get();

        return view('admin.shop.orders.index', compact('orders', 'revenueSummary', 'customers'));
    }

    /**
     * Display order detail with customer, products, payment/fulfilment status, and Stripe references.
     * Includes delivery address, rental booking details (dates, status, signature), and PDF link.
     *
     * Requirements: 10.4, 15.2, 18.1, 21.4, 22.1, 22.3
     */
    public function show(Order $order): View
    {
        $order->load('customer', 'items.product', 'items.service', 'items.booking');

        return view('admin.shop.orders.show', compact('order'));
    }

    /**
     * Mark an order as fulfilled using FulfilmentService.
     *
     * Requirements: 5.3, 7.3, 7.4
     */
    public function fulfil(Order $order): RedirectResponse
    {
        $this->fulfilmentService->fulfilOrder($order);

        return redirect()->route('admin.shop.orders.show', $order)
            ->with('success', 'Order marked as fulfilled.');
    }

    /**
     * Append admin notes to an order with timestamp.
     *
     * Requirements: 10.4
     */
    public function addNote(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $existingNotes = $order->admin_notes ? $order->admin_notes . "\n\n" : '';
        $order->update([
            'admin_notes' => $existingNotes . '[' . now()->format('Y-m-d H:i') . '] ' . $validated['note'],
        ]);

        return redirect()->route('admin.shop.orders.show', $order)
            ->with('success', 'Note added.');
    }

    /**
     * Download the generated PDF invoice for an order.
     *
     * Requirements: 18.1, 18.4
     */
    public function downloadPdf(Order $order): StreamedResponse|RedirectResponse
    {
        if (!$order->invoice_pdf_path || !Storage::disk('local')->exists($order->invoice_pdf_path)) {
            return redirect()->route('admin.shop.orders.show', $order)
                ->with('error', 'No invoice PDF available for this order.');
        }

        return Storage::disk('local')->download(
            $order->invoice_pdf_path,
            'invoice-order-' . $order->id . '.pdf'
        );
    }

    /**
     * Mark an order as paid offline for manual payment reconciliation.
     * This triggers the OrderObserver which generates the PDF and sends confirmation.
     *
     * Requirements: 16.5
     */
    public function markPaidOffline(Order $order): RedirectResponse
    {
        if ($order->payment_status === 'paid' || $order->payment_status === 'paid_offline') {
            return redirect()->route('admin.shop.orders.show', $order)
                ->with('error', 'This order is already marked as paid.');
        }

        $order->update([
            'payment_status' => 'paid_offline',
        ]);

        return redirect()->route('admin.shop.orders.show', $order)
            ->with('success', 'Order marked as paid offline. Invoice PDF will be generated.');
    }

    /**
     * Cancel an order. Sets fulfilment_status to 'cancelled', cancels any
     * associated bookings and pending services. Does NOT handle Stripe refunds.
     */
    public function cancel(Order $order): RedirectResponse
    {
        if ($order->fulfilment_status === 'cancelled') {
            return redirect()->route('admin.shop.orders.show', $order)
                ->with('error', 'This order is already cancelled.');
        }

        DB::transaction(function () use ($order) {
            $order->update(['fulfilment_status' => 'cancelled']);

            foreach ($order->items as $item) {
                // Cancel associated bookings
                if ($item->booking && in_array($item->booking->status, ['confirmed', 'active'])) {
                    $item->booking->update(['status' => 'cancelled']);
                }

                // Cancel pending services
                if ($item->service && $item->service->status === 'pending') {
                    $item->service->update(['status' => 'cancelled']);
                }
            }
        });

        return redirect()->route('admin.shop.orders.show', $order)
            ->with('success', 'Order cancelled. Use the refund section to process a refund if needed.');
    }

    /**
     * Process a full or partial refund for an order via Stripe.
     *
     * Requires a valid stripe_payment_intent_id on the order.
     * Accepts an optional refund_amount for partial refunds.
     */
    public function refund(Request $request, Order $order): RedirectResponse
    {
        // Validate the order can be refunded
        if (!$order->stripe_payment_intent_id) {
            return redirect()->route('admin.shop.orders.show', $order)
                ->with('error', 'No Stripe payment intent found for this order. Refund must be processed manually.');
        }

        if ($order->refund_status === 'full') {
            return redirect()->route('admin.shop.orders.show', $order)
                ->with('error', 'This order has already been fully refunded.');
        }

        $validated = $request->validate([
            'refund_amount' => 'nullable|numeric|min:0.01',
            'refund_reason' => 'nullable|in:duplicate,fraudulent,requested_by_customer',
        ]);

        $maxRefundable = (float) $order->total_amount - (float) $order->refund_amount;

        // Determine refund amount (null = full remaining balance)
        $refundAmount = isset($validated['refund_amount'])
            ? min((float) $validated['refund_amount'], $maxRefundable)
            : $maxRefundable;

        if ($refundAmount <= 0) {
            return redirect()->route('admin.shop.orders.show', $order)
                ->with('error', 'No refundable amount remaining on this order.');
        }

        $reason = $validated['refund_reason'] ?? 'requested_by_customer';

        try {
            $stripeRefund = $this->stripeService->createRefund(
                $order->stripe_payment_intent_id,
                (int) round($refundAmount * 100),
                $reason
            );

            // Update order with refund details
            $totalRefunded = (float) $order->refund_amount + $refundAmount;
            $refundStatus = $totalRefunded >= (float) $order->total_amount ? 'full' : 'partial';

            $order->update([
                'refund_amount' => $totalRefunded,
                'refund_status' => $refundStatus,
                'stripe_refund_id' => $stripeRefund->id,
            ]);

            return redirect()->route('admin.shop.orders.show', $order)
                ->with('success', "Refund of £" . number_format($refundAmount, 2) . " processed successfully. Stripe Refund ID: {$stripeRefund->id}");
        } catch (\Throwable $e) {
            return redirect()->route('admin.shop.orders.show', $order)
                ->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }
}
