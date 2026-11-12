<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

/**
 * Creates Stripe billing for an admin-created Service. Must be called AFTER the
 * Service transaction has committed. A Stripe failure never removes the Service;
 * it is flagged billing_failed with the error so an admin can retry.
 */
class ServiceBillingService
{
    public const STATE_FAILED = 'billing_failed';

    public function __construct(private StripeService $stripe)
    {
    }

    /**
     * @param array $data validated input: stripe_price_id, contact_email (optional)
     * @return bool true when billing was set up (or nothing to do), false on failure
     */
    public function setup(Service $service, array $data): bool
    {
        $priceId = $data['stripe_price_id'] ?? null;
        if (empty($priceId)) {
            return true;
        }

        try {
            $customer = Customer::findOrFail($service->company_id);
            $stripeCustomerId = $this->stripe->ensureCustomerWithEmail($customer, $data['contact_email'] ?? null);

            $subscription = $this->stripe->createInvoicedSubscription($stripeCustomerId, $priceId, [
                'service_id' => $service->service_id,
                'company_id' => $service->company_id,
            ]);

            $service->forceFill([
                'stripe_subscription_id' => $subscription->id,
                'billing_state' => null,
                'billing_error' => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            Log::error('Service billing setup failed', [
                'service_id' => $service->service_id,
                'error' => $e->getMessage(),
            ]);

            $service->forceFill([
                'billing_state' => self::STATE_FAILED,
                'billing_error' => $e->getMessage(),
            ])->save();

            return false;
        }
    }
}
