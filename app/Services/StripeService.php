<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Customer as StripeCustomer;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
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
     * If the customer has a stripe_customer_id, verify it exists on Stripe.
     * If Stripe returns "customer not found", recreate the customer and update the record.
     * If no stripe_customer_id exists, create a new Stripe customer.
     */
    public function ensureCustomer(Customer $customer): string
    {
        if ($customer->stripe_customer_id) {
            try {
                StripeCustomer::retrieve($customer->stripe_customer_id);
                return $customer->stripe_customer_id;
            } catch (InvalidRequestException $e) {
                if (str_contains($e->getMessage(), 'No such customer')) {
                    Log::warning('Stripe customer not found, recreating', [
                        'company_id' => $customer->company_id,
                        'old_stripe_customer_id' => $customer->stripe_customer_id,
                    ]);
                    // Fall through to creation below
                } else {
                    throw $e;
                }
            }
        }

        try {
            $stripeCustomer = StripeCustomer::create([
                'name' => $customer->company_name,
                'email' => $customer->users()->first()?->email,
                'metadata' => ['company_id' => $customer->company_id],
            ]);

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
     * Create a one-time Stripe PaymentIntent for rental bookings.
     *
     * @param int $amount Amount in the smallest currency unit (e.g., pence for GBP)
     * @param string $customerId Stripe customer ID
     * @param array $metadata Additional metadata to attach to the PaymentIntent
     * @return PaymentIntent
     */
    public function createOneTimePayment(int $amount, string $customerId, array $metadata = []): PaymentIntent
    {
        try {
            return PaymentIntent::create([
                'amount' => $amount,
                'currency' => config('services.stripe.currency', 'gbp'),
                'customer' => $customerId,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to create one-time payment', [
                'customer' => $customerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to create payment: ' . $e->getMessage());
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
