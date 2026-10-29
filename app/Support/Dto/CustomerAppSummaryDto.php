<?php

namespace App\Support\Dto;

use App\Models\Service;
use App\Support\SecuritySummaryService;

/**
 * Hand-built, allow-listed transformer for the customer-facing app summary.
 *
 * The customer surface must never receive technical metadata. This DTO
 * returns a fixed associative array of allow-listed keys. Besides the
 * Service-derived name/status/billing and the derived security summary, it
 * surfaces the two customer-safe app fields (a pretty display name and the
 * custom installation URL). It never calls toArray() on a model and never
 * touches CustomerApp::repo_url / hosting_location / codebase_info / packages /
 * findings detail, so those values are structurally absent from the output
 * (Req 5.1, 5.3, 5.4).
 */
final class CustomerAppSummaryDto
{
    /**
     * Build the customer-safe summary array for the given Service.
     *
     * The display name prefers the app's own pretty name and falls back to the
     * service short name so the customer always sees a human label rather than
     * a raw URL. The install URL is the custom location where the app lives.
     *
     * @return array{name: string|null, install_url: string|null, status: string|null, billing: string, security_summary: string}
     */
    public static function fromService(Service $service): array
    {
        $app = $service->customerApp;

        return [
            'name'             => $app?->display_name ?: $service->service_short,
            'install_url'      => $app?->install_url,
            'status'           => $service->status,
            'billing'          => self::billing($service),
            'security_summary' => SecuritySummaryService::for($app),
        ];
    }

    /**
     * Derive the billing line from the Service record alone (Req 5.1, 1.5).
     *
     * A service with no Stripe link and no monthly charge is "Free";
     * otherwise the stored charge is formatted as a currency amount,
     * suffixed with the payment frequency when one is recorded. Billing is
     * never duplicated onto CustomerApp.
     */
    private static function billing(Service $service): string
    {
        $charge = $service->service_monthly_charge;
        $hasCharge = $charge !== null && (float) $charge > 0;

        if (empty($service->stripe_subscription_id) && ! $hasCharge) {
            return 'Free';
        }

        $amount = '£' . number_format((float) $charge, 2);

        return $service->service_payment_frequency
            ? "{$amount}/{$service->service_payment_frequency}"
            : $amount;
    }
}
