<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReviewWizardController::moveToDomain links the Service and never deletes it.
 *
 * Validates: Requirements 15.7
 */
class ReviewWizardMoveToDomainTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->is_admin = true;
        $this->admin->save();
    }

    private function move(Service $service, Customer $customer): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cleanup.review.move-to-domain'), [
                'service_id' => $service->service_id,
                'customer' => $customer->company_id,
            ])
            ->assertRedirect();
    }

    public function test_service_survives_is_linked_and_billing_data_moves_to_new_domain(): void
    {
        $customer = Customer::factory()->create();
        $service = Service::factory()->create([
            'company_id' => $customer->company_id,
            'domain_name' => 'Moved-Example.com',
            'stripe_subscription_id' => 'sub_123',
            'service_monthly_charge' => 12.50,
        ]);

        $this->move($service, $customer);

        $service->refresh();
        $domain = Domain::where('domain_name', 'moved-example.com')->firstOrFail();
        $this->assertSame($domain->id, $service->domain_id);
        $this->assertSame('sub_123', $domain->stripe_subscription_id);
        $this->assertEquals(12.50, $domain->cost);
        $this->assertNull($service->stripe_subscription_id);
    }

    public function test_existing_domain_values_are_kept(): void
    {
        $customer = Customer::factory()->create();
        $domain = Domain::factory()->create([
            'company_id' => $customer->company_id,
            'domain_name' => 'kept-example.com',
            'stripe_subscription_id' => 'sub_domain',
            'cost' => 9.99,
        ]);
        $service = Service::factory()->create([
            'company_id' => $customer->company_id,
            'domain_name' => 'kept-example.com',
            'stripe_subscription_id' => 'sub_service',
            'service_monthly_charge' => 50,
        ]);

        $this->move($service, $customer);

        $domain->refresh();
        $service->refresh();
        $this->assertSame('sub_domain', $domain->stripe_subscription_id);
        $this->assertEquals(9.99, $domain->cost);
        $this->assertSame($domain->id, $service->domain_id);
        $this->assertSame('sub_service', $service->stripe_subscription_id);
        $this->assertNull($service->deleted_at ?? null);
    }
}
