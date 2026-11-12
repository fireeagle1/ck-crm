<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerApp;
use App\Models\Domain;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Customer app index/show link to the linked Domain and Service.
 *
 * Validates: Requirements 21.4
 */
class CustomerAppDomainServiceLinksTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->is_admin = true;
        $admin->save();

        return $admin;
    }

    public function test_index_and_show_link_domain_and_service(): void
    {
        $service = Service::factory()->create();
        $domain = Domain::factory()->create(['domain_name' => 'linked-example.com']);
        $app = CustomerApp::factory()->forService($service)->create(['domain_id' => $domain->id]);

        foreach ([route('admin.customer-apps.index'), route('admin.customer-apps.show', $app)] as $url) {
            $this->actingAs($this->admin())
                ->get($url)
                ->assertOk()
                ->assertSee('linked-example.com')
                ->assertSee(route('admin.domains.edit', $domain), false)
                ->assertSee(route('admin.services.show', $service), false);
        }
    }

    public function test_app_without_domain_renders(): void
    {
        $app = CustomerApp::factory()->create(['domain_id' => null]);

        $this->actingAs($this->admin())->get(route('admin.customer-apps.index'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.customer-apps.show', $app))->assertOk();
    }

    public function test_index_eager_loads_domains_without_n_plus_one(): void
    {
        foreach (range(1, 4) as $i) {
            CustomerApp::factory()->create(['domain_id' => Domain::factory()->create()->id]);
        }

        $admin = $this->admin();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.customer-apps.index'))->assertOk();
        $domainQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'from `domains`') || str_contains($q['query'], 'from "domains"'))
            ->count();

        $this->assertSame(1, $domainQueries);
    }
}
