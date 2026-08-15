<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Check availability for a product over a given date range and quantity.
     * Returns JSON with available status, booked units, and total units.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'quantity' => 'integer|min:1',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $quantity = $validated['quantity'] ?? 1;

        $result = $this->bookingService->checkAvailability($product, $startDate, $endDate, $quantity);

        return response()->json($result);
    }

    /**
     * Get unavailable dates for a product within a date range.
     * Returns JSON array of Y-m-d strings that are fully booked or in cooldown.
     */
    public function getUnavailableDates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'range_start' => 'required|date',
            'range_end' => 'required|date|after:range_start',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $rangeStart = Carbon::parse($validated['range_start']);
        $rangeEnd = Carbon::parse($validated['range_end']);

        $unavailableDates = $this->bookingService->getUnavailableDates($product, $rangeStart, $rangeEnd);

        // Return as array of Y-m-d strings
        $dateStrings = $unavailableDates->map(fn (Carbon $date) => $date->format('Y-m-d'))->values()->toArray();

        return response()->json($dateStrings);
    }
}
