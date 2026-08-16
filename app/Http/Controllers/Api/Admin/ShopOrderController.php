<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Services\AssetAssignmentService;
use App\Services\BookingInspectionService;
use App\Services\FulfilmentService;
use App\Services\FulfilmentStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShopOrderController extends Controller
{
    public function __construct(
        private FulfilmentService $fulfilmentService,
        private FulfilmentStageService $fulfilmentStageService,
        private AssetAssignmentService $assetAssignmentService,
        private BookingInspectionService $bookingInspectionService,
    ) {}

    /**
     * Paginated list of shop orders with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Order::with('customer');

        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($fulfilment = $request->input('fulfilment_status')) {
            $query->where('fulfilment_status', $fulfilment);
        }

        if ($companyId = $request->input('company_id')) {
            $query->where('company_id', $companyId);
        }

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
     * Show a single order with items, bookings, fulfilment data, and assigned assets.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'customer',
            'items.product',
            'items.booking.assignedAssets.asset',
            'items.booking.checkoutInspection',
            'items.booking.returnInspection',
        ]);

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
                    'booking' => $item->booking ? [
                        'id' => $item->booking->id,
                        'status' => $item->booking->status,
                        'fulfilment_stage' => $item->booking->fulfilment_stage,
                        'quantity' => $item->booking->quantity,
                        'total_price' => (float) $item->booking->total_price,
                        'returned_at' => $item->booking->returned_at?->toIso8601String(),
                        'assigned_assets' => $item->booking->assignedAssets->map(fn ($ba) => [
                            'id' => $ba->id,
                            'device_name' => $ba->asset?->device_name,
                            'serial_number' => $ba->asset?->serial_number,
                            'status' => $ba->asset?->asset_status,
                            'released_at' => $ba->released_at?->toIso8601String(),
                        ]),
                        'has_checkout_inspection' => $item->booking->checkoutInspection !== null,
                        'has_return_inspection' => $item->booking->returnInspection !== null,
                        'next_stage' => $this->fulfilmentStageService->getNextStage($item->booking),
                    ] : null,
                ]),
            ],
        ]);
    }

    /**
     * Mark an order as fulfilled.
     */
    public function fulfil(Order $order): JsonResponse
    {
        $this->fulfilmentService->fulfilOrder($order);

        return response()->json(['message' => 'Order marked as fulfilled.']);
    }

    /**
     * Cancel an order and associated bookings/services.
     */
    public function cancel(Order $order): JsonResponse
    {
        if ($order->fulfilment_status === 'cancelled') {
            return response()->json(['message' => 'Order is already cancelled.'], 422);
        }

        DB::transaction(function () use ($order) {
            $order->update(['fulfilment_status' => 'cancelled']);

            foreach ($order->items as $item) {
                if ($item->booking && in_array($item->booking->status, ['confirmed', 'active'])) {
                    $item->booking->update(['status' => 'cancelled']);
                }
                if ($item->service && $item->service->status === 'pending') {
                    $item->service->update(['status' => 'cancelled']);
                }
            }
        });

        return response()->json(['message' => 'Order cancelled.']);
    }

    /**
     * Mark an order as paid offline.
     */
    public function markPaidOffline(Order $order): JsonResponse
    {
        if (in_array($order->payment_status, ['paid', 'paid_offline'])) {
            return response()->json(['message' => 'Order is already paid.'], 422);
        }

        $order->update(['payment_status' => 'paid_offline']);

        return response()->json(['message' => 'Order marked as paid offline.']);
    }

    /**
     * Add an admin note to an order.
     */
    public function addNote(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate(['note' => 'required|string|max:1000']);

        $existingNotes = $order->admin_notes ? $order->admin_notes . "\n\n" : '';
        $order->update([
            'admin_notes' => $existingNotes . '[' . now()->format('Y-m-d H:i') . '] ' . $validated['note'],
        ]);

        return response()->json(['message' => 'Note added.', 'admin_notes' => $order->admin_notes]);
    }

    /**
     * Advance a booking's fulfilment stage.
     */
    public function advanceStage(Order $order, Booking $booking): JsonResponse
    {
        abort_unless($booking->orderItem?->order_id === $order->id, 404);

        $nextStage = $this->fulfilmentStageService->getNextStage($booking);

        if (!$nextStage) {
            return response()->json(['message' => 'Booking is already at the final stage.'], 422);
        }

        try {
            $this->fulfilmentStageService->advance($booking, $nextStage);
            return response()->json([
                'message' => 'Booking advanced to "' . str_replace('_', ' ', $nextStage) . '".',
                'fulfilment_stage' => $nextStage,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Assign assets to a booking.
     */
    public function assignAssets(Request $request, Order $order, Booking $booking): JsonResponse
    {
        abort_unless($booking->orderItem?->order_id === $order->id, 404);

        $validated = $request->validate([
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'required|integer|exists:cmdb,device_id',
        ]);

        try {
            $this->assetAssignmentService->assignAssets($booking, $validated['asset_ids']);

            if ($booking->fulfilment_stage === 'ordered') {
                $this->fulfilmentStageService->advance($booking, 'packing');
            }

            return response()->json(['message' => 'Assets assigned successfully.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Upload inspection photos and advance stage.
     * Accepts multipart/form-data with photos[] and optional condition_notes, damage_flagged.
     */
    public function inspect(Request $request, Order $order, Booking $booking): JsonResponse
    {
        abort_unless($booking->orderItem?->order_id === $order->id, 404);

        $validated = $request->validate([
            'photos' => 'required|array|min:1|max:10',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'condition_notes' => 'nullable|string|max:2000',
            'damage_flagged' => 'nullable|boolean',
        ]);

        $photos = $request->file('photos');
        $notes = $validated['condition_notes'] ?? null;
        $damageFlagged = (bool) ($validated['damage_flagged'] ?? false);
        $adminId = $request->user()->id;

        $isReturnInspection = in_array($booking->fulfilment_stage, ['checked_out', 'returned']);

        try {
            if ($isReturnInspection) {
                $this->bookingInspectionService->createReturnInspection($booking, $photos, $notes, $damageFlagged, $adminId);

                if ($booking->fulfilment_stage === 'checked_out') {
                    $this->fulfilmentStageService->advance($booking, 'returned');
                    $booking->refresh();
                }
                $this->fulfilmentStageService->advance($booking, 'inspected');

                return response()->json(['message' => 'Return inspection recorded.', 'fulfilment_stage' => 'inspected']);
            } else {
                $this->bookingInspectionService->createCheckoutInspection($booking, $photos, $notes, $adminId);

                if ($booking->fulfilment_stage === 'ready') {
                    $this->fulfilmentStageService->advance($booking, 'checked_out');
                }

                return response()->json(['message' => 'Checkout inspection recorded.', 'fulfilment_stage' => 'checked_out']);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mark a booking as returned.
     */
    public function markReturned(Order $order, Booking $booking): JsonResponse
    {
        abort_unless($booking->orderItem?->order_id === $order->id, 404);

        if ($booking->status === 'returned') {
            return response()->json(['message' => 'Booking is already returned.'], 422);
        }

        $booking->update(['status' => 'returned', 'returned_at' => now()]);

        if ($booking->fulfilment_stage === 'checked_out') {
            try {
                $this->fulfilmentStageService->advance($booking, 'returned');
            } catch (InvalidArgumentException $e) {
                // Non-blocking
            }
        }

        return response()->json(['message' => 'Booking marked as returned.', 'fulfilment_stage' => $booking->fresh()->fulfilment_stage]);
    }
}
