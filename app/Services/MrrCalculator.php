<?php

namespace App\Services;

use App\Models\Service;

class MrrCalculator
{
    /**
     * Calculate true MRR by normalising each service's charge to a monthly value.
     *
     * For Stripe-managed services: `service_monthly_charge` stores the charge per
     * billing cycle (from Stripe's unit_amount), so we divide by the cycle length.
     *
     * For manually-entered services (no stripe_subscription_id): the charge is
     * already entered as a monthly amount by the admin, so we use it as-is.
     */
    public function calculate(): float
    {
        $services = Service::where('status', 'Active')
            ->whereNotNull('service_monthly_charge')
            ->where('service_monthly_charge', '>', 0)
            ->get(['service_monthly_charge', 'service_payment_frequency', 'stripe_subscription_id']);

        return $services->sum(function ($service) {
            $charge = (float) $service->service_monthly_charge;

            // Manual services are entered as monthly — no conversion needed
            if (empty($service->stripe_subscription_id)) {
                return $charge;
            }

            // Stripe-synced: divide by number of months in the billing cycle
            $months = match ($service->service_payment_frequency) {
                'Weekly'     => 0.25,   // ~1 week ≈ 0.25 months
                'Monthly'    => 1,
                'Quarterly'  => 3,
                'Biannually' => 6,
                'Annually'   => 12,
                'Biennially' => 24,
                default      => 1,
            };

            return $charge / $months;
        });
    }
}
