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

            throw new HttpException(502, 'Unable to create Stripe customer. Please try again or contact support.');
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

            throw new HttpException(502, 'Unable to process payment. Please try again or contact support.');
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

            throw new HttpException(502, 'Unable to initiate checkout. Please try again or contact support.');
        }
    }

    /**
     * Create a Stripe Checkout Session with full params (supports discounts, metadata, etc.).
     */
    public function createCheckoutSessionWithParams(array $params): Session
    {
        try {
            return Session::create($params);
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to create checkout session', [
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to initiate checkout. Please try again or contact support.');
        }
    }

    /**
     * Create a refund for a payment intent.
     *
     * @param string $paymentIntentId The Stripe payment intent ID.
     * @param int|null $amount Amount in pence to refund (null = full refund).
     * @param string $reason Reason for refund: duplicate, fraudulent, requested_by_customer.
     * @return \Stripe\Refund
     */
    public function createRefund(string $paymentIntentId, ?int $amount = null, string $reason = 'requested_by_customer'): \Stripe\Refund
    {
        try {
            $params = [
                'payment_intent' => $paymentIntentId,
                'reason' => $reason,
            ];

            if ($amount !== null) {
                $params['amount'] = $amount;
            }

            return \Stripe\Refund::create($params);
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to create refund', [
                'payment_intent' => $paymentIntentId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to process refund. Please try again or contact support.');
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

            throw new HttpException(502, 'Unable to create subscription. Please try again or contact support.');
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
