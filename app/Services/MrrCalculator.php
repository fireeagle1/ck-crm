<?php

namespace App\Services;

use App\Models\Service;

class MrrCalculator
{
    /**
     * Number of months covered by one billing cycle for each frequency.
     */
    private const CYCLE_MONTHS = [
        'Weekly'     => 12 / 52,
        'Monthly'    => 1,
        'Quarterly'  => 3,
        'Biannually' => 6,
        'Annually'   => 12,
        'Biennially' => 24,
    ];

    /**
     * Calculate true MRR by normalising each active service's charge to a monthly value.
     *
     * `service_monthly_charge` holds the charge per billing cycle (Stripe's unit_amount for
     * synced services, or the amount the admin entered alongside a frequency), so every
     * service is divided by the length of its billing cycle. A missing frequency is
     * treated as monthly.
     */
    public function calculate(): float
    {
        return array_sum($this->breakdownByType());
    }

    /**
     * Monthly-normalised revenue grouped by service type, highest first.
     *
     * @return array<string, float>
     */
    public function breakdownByType(): array
    {
        $services = Service::where('status', 'Active')
            ->whereNotNull('service_monthly_charge')
            ->where('service_monthly_charge', '>', 0)
            ->get(['service_type', 'service_monthly_charge', 'service_payment_frequency']);

        $totals = [];
        foreach ($services as $service) {
            $type = $service->service_type ?: 'Other';
            $totals[$type] = ($totals[$type] ?? 0) + $this->monthlyValue($service);
        }

        arsort($totals);

        return array_map(fn ($v) => round($v, 2), $totals);
    }

    public function monthlyValue(Service $service): float
    {
        $months = self::CYCLE_MONTHS[$service->service_payment_frequency] ?? 1;

        return (float) $service->service_monthly_charge / $months;
    }
}
