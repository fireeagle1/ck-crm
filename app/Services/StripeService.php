<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Coupon;
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
                'currency' => $this->currency(),
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
     * Create a discount coupon. Defaults: duration "once", name "Discount".
     * Pass either `amount_off` (smallest currency unit) or `percent_off`.
     * The configured currency is added only for fixed-amount coupons.
     *
     * @param array $params Stripe coupon params, merged over the defaults.
     */
    public function createDiscountCoupon(array $params = []): Coupon
    {
        $params += ['duration' => 'once', 'name' => 'Discount'];

        if (isset($params['amount_off']) && !isset($params['currency'])) {
            $params['currency'] = $this->currency();
        }

        try {
            return Coupon::create($params);
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to create coupon', [
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to apply discount. Please try again or contact support.');
        }
    }

    /**
     * Expire an open Checkout Session (compensation). Throws HttpException(502) on failure.
     */
    public function expireCheckoutSession(string $sessionId): void
    {
        try {
            Session::retrieve($sessionId)->expire();
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to expire checkout session', [
                'session' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to expire checkout session.');
        }
    }

    /**
     * Cancel a subscription immediately (compensation). Throws HttpException(502) on failure.
     */
    public function cancelSubscription(string $subscriptionId): void
    {
        try {
            Subscription::retrieve($subscriptionId)->cancel();
        } catch (ApiErrorException $e) {
            Log::error('Stripe: Failed to cancel subscription', [
                'subscription' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(502, 'Unable to cancel subscription.');
        }
    }

    /**
     * Ensure a Stripe customer exists with the given billing email (admin-created invoiced billing).
     * Falls back to the first user email. Throws InvalidArgumentException without an email.
     */
    public function ensureCustomerWithEmail(Customer $customer, ?string $contactEmail = null): string
    {
        $email = $contactEmail ?: $customer->users()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->value('email');

        if (!$email) {
            throw new \InvalidArgumentException(
                'A valid billing email is required to create an invoiced Stripe subscription.'
            );
        }

        if ($customer->stripe_customer_id) {
            $stripeCustomer = StripeCustomer::retrieve($customer->stripe_customer_id);

            if (($stripeCustomer->email ?? null) !== $email) {
                StripeCustomer::update($customer->stripe_customer_id, ['email' => $email]);
            }

            return $customer->stripe_customer_id;
        }

        $stripeCustomer = StripeCustomer::create([
            'name' => $customer->company_name,
            'email' => $email,
            'phone' => $customer->phone_number,
            'metadata' => ['company_id' => $customer->company_id],
        ]);

        $customer->update(['stripe_customer_id' => $stripeCustomer->id]);

        return $stripeCustomer->id;
    }

    /** Create a send_invoice subscription for an admin-created service. */
    public function createInvoicedSubscription(string $customerId, string $priceId, array $metadata = []): Subscription
    {
        return Subscription::create([
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'collection_method' => 'send_invoice',
            'days_until_due' => 7,
            'metadata' => $metadata,
        ]);
    }

    /** Currency for all Stripe amounts (Req 5.6). */
    protected function currency(): string
    {
        return (string) (config('services.stripe.currency', 'gbp') ?: 'gbp');
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
