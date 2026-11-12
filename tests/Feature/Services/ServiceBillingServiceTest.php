<?php

namespace Tests\Feature\Services;

use App\Models\Customer;
use App\Models\Service;
use App\Services\ServiceBillingService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Subscription;
use Tests\TestCase;

/** Validates: Requirements 18.1, 18.2 */
class ServiceBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $attrs = []): Service
    {
        $customer = Customer::factory()->create();

        return Service::factory()->create(array_merge(['company_id' => $customer->company_id], $attrs));
    }

    public function test_success_stores_subscription_and_clears_state(): void
    {
        $service = $this->service(['billing_state' => 'billing_failed', 'billing_error' => 'old']);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('ensureCustomerWithEmail')->once()->andReturn('cus_1');
        $stripe->shouldReceive('createInvoicedSubscription')
            ->once()
            ->with('cus_1', 'price_1', Mockery::type('array'))
            ->andReturn(Subscription::constructFrom(['id' => 'sub_1']));

        $ok = (new ServiceBillingService($stripe))->setup($service, [
            'stripe_price_id' => 'price_1',
            'contact_email' => 'a@example.com',
        ]);

        $this->assertTrue($ok);
        $service->refresh();
        $this->assertSame('sub_1', $service->stripe_subscription_id);
        $this->assertNull($service->billing_state);
        $this->assertNull($service->billing_error);
    }

    public function test_failure_sets_billing_failed_and_service_persists(): void
    {
        $service = $this->service();

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('ensureCustomerWithEmail')->once()->andReturn('cus_1');
        $stripe->shouldReceive('createInvoicedSubscription')->once()
            ->andThrow(new \RuntimeException('Stripe is down'));

        $ok = (new ServiceBillingService($stripe))->setup($service, ['stripe_price_id' => 'price_1']);

        $this->assertFalse($ok);
        $this->assertDatabaseHas('services', ['service_id' => $service->service_id]);
        $service->refresh();
        $this->assertSame('billing_failed', $service->billing_state);
        $this->assertSame('Stripe is down', $service->billing_error);
        $this->assertNull($service->stripe_subscription_id);
    }

    public function test_customer_failure_also_flags_service(): void
    {
        $service = $this->service();

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('ensureCustomerWithEmail')->once()
            ->andThrow(new \InvalidArgumentException('A valid billing email is required'));
        $stripe->shouldNotReceive('createInvoicedSubscription');

        $this->assertFalse((new ServiceBillingService($stripe))->setup($service, ['stripe_price_id' => 'price_1']));
        $this->assertSame('billing_failed', $service->fresh()->billing_state);
    }
}
