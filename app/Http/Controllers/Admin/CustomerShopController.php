<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerShopController extends Controller
{
    /**
     * Display the customer's shop & rental history page with KPIs,
     * orders, bookings, and document links.
     *
     * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 6.1, 6.2, 6.3, 6.4
     */
    public function index(Request $request, Customer $customer): View
    {
        // ──────────────────────────────────────────────
        // Filters
        // ──────────────────────────────────────────────
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $productType = $request->input('product_type');

        // ──────────────────────────────────────────────
        // KPIs
        // ──────────────────────────────────────────────
        $ordersQuery = $customer->orders()
            ->whereIn('payment_status', ['paid', 'paid_offline']);

        // Apply date filter to KPI calculations
        if ($dateFrom) {
            $ordersQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ordersQuery->whereDate('created_at', '<=', $dateTo);
        }

        $paidOrders = $ordersQuery->get();

        // Total rental spend: sum of order items where product_type = equipment_rental
        $totalRentalSpend = 0;
        $totalPurchaseSpend = 0;
        $rentalCount = 0;
        $orderCount = $paidOrders->count();

        foreach ($paidOrders as $order) {
            $items = $order->items;
            foreach ($items as $item) {
                $lineTotal = (float) $item->price * ($item->quantity ?? 1);
                if ($item->product_type === 'equipment_rental') {
                    $totalRentalSpend += $lineTotal;
                    $rentalCount++;
                } else {
                    $totalPurchaseSpend += $lineTotal;
                }
            }
        }

        $avgOrderValue = $orderCount > 0
            ? ($totalRentalSpend + $totalPurchaseSpend) / $orderCount
            : 0;

        // ──────────────────────────────────────────────
        // Orders with items (filtered)
        // ──────────────────────────────────────────────
        $ordersListQuery = $customer->orders()->with('items.product')->latest();

        if ($dateFrom) {
            $ordersListQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ordersListQuery->whereDate('created_at', '<=', $dateTo);
        }
        if ($productType) {
            $ordersListQuery->whereHas('items', function ($q) use ($productType) {
                $q->where('product_type', $productType);
            });
        }

        $orders = $ordersListQuery->paginate(15, ['*'], 'orders_page');

        // ──────────────────────────────────────────────
        // Active Bookings (not returned/inspected/cancelled)
        // ──────────────────────────────────────────────
        $activeBookingsQuery = $customer->bookings()
            ->with(['product', 'assignedAssets'])
            ->whereIn('status', ['confirmed', 'active'])
            ->whereNotIn('fulfilment_stage', ['inspected']);

        if ($productType) {
            $activeBookingsQuery->whereHas('product', function ($q) use ($productType) {
                $q->where('product_type', $productType);
            });
        }

        $activeBookings = $activeBookingsQuery->orderBy('start_date')->get();

        // ──────────────────────────────────────────────
        // Past Bookings (returned/inspected or cancelled)
        // ──────────────────────────────────────────────
        $pastBookingsQuery = $customer->bookings()
            ->with(['product', 'inspections'])
            ->where(function ($q) {
                $q->where('status', 'returned')
                    ->orWhere('status', 'cancelled')
                    ->orWhere('fulfilment_stage', 'inspected');
            });

        if ($dateFrom) {
            $pastBookingsQuery->whereDate('start_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $pastBookingsQuery->whereDate('end_date', '<=', $dateTo);
        }
        if ($productType) {
            $pastBookingsQuery->whereHas('product', function ($q) use ($productType) {
                $q->where('product_type', $productType);
            });
        }

        $pastBookings = $pastBookingsQuery->orderByDesc('end_date')->paginate(10, ['*'], 'bookings_page');

        // ──────────────────────────────────────────────
        // Documents (invoice PDFs + agreement snapshots)
        // ──────────────────────────────────────────────
        $documents = collect();

        // Invoice PDFs from orders
        $ordersWithInvoices = $customer->orders()
            ->whereNotNull('invoice_pdf_path')
            ->orderByDesc('created_at')
            ->get(['id', 'created_at', 'invoice_pdf_path']);

        foreach ($ordersWithInvoices as $order) {
            $documents->push([
                'order_id' => $order->id,
                'date' => $order->created_at,
                'type' => 'Invoice PDF',
                'path' => $order->invoice_pdf_path,
                'download_route' => route('admin.shop.orders.download-pdf', $order->id),
            ]);
        }

        // Agreement snapshots from bookings
        $bookingsWithAgreements = $customer->bookings()
            ->whereNotNull('agreement_text_snapshot')
            ->with('product:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'product_id', 'created_at', 'agreement_accepted_at', 'order_item_id']);

        foreach ($bookingsWithAgreements as $booking) {
            $documents->push([
                'order_id' => $booking->orderItem ? $booking->orderItem->order_id : null,
                'date' => $booking->agreement_accepted_at ?? $booking->created_at,
                'type' => 'Rental Agreement',
                'booking_id' => $booking->id,
                'product_name' => $booking->product->name ?? 'Unknown',
            ]);
        }

        // Group documents by order
        $documentsByOrder = $documents->groupBy('order_id');

        // ──────────────────────────────────────────────
        // Customer Tier / Loyalty Info
        // ──────────────────────────────────────────────
        $customer->load('tiers');
        $lifetimeSpend = $totalRentalSpend + $totalPurchaseSpend;

        $kpis = [
            'total_rental_spend' => $totalRentalSpend,
            'total_purchase_spend' => $totalPurchaseSpend,
            'order_count' => $orderCount,
            'rental_count' => $rentalCount,
            'avg_order_value' => $avgOrderValue,
            'lifetime_spend' => $lifetimeSpend,
            'customer_since' => $customer->created_at,
        ];

        return view('admin.customers.shop', compact(
            'customer',
            'kpis',
            'orders',
            'activeBookings',
            'pastBookings',
            'documentsByOrder',
            'dateFrom',
            'dateTo',
            'productType',
        ));
    }
}
