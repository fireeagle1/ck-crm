<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

/**
 * stripe:sync must not activate Pending shop services.
 * Validates: Requirements 1.6, 9.6
 */
class StripeSyncInvoicesGuardTest extends TestCase
{
    use RefreshDatabase;

    private array $subscriptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.secret' => 'sk_test_fake']);
        \Stripe\Stripe::setApiKey('sk_test_fake');

        $test = $this;
        ApiRequestor::setHttpClient(new class($test) implements ClientInterface {
            public function __construct(private StripeSyncInvoicesGuardTest $test)
            {
            }

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $data = str_contains($absUrl, 'subscriptions') ? $this->test->subscriptions() : [];

                return [json_encode(['object' => 'list', 'data' => $data, 'has_more' => false, 'url' => '/v1/x']), 200, []];
            }
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    /** @internal called by the fake HTTP client */
    public function subscriptions(): array
    {
        return $this->subscriptions;
    }

    private function subscription(string $id): array
    {
        return [
            'id' => $id,
            'object' => 'subscription',
            'status' => 'active',
            'current_period_end' => strtotime('2030-01-15'),
            'canceled_at' => null,
            'items' => ['object' => 'list', 'data' => [[
                'id' => 'si_1',
                'object' => 'subscription_item',
                'price' => ['id' => 'price_1', 'object' => 'price', 'unit_amount' => 2500, 'recurring' => ['interval' => 'month', 'interval_count' => 1]],
            ]]],
        ];
    }

    private function runSync(): void
    {
        Customer::factory()->create(['stripe_customer_id' => 'cus_1']);
        $this->artisan('stripe:sync')->assertSuccessful();
    }

    public function test_pending_shop_service_is_not_activated_but_charge_and_dates_update(): void
    {
        $item = OrderItem::factory()->hosting('shop.example.com')->create(['order_id' => Order::factory()->create()->id]);
        $service = Service::factory()->webHosting()->pending()->create([
            'order_item_id' => $item->id,
            'stripe_subscription_id' => 'sub_shop',
        ]);
        $this->subscriptions = [$this->subscription('sub_shop')];

        $this->runSync();

        $service->refresh();
        $this->assertSame('Pending', $service->status);
        $this->assertEquals(25, $service->service_monthly_charge);
        $this->assertSame('Monthly', $service->service_payment_frequency);
        $this->assertSame('2030-01-15', $service->next_payment_date->toDateString());
    }

    public function test_hand_created_pending_service_still_syncs_status(): void
    {
        $service = Service::factory()->pending()->create([
            'order_item_id' => null,
            'stripe_subscription_id' => 'sub_manual',
        ]);
        $this->subscriptions = [$this->subscription('sub_manual')];

        $this->runSync();

        $service->refresh();
        $this->assertSame('Active', $service->status);
        $this->assertEquals(25, $service->service_monthly_charge);
    }
}
