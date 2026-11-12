<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\Coupon;
use Stripe\Event;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Subscription;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Test double for StripeService. Makes no network calls.
 *
 * - Every call is recorded with its arguments and DB::transactionLevel().
 * - A call can be made to fail (before any object is created) with failAt().
 * - Created and cancelled Stripe object ids are recorded.
 *
 * Includes the compensation helpers planned for StripeService (task 4.8):
 * createDiscountCoupon, expireCheckoutSession, cancelSubscription.
 */
class FakeStripeService extends StripeService
{
    /** @var list<array{method: string, args: array, transaction_level: int}> */
    public array $calls = [];

    /** @var array<string, int> method name => remaining calls to let through before failing (0 = fail next) */
    private array $failures = [];

    /** @var array<string, \Throwable> */
    private array $failureExceptions = [];

    /** @var array<string, list<string>> kind => ids, kinds: customer, coupon, session, subscription, payment_intent, refund */
    public array $created = [];

    /** @var array<string, list<string>> kind => ids, kinds: session (expired), subscription (cancelled) */
    public array $cancelled = [];

    private int $sequence = 0;

    /** Do not call the parent constructor: it sets the global Stripe API key. */
    public function __construct()
    {
    }

    /**
     * Make a method fail. $afterSuccesses lets that many calls succeed first.
     * Default exception mirrors the real service (HTTP 502).
     */
    public function failAt(string $method, ?\Throwable $e = null, int $afterSuccesses = 0): static
    {
        $this->failures[$method] = $afterSuccesses;
        $this->failureExceptions[$method] = $e ?? new HttpException(502, "Fake Stripe failure at {$method}.");

        return $this;
    }

    public function clearFailures(): static
    {
        $this->failures = [];
        $this->failureExceptions = [];

        return $this;
    }

    /** @return list<array{method: string, args: array, transaction_level: int}> */
    public function callsTo(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c['method'] === $method));
    }

    public function callCount(string $method): int
    {
        return count($this->callsTo($method));
    }

    /** @return list<int> transaction level seen at each call, in call order */
    public function transactionLevels(): array
    {
        return array_column($this->calls, 'transaction_level');
    }

    /** @return list<string> */
    public function createdIds(string $kind): array
    {
        return $this->created[$kind] ?? [];
    }

    /** @return list<string> */
    public function cancelledIds(string $kind): array
    {
        return $this->cancelled[$kind] ?? [];
    }

    // ------------------------------------------------------------------
    // StripeService API
    // ------------------------------------------------------------------

    public function ensureCustomer(Customer $customer): string
    {
        $this->record(__FUNCTION__, ['company_id' => $customer->company_id]);

        if ($customer->stripe_customer_id) {
            return $customer->stripe_customer_id;
        }

        $id = $this->nextId('cus');
        $this->created['customer'][] = $id;
        $customer->update(['stripe_customer_id' => $id]);

        return $id;
    }

    public function createOneTimePayment(int $amount, string $customerId, array $metadata = []): PaymentIntent
    {
        $this->record(__FUNCTION__, compact('amount', 'customerId', 'metadata'));

        $id = $this->nextId('pi');
        $this->created['payment_intent'][] = $id;

        return PaymentIntent::constructFrom([
            'id' => $id,
            'amount' => $amount,
            'currency' => config('services.stripe.currency', 'gbp'),
            'customer' => $customerId,
            'metadata' => $metadata,
            'client_secret' => $id . '_secret',
        ]);
    }

    public function createCheckoutSession(
        string $stripeCustomerId,
        array $lineItems,
        string $successUrl,
        string $cancelUrl
    ): Session {
        $this->record(__FUNCTION__, compact('stripeCustomerId', 'lineItems', 'successUrl', 'cancelUrl'));

        return $this->makeSession(['customer' => $stripeCustomerId, 'line_items' => $lineItems]);
    }

    public function createCheckoutSessionWithParams(array $params): Session
    {
        $this->record(__FUNCTION__, ['params' => $params]);

        return $this->makeSession($params);
    }

    public function createRefund(string $paymentIntentId, ?int $amount = null, string $reason = 'requested_by_customer'): Refund
    {
        $this->record(__FUNCTION__, compact('paymentIntentId', 'amount', 'reason'));

        $id = $this->nextId('re');
        $this->created['refund'][] = $id;

        return Refund::constructFrom([
            'id' => $id,
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }

    public function createSubscription(string $stripeCustomerId, string $priceId, array $metadata = []): Subscription
    {
        $this->record(__FUNCTION__, compact('stripeCustomerId', 'priceId', 'metadata'));

        $id = $this->nextId('sub');
        $this->created['subscription'][] = $id;

        return Subscription::constructFrom([
            'id' => $id,
            'customer' => $stripeCustomerId,
            'status' => 'incomplete',
            'metadata' => $metadata,
            'items' => ['data' => [['price' => ['id' => $priceId]]]],
        ]);
    }

    public function verifyWebhookSignature(string $payload, string $sigHeader): Event
    {
        $this->record(__FUNCTION__, compact('payload', 'sigHeader'));

        return Event::constructFrom(json_decode($payload, true) ?: []);
    }

    // ------------------------------------------------------------------
    // Compensation helpers (planned in task 4.8)
    // ------------------------------------------------------------------

    public function createDiscountCoupon(array $params = []): Coupon
    {
        $this->record(__FUNCTION__, ['params' => $params]);

        $id = $this->nextId('coupon');
        $this->created['coupon'][] = $id;

        return Coupon::constructFrom(['id' => $id] + $params);
    }

    public function expireCheckoutSession(string $sessionId): void
    {
        $this->record(__FUNCTION__, compact('sessionId'));
        $this->cancelled['session'][] = $sessionId;
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->record(__FUNCTION__, compact('subscriptionId'));
        $this->cancelled['subscription'][] = $subscriptionId;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function makeSession(array $params): Session
    {
        $id = $this->nextId('cs_test');
        $this->created['session'][] = $id;

        return Session::constructFrom([
            'id' => $id,
            'url' => "https://checkout.stripe.test/pay/{$id}",
            'client_reference_id' => $params['client_reference_id'] ?? null,
            'metadata' => $params['metadata'] ?? [],
            'customer' => $params['customer'] ?? null,
        ]);
    }

    private function nextId(string $prefix): string
    {
        return $prefix . '_fake_' . (++$this->sequence);
    }

    /**
     * Record the call (with transaction level) and throw if configured to fail.
     * Recording happens first so failed calls are visible too.
     */
    private function record(string $method, array $args): void
    {
        $this->calls[] = [
            'method' => $method,
            'args' => $args,
            'transaction_level' => DB::transactionLevel(),
        ];

        if (array_key_exists($method, $this->failures)) {
            if ($this->failures[$method] > 0) {
                $this->failures[$method]--;

                return;
            }

            throw $this->failureExceptions[$method];
        }
    }
}
