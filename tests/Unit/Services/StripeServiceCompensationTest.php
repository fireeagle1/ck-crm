<?php

namespace Tests\Unit\Services;

use App\Services\StripeService;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * StripeService helpers for coupons and compensation, with the Stripe HTTP client faked.
 * Validates: Requirements 5.4, 5.6
 */
class StripeServiceCompensationTest extends TestCase
{
    /** @var list<array{method: string, url: string, params: array}> */
    private array $requests = [];

    /** @var list<array{0: int, 1: array}> queued [status, body] responses */
    private array $responses = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.secret' => 'sk_test_fake']);

        $test = $this;
        ApiRequestor::setHttpClient(new class($test) implements ClientInterface {
            public function __construct(private StripeServiceCompensationTest $test)
            {
            }

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                [$status, $body] = $this->test->nextResponse($method, $absUrl, $params);

                return [json_encode($body), $status, []];
            }
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    /** @internal called by the fake HTTP client */
    public function nextResponse(string $method, string $url, array $params): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'params' => $params];

        $object = str_contains($url, 'coupons') ? 'coupon' : 'payment_intent';

        return array_shift($this->responses) ?? [200, ['id' => 'obj_default', 'object' => $object]];
    }

    private function service(): StripeService
    {
        return new StripeService();
    }

    public function test_coupon_uses_configured_currency_and_defaults(): void
    {
        config(['services.stripe.currency' => 'eur']);
        $this->responses = [[200, ['id' => 'co_1', 'object' => 'coupon']]];

        $coupon = $this->service()->createDiscountCoupon(['amount_off' => 500]);

        $this->assertSame('co_1', $coupon->id);
        $params = $this->requests[0]['params'];
        $this->assertStringEndsWith('/v1/coupons', $this->requests[0]['url']);
        $this->assertSame('eur', $params['currency']);
        $this->assertSame(500, $params['amount_off']);
        $this->assertSame('once', $params['duration']);
        $this->assertSame('Discount', $params['name']);
    }

    public function test_coupon_defaults_to_gbp_and_omits_currency_for_percent_off(): void
    {
        config(['services.stripe.currency' => null]);
        $this->service()->createDiscountCoupon(['amount_off' => 100]);
        $this->assertSame('gbp', $this->requests[0]['params']['currency']);

        $this->service()->createDiscountCoupon(['percent_off' => 10]);
        $this->assertArrayNotHasKey('currency', $this->requests[1]['params']);
    }

    public function test_coupon_failure_becomes_502(): void
    {
        $this->responses = [[400, ['error' => ['message' => 'bad']]]];

        try {
            $this->service()->createDiscountCoupon(['amount_off' => 100]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
        }
    }

    public function test_expire_checkout_session_retrieves_then_expires(): void
    {
        $this->responses = [
            [200, ['id' => 'cs_1', 'object' => 'checkout.session']],
            [200, ['id' => 'cs_1', 'object' => 'checkout.session', 'status' => 'expired']],
        ];

        $this->service()->expireCheckoutSession('cs_1');

        $this->assertSame('get', strtolower($this->requests[0]['method']));
        $this->assertStringEndsWith('/v1/checkout/sessions/cs_1', $this->requests[0]['url']);
        $this->assertStringEndsWith('/v1/checkout/sessions/cs_1/expire', $this->requests[1]['url']);
    }

    public function test_cancel_subscription_retrieves_then_deletes(): void
    {
        $this->responses = [
            [200, ['id' => 'sub_1', 'object' => 'subscription']],
            [200, ['id' => 'sub_1', 'object' => 'subscription', 'status' => 'canceled']],
        ];

        $this->service()->cancelSubscription('sub_1');

        $this->assertStringEndsWith('/v1/subscriptions/sub_1', $this->requests[1]['url']);
        $this->assertSame('delete', strtolower($this->requests[1]['method']));
    }

    public function test_compensation_failures_become_502(): void
    {
        $this->responses = [[404, ['error' => ['message' => 'No such session']]]];
        try {
            $this->service()->expireCheckoutSession('cs_x');
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
        }

        $this->responses = [[404, ['error' => ['message' => 'No such subscription']]]];
        $this->expectException(HttpException::class);
        $this->service()->cancelSubscription('sub_x');
    }

    public function test_one_time_payment_uses_configured_currency(): void
    {
        config(['services.stripe.currency' => 'usd']);
        $this->service()->createOneTimePayment(1000, 'cus_1');

        $this->assertSame('usd', $this->requests[0]['params']['currency']);
    }
}
