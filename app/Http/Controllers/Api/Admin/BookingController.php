<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * Paginated list of rental bookings with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Booking::with(['product', 'customer']);

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by customer
        if ($companyId = $request->input('company_id')) {
            $query->where('company_id', $companyId);
        }

        // Filter by product
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
}
