<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopOrderController extends Controller
{
    /**
     * Paginated list of shop orders with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Order::with('customer');

        // Filter by payment status
        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        // Filter by fulfilment status
        if ($fulfilment = $request->input('fulfilment_status')) {
            $query->where('fulfilment_status', $fulfilment);
        }

        // Filter by customer
        if ($companyId = $request->input('company_id')) {
            $query->where('company_id', $companyId);
        }

        // Filter by product type (via order items)
        if ($productType = $request->input('product_type')) {
            $query->whereHas('items', fn ($q) => $q->where('product_type', $productType));
        }

        $orders = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'customer_name' => $order->customer?->company_name,
                'total_amount' => (float) $order->total_amount,
                'payment_status' => $order->payment_status,
                'fulfilment_status' => $order->fulfilment_status,
                'item_count' => $order->items_count ?? $order->items()->count(),
                'created_at' => $order->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Show a single order with its items and related data.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['customer', 'items.product', 'items.booking']);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'customer_name' => $order->customer?->company_name,
                'company_id' => $order->company_id,
                'total_amount' => (float) $order->total_amount,
                'payment_status' => $order->payment_status,
                'fulfilment_status' => $order->fulfilment_status,
                'admin_notes' => $order->admin_notes,
                'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
                'created_at' => $order->created_at?->toIso8601String(),
                'delivery_address' => [
                    'line1' => $order->delivery_address_line1,
                    'line2' => $order->delivery_address_line2,
                    'city' => $order->delivery_city,
                    'state' => $order->delivery_state,
                    'postal_code' => $order->delivery_postal_code,
                    'country' => $order->delivery_country,
                ],
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'product_type' => $item->product_type,
                    'price' => (float) $item->price,
                    'quantity' => $item->quantity,
                    'billing_frequency' => $item->billing_frequency,
                    'domain_name' => $item->domain_name,
                    'rental_start_date' => $item->rental_start_date?->toDateString(),
                    'rental_end_date' => $item->rental_end_date?->toDateString(),
                    'booking_status' => $item->booking?->status,
                ]),
            ],
        ]);
    }
}
