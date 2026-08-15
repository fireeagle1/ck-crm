<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Customer as StripeCustomer;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Ensure a Stripe customer exists for the given customer.
     * If the customer already has a stripe_customer_id, return it.
     * Otherwise, create a new Stripe customer and persist the ID.
     */
    public function ensureCustomer(Customer $customer): string
    {
        if ($customer->stripe_customer_id) {
            return $customer->stripe_customer_id;
        }

        try {
            $params = [
                'name' => $customer->company_name,
                'metadata' => [
                    'company_id' => $customer->company_id,
                ],
            ];

            // Use the first user's email if available
            $firstUser = $customer->users()->first();
            if ($firstUser && $firstUser->email) {
                $params['email'] = $firstUser->email;
            }

            $stripeCustomer = StripeCustomer::create($params);

            $customer->update(['stripe_customer_id' => $stripeCustomer->id]);

            return $stripeCustomer->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to create customer', [
                'company_id' => $customer->company_id,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to create Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Create a Stripe Checkout Session for one-off payments.
     */
    public function createCheckoutSession(
        string $stripeCustomerId,
        array $lineItems,
        string $successUrl,
        string $cancelUrl
    ): Session {
        try {
            return Session::create([
                'customer' => $stripeCustomerId,
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to create checkout session', [
                'customer' => $stripeCustomerId,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to create checkout session: ' . $e->getMessage());
        }
    }

    /**
     * Create a Stripe Subscription for recurring items.
     */
    public function createSubscription(
        string $stripeCustomerId,
        string $priceId,
        array $metadata = []
    ): Subscription {
        try {
            return Subscription::create([
                'customer' => $stripeCustomerId,
                'items' => [
                    ['price' => $priceId],
                ],
                'metadata' => $metadata,
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to create subscription', [
                'customer' => $stripeCustomerId,
                'price_id' => $priceId,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to create subscription: ' . $e->getMessage());
        }
    }

    /**
     * Verify the Stripe webhook signature and return the parsed event.
     */
    public function verifyWebhookSignature(string $payload, string $sigHeader): Event
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            return Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe: Webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(400, 'Invalid webhook signature.');
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe: Invalid webhook payload', [
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(400, 'Invalid webhook payload.');
        }
    }
}
