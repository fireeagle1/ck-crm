<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Check date availability for a product considering existing bookings,
     * stock_quantity, and cooldown periods.
     *
     * @return array{available: bool, booked_units: int, total_units: int}
     */
    public function checkAvailability(Product $product, Carbon $startDate, Carbon $endDate, int $quantity = 1): array
    {
        $available = $this->availabilityService->isAvailable($product, $startDate, $endDate, $quantity);
        $stockQuantity = $product->stock_quantity ?? 0;

        // Get the max booked units across the date range for reporting
        $bookedUnitsPerDay = $this->availabilityService->getBookedUnitsPerDay($product, $startDate, $endDate);
        $maxBookedUnits = !empty($bookedUnitsPerDay) ? max($bookedUnitsPerDay) : 0;

        return [
            'available' => $available,
            'booked_units' => $maxBookedUnits,
            'total_units' => $stockQuantity,
        ];
    }

    /**
     * Get unavailable dates for a product within a date range.
     * A date is unavailable when booked_units >= effective stock OR falls in cooldown.
     *
     * For tracked products, effective stock = count of non-decommissioned/non-repair assets.
     * For non-tracked products, uses stock_quantity.
     *
     * @return Collection<int, Carbon> dates that are fully booked or in cooldown
     */
    public function getUnavailableDates(Product $product, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $stockQuantity = $product->track_individual_assets
            ? $product->assets()->whereIn('asset_status', ['Available', 'Reserved', 'Rented Out'])->count()
            : $product->stock_quantity;

        // If stock is unlimited (null), no dates are unavailable
        if ($stockQuantity === null) {
            return collect();
        }

        $bookedUnitsPerDay = $this->availabilityService->getBookedUnitsPerDay($product, $rangeStart, $rangeEnd);

        $unavailableDates = collect();

        $period = CarbonPeriod::create($rangeStart, $rangeEnd);

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $usedUnits = $bookedUnitsPerDay[$key] ?? 0;

            if ($usedUnits >= $stockQuantity) {
                $unavailableDates->push($date->copy());
            }
        }

        return $unavailableDates;
    }

    /**
     * Create a booking with pessimistic locking.
     * Acquires FOR UPDATE lock on bookings for the product/date range,
     * re-checks availability, then inserts.
     *
     * @throws BookingConflictException if dates are no longer available
     */
    public function createBooking(
        OrderItem $item,
        Product $product,
        Carbon $startDate,
        Carbon $endDate,
        int $quantity,
        ?string $signatureData = null,
        ?string $agreementText = null
    ): Booking {
        return DB::transaction(function () use ($item, $product, $startDate, $endDate, $quantity, $signatureData, $agreementText) {
            // Acquire pessimistic lock on overlapping bookings for this product
            Booking::forProduct($product->id)
                ->where(function ($query) {
                    $query->where('status', 'confirmed')
                          ->orWhere('status', 'active');
                })
                ->overlapping($startDate, $endDate)
                ->lockForUpdate()
                ->get();

            // Re-check availability after acquiring the lock
            $available = $this->availabilityService->isAvailable($product, $startDate, $endDate, $quantity);

            if (!$available) {
                throw new BookingConflictException('The selected dates are no longer available');
            }

            // Calculate total price
            $totalPrice = $this->calculateTotal($product, $startDate, $endDate, $quantity);

            // Create the booking record
            $booking = Booking::create([
                'order_item_id' => $item->id,
                'product_id' => $product->id,
                'company_id' => $item->order->company_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'status' => 'confirmed',
                'fulfilment_stage' => 'ordered',
                'signature_data' => $signatureData,
                'agreement_accepted_at' => $agreementText ? now() : null,
                'agreement_text_snapshot' => $agreementText,
            ]);

            // Link the booking to the order item
            $item->update(['booking_id' => $booking->id]);

            return $booking;
        });
    }

    /**
     * Mark a booking as returned and record timestamp.
     */
    public function markReturned(Booking $booking): void
    {
        $booking->update([
            'status' => 'returned',
            'returned_at' => now(),
        ]);
    }

    /**
     * Calculate total price: product.price × days × quantity.
     * Days are inclusive (end_date - start_date + 1 day).
     */
    public function calculateTotal(Product $product, Carbon $startDate, Carbon $endDate, int $quantity): float
    {
        $days = $startDate->diffInDays($endDate) + 1;

        return (float) $product->price * $days * $quantity;
    }
}
