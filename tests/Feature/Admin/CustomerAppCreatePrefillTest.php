<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\Domain;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validates: Requirements 16.6, 21.2
 */
class CustomerAppCreatePrefillTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    public function test_create_form_prefills_domain_and_install_url_from_service(): void
    {
        $customer = Customer::factory()->create();
        $domain = Domain::factory()->create(['company_id' => $customer->company_id, 'domain_name' => 'acme-example.test']);
        $service = Service::factory()->create(['company_id' => $customer->company_id, 'domain_id' => $domain->id]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.customer-apps.create', $service))
            ->assertOk()
            ->assertSee('name="domain_id"', false)
            ->assertSee('value="https://acme-example.test"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$domain->id.'"\s+selected/', $html);
    }

    public function test_store_persists_prefilled_domain_id(): void
    {
        $customer = Customer::factory()->create();
        $domain = Domain::factory()->create(['company_id' => $customer->company_id, 'domain_name' => 'acme-example.test']);
        $service = Service::factory()->create(['company_id' => $customer->company_id, 'domain_id' => $domain->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.customer-apps.store', $service), [
                'domain_id' => $domain->id,
                'install_url' => 'https://acme-example.test',
            ])->assertRedirect();

        $this->assertSame($domain->id, CustomerApp::where('service_id', $service->service_id)->first()->domain_id);
    }

    public function test_pick_screen_links_to_the_single_create_route(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.customer-apps.pick'))
            ->assertOk()
            ->assertSee(route('admin.customer-apps.create', $service), false);

        $creates = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()) && str_ends_with($r->getName() ?? '', 'customer-apps.create'));
        $this->assertCount(1, $creates);
    }
}
