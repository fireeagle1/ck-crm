<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Subscription;
use Tests\TestCase;

/** Validates: Requirements 18.2, 18.4 */
class ServiceRetryBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private function failedService(array $attrs = []): Service
    {
        $customer = Customer::factory()->create();

        return Service::factory()->create(array_merge([
            'company_id' => $customer->company_id,
            'billing_state' => 'billing_failed',
            'billing_error' => 'Stripe is down',
        ], $attrs));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->is_admin = true;
        $this->admin->save();
    }

    private function payload(): array
    {
        return ['stripe_price_id' => 'price_1', 'contact_email' => 'a@example.com'];
    }

    public function test_failed_service_shows_persistent_warning_with_retry(): void
    {
        $service = $this->failedService();

        $this->actingAs($this->admin)
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee('Billing setup failed')
            ->assertSee('Stripe is down')
            ->assertSee(route('admin.services.retry-billing', $service));
    }

    public function test_retry_success_clears_warning(): void
    {
        $service = $this->failedService();

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('ensureCustomerWithEmail')->andReturn('cus_1');
        $stripe->shouldReceive('createInvoicedSubscription')
            ->andReturn(Subscription::constructFrom(['id' => 'sub_1']));
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->admin)
            ->post(route('admin.services.retry-billing', $service), $this->payload())
            ->assertRedirect(route('admin.services.show', $service));

        $service->refresh();
        $this->assertNull($service->billing_state);
        $this->assertSame('sub_1', $service->stripe_subscription_id);

        $this->actingAs($this->admin)
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertDontSee('Billing setup failed');
    }

    public function test_retry_failure_keeps_warning(): void
    {
        $service = $this->failedService();

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('ensureCustomerWithEmail')->andThrow(new \RuntimeException('Still down'));
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->admin)
            ->post(route('admin.services.retry-billing', $service), $this->payload())
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHas('error');

        $this->assertSame('billing_failed', $service->fresh()->billing_state);

        $this->actingAs($this->admin)
            ->get(route('admin.services.show', $service))
            ->assertSee('Billing setup failed')
            ->assertSee('Still down');
    }

    public function test_non_failed_service_is_rejected(): void
    {
        $service = $this->failedService(['billing_state' => null, 'billing_error' => null]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('createInvoicedSubscription');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->admin)
            ->post(route('admin.services.retry-billing', $service), $this->payload())
            ->assertRedirect(route('admin.services.show', $service))
            ->assertSessionHas('error');

        $this->assertNull($service->fresh()->stripe_subscription_id);
    }

    public function test_non_admin_is_denied(): void
    {
        $service = $this->failedService();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('admin.services.retry-billing', $service), $this->payload())
            ->assertForbidden();

        $this->assertSame('billing_failed', $service->fresh()->billing_state);
    }
}
