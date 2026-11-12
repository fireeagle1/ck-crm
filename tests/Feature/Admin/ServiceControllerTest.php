<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use App\Jobs\ProvisionHostingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Admin\ServiceController store/update validation via ServiceRequest.
 *
 * Validates: Requirements 1.3, 11.5
 */
class ServiceControllerTest extends TestCase
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

    private function payload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $customer->company_id,
            'service_short' => 'Hosting',
            'service_type' => 'Web Hosting',
            'status' => 'Active',
            'domain_name' => 'example.com',
        ], $overrides);
    }

    public function test_store_rejects_invalid_type_and_status(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.services.store'), $this->payload($customer, [
                'service_type' => 'Bogus',
                'status' => 'Nope',
            ]))
            ->assertSessionHasErrors(['service_type', 'status']);

        $this->assertSame(0, Service::count());
    }

    public function test_update_rejects_invalid_type_and_status(): void
    {
        $service = Service::factory()->webHosting()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.services.update', $service), [
                'service_type' => 'Bogus',
                'status' => 'Nope',
            ])
            ->assertSessionHasErrors(['service_type', 'status']);
    }

    public function test_store_saves_canonical_domain(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.services.store'), $this->payload($customer, [
                'domain_name' => ' WWW.Example.COM. ',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.services.index'));

        $this->assertSame('example.com', Service::firstOrFail()->domain_name);
    }

    public function test_update_saves_canonical_domain(): void
    {
        $service = Service::factory()->webHosting()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.services.update', $service), [
                'domain_name' => 'WWW.Updated.Example.com',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('updated.example.com', $service->fresh()->domain_name);
    }

    public function test_domain_variants_are_treated_as_duplicates(): void
    {
        $customer = Customer::factory()->create();
        Service::factory()->create([
            'company_id' => $customer->company_id,
            'service_type' => 'Web Hosting',
            'domain_name' => 'example.com',
        ]);

        foreach (['EXAMPLE.com', 'www.example.com', 'Example.com.'] as $variant) {
            $this->actingAs($this->admin)
                ->post(route('admin.services.store'), $this->payload($customer, ['domain_name' => $variant]))
                ->assertSessionHasErrors('domain_name');
        }

        $this->assertSame(1, Service::count());
    }

    public function test_duplicate_domain_is_rejected_across_customers_and_cancelled_services(): void
    {
        $other = Service::factory()->create([
            'service_type' => 'Web Hosting',
            'domain_name' => 'example.com',
            'status' => 'Cancelled',
        ]);
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.services.store'), $this->payload($customer))
            ->assertSessionHasErrors('domain_name');

        $this->assertSame(1, Service::count());
        $this->assertNotNull($other->fresh());
    }

    public function test_update_may_keep_its_own_domain(): void
    {
        $service = Service::factory()->create([
            'service_type' => 'Web Hosting',
            'domain_name' => 'example.com',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.services.update', $service), [
                'service_short' => 'Renamed',
                'domain_name' => 'www.example.com',
            ])
            ->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame('Renamed', $service->service_short);
        $this->assertSame('example.com', $service->domain_name);
    }

    public function test_store_rejects_domain_held_by_cancelled_service_via_domain_link_naming_it(): void
    {
        $customer = Customer::factory()->create();
        $domain = Domain::factory()->create(['company_id' => $customer->company_id, 'domain_name' => 'example.com']);
        // Holder is linked by domain_id only, so the request's unique rule does not catch it.
        $holder = Service::factory()->create([
            'service_type' => 'Web Hosting',
            'domain_name' => null,
            'domain_id' => $domain->getKey(),
            'status' => 'Cancelled',
            'service_short' => 'OldHosting',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.services.store'), $this->payload($customer));

        $response->assertSessionHasErrors('domain_name');
        $message = session('errors')->first('domain_name');
        $this->assertStringContainsString('example.com', $message);
        $this->assertStringContainsString("#{$holder->service_id}", $message);
        $this->assertSame(1, Service::count());
        $this->assertSame('Cancelled', (string) ($holder->fresh()->status->value ?? $holder->fresh()->status));
    }

    public function test_store_creates_domain_row_and_links_it(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.services.store'), $this->payload($customer, ['domain_name' => 'Brand-New.com']))
            ->assertSessionHasNoErrors();

        $domain = Domain::where('domain_name', 'brand-new.com')->firstOrFail();
        $this->assertSame($customer->company_id, (int) $domain->company_id);
        $this->assertSame($domain->getKey(), Service::firstOrFail()->domain_id);
    }

    public function test_store_reuses_existing_domain_row(): void
    {
        $customer = Customer::factory()->create();
        $domain = Domain::factory()->create(['company_id' => $customer->company_id, 'domain_name' => 'example.com']);

        $this->actingAs($this->admin)
            ->post(route('admin.services.store'), $this->payload($customer))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Domain::where('domain_name', 'example.com')->count());
        $this->assertSame($domain->getKey(), Service::firstOrFail()->domain_id);
    }

    public function test_store_with_provision_whm_queues_job_without_inline_whm_call(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        Http::fake();
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.services.store'), $this->payload($customer, [
                'provision_whm' => '1',
                'whm_package' => 'ckhostco_Basic',
                'cpanel_username' => 'wantedname',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.services.index'));

        Http::assertNothingSent();
        $service = Service::firstOrFail();
        $this->assertNull($service->cpanel_username);
        $this->assertSame('ckhostco_Basic', $service->whm_package);
        $this->assertSame('queued', $service->provisioning_status);
        Queue::assertPushed(
            ProvisionHostingJob::class,
            fn ($job) => $job->serviceId === $service->service_id && $job->requestedUsername === 'wantedname'
        );
    }

    public function test_store_without_provision_whm_maps_existing_account_and_dispatches_nothing(): void
    {
        Queue::fake();
        Http::fake();
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.services.store'), $this->payload($customer, [
                'cpanel_username' => 'existingacct',
                'whm_package' => 'ckhostco_Basic',
            ]))
            ->assertSessionHasNoErrors();

        $service = Service::firstOrFail();
        $this->assertSame('existingacct', $service->cpanel_username);
        $this->assertNull($service->whm_package);
        $this->assertNull($service->provisioning_status);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}
