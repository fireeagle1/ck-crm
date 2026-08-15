<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\FulfilmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopOrderController extends Controller
{
    public function __construct(private FulfilmentService $fulfilmentService)
    {
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
     *
     * Requirements: 10.4
     */
    public function show(Order $order): View
    {
        $order->load('customer', 'items.product', 'items.service');

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
}
