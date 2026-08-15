<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AvailabilityService
{
    /**
     * For a given product and date range, return the number of booked units per day.
     * Accounts for cooldown_days after each booking's end_date.
     *
     * @return array<string, int> date (Y-m-d) => booked_units
     */
    public function getBookedUnitsPerDay(Product $product, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $cooldownDays = $product->cooldown_days ?? 0;

        // Query confirmed/active bookings that overlap the range.
        // We need to expand the query window to catch bookings whose cooldown
        // spills into our range: a booking ending as early as (rangeStart - cooldownDays)
        // can still occupy days in our range via its cooldown period.
        $queryStart = $cooldownDays > 0
            ? $rangeStart->copy()->subDays($cooldownDays)
            : $rangeStart->copy();

        $bookings = Booking::forProduct($product->id)
            ->where(function ($query) {
                $query->where('status', 'confirmed')
                      ->orWhere('status', 'active');
            })
            ->overlapping($queryStart, $rangeEnd)
            ->get(['start_date', 'end_date', 'quantity']);

        $bookedUnits = [];

        foreach ($bookings as $booking) {
            // Calculate the booking's occupied period: the actual rental days
            $bookingStart = $booking->start_date->copy();
            $bookingEnd = $booking->end_date->copy();

            // Also include cooldown days after the booking ends
            $effectiveEnd = $cooldownDays > 0
                ? $bookingEnd->copy()->addDays($cooldownDays)
                : $bookingEnd->copy();

            // Iterate each day from the booking start through effective end (inclusive)
            $periodStart = $bookingStart->max($rangeStart);
            $periodEnd = $effectiveEnd->min($rangeEnd);

            if ($periodStart->greaterThan($periodEnd)) {
                continue;
            }

            $period = CarbonPeriod::create($periodStart, $periodEnd);

            foreach ($period as $date) {
                $key = $date->format('Y-m-d');
                $bookedUnits[$key] = ($bookedUnits[$key] ?? 0) + $booking->quantity;
            }
        }

        return $bookedUnits;
    }

    /**
     * Determine if the requested quantity is available for every day in the range.
     * A date is available if (stock_quantity - booked_units_on_that_date) >= requested quantity.
     */
    public function isAvailable(Product $product, Carbon $startDate, Carbon $endDate, int $quantity): bool
    {
        $stockQuantity = $product->stock_quantity;

        // If stock is null (unlimited), always available
        if ($stockQuantity === null) {
            return true;
        }

        $bookedUnits = $this->getBookedUnitsPerDay($product, $startDate, $endDate);

        // Check every day in the requested range
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $usedUnits = $bookedUnits[$key] ?? 0;
            $remaining = $stockQuantity - $usedUnits;

            if ($remaining < $quantity) {
                return false;
            }
        }

        return true;
    }
}
