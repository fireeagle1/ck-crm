<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Product;
use App\Services\FulfilmentStageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private FulfilmentStageService $fulfilmentStageService,
    ) {}

    /**
     * Paginated list of rental bookings with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Booking::with(['product', 'customer']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($stage = $request->input('stage') ?? $request->input('fulfilment_stage')) {
            $query->where('fulfilment_stage', $stage);
        }

        if ($companyId = $request->input('company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        $bookings = $query->orderByDesc('start_date')->paginate($perPage);

        return response()->json([
            'data' => $bookings->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'product_name' => $booking->product?->name,
                'customer_name' => $booking->customer?->company_name,
                'start_date' => $booking->start_date?->toDateString(),
                'end_date' => $booking->end_date?->toDateString(),
                'quantity' => $booking->quantity,
                'total_price' => (float) $booking->total_price,
                'status' => $booking->status,
                'fulfilment_stage' => $booking->fulfilment_stage,
                'returned_at' => $booking->returned_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * Show a single booking with full detail including assets and inspections.
     */
    public function show(Booking $booking): JsonResponse
    {
        $booking->load([
            'product',
            'customer',
            'orderItem.order',
            'assignedAssets.asset',
            'checkoutInspection.inspector',
            'returnInspection.inspector',
        ]);

        return response()->json([
            'data' => [
                'id' => $booking->id,
                'product_name' => $booking->product?->name,
                'customer_name' => $booking->customer?->company_name,
                'order_id' => $booking->orderItem?->order_id,
                'start_date' => $booking->start_date?->toDateString(),
                'end_date' => $booking->end_date?->toDateString(),
                'quantity' => $booking->quantity,
                'total_price' => (float) $booking->total_price,
                'status' => $booking->status,
                'fulfilment_stage' => $booking->fulfilment_stage,
                'returned_at' => $booking->returned_at?->toIso8601String(),
                'next_stage' => $this->fulfilmentStageService->getNextStage($booking),
                'pre_conditions' => $this->fulfilmentStageService->getNextStage($booking)
                    ? $this->fulfilmentStageService->checkPreConditions($booking, $this->fulfilmentStageService->getNextStage($booking))
                    : [],
                'assigned_assets' => $booking->assignedAssets->map(fn ($ba) => [
                    'id' => $ba->id,
                    'device_name' => $ba->asset?->device_name,
                    'serial_number' => $ba->asset?->serial_number,
                    'status' => $ba->asset?->asset_status,
                    'assigned_at' => $ba->assigned_at?->toIso8601String(),
                    'released_at' => $ba->released_at?->toIso8601String(),
                ]),
                'checkout_inspection' => $booking->checkoutInspection ? [
                    'photos' => $booking->checkoutInspection->photos,
                    'condition_notes' => $booking->checkoutInspection->condition_notes,
                    'damage_flagged' => $booking->checkoutInspection->damage_flagged,
                    'inspector_name' => $booking->checkoutInspection->inspector?->name,
                    'inspected_at' => $booking->checkoutInspection->inspected_at?->toIso8601String(),
                ] : null,
                'return_inspection' => $booking->returnInspection ? [
                    'photos' => $booking->returnInspection->photos,
                    'condition_notes' => $booking->returnInspection->condition_notes,
                    'damage_flagged' => $booking->returnInspection->damage_flagged,
                    'inspector_name' => $booking->returnInspection->inspector?->name,
                    'inspected_at' => $booking->returnInspection->inspected_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /**
     * Calendar data: bookings for a given month with date ranges.
     * Returns bookings as date-range blocks for calendar rendering.
     */
    public function calendar(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) now()->format('Y'));
        $month = $request->integer('month', (int) now()->format('m'));

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        // Include bookings from surrounding weeks for calendar overlap
        $rangeStart = $startOfMonth->copy()->startOfWeek();
        $rangeEnd = $endOfMonth->copy()->endOfWeek();

        $bookings = Booking::with('product', 'customer')
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_date', '<=', $rangeEnd)
            ->where('end_date', '>=', $rangeStart)
            ->orderBy('start_date')
            ->get();

        // Also get products for availability context
        $products = Product::where('product_type', 'equipment_rental')
            ->where('is_archived', false)
            ->orderBy('name')
            ->get(['id', 'name', 'stock_quantity']);

        return response()->json([
            'data' => [
                'year' => $year,
                'month' => $month,
                'range_start' => $rangeStart->toDateString(),
                'range_end' => $rangeEnd->toDateString(),
                'bookings' => $bookings->map(fn (Booking $b) => [
                    'id' => $b->id,
                    'product_name' => $b->product?->name,
                    'product_id' => $b->product_id,
                    'customer_name' => $b->customer?->company_name,
                    'start_date' => $b->start_date->toDateString(),
                    'end_date' => $b->end_date->toDateString(),
                    'quantity' => $b->quantity,
                    'status' => $b->status,
                    'fulfilment_stage' => $b->fulfilment_stage,
                ]),
                'products' => $products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'stock_quantity' => $p->stock_quantity,
                ]),
            ],
        ]);
    }
}
