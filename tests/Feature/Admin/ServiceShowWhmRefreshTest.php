<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Admin service show page makes no WHM call; WHM is queried on demand only.
 *
 * Validates: Requirements 7.4, 10.1, 10.2, 10.3
 */
class ServiceShowWhmRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->is_admin = true;
        $this->admin->save();

        $this->service = Service::factory()->webHosting()->create([
            'service_short' => 'Acme Hosting',
            'cpanel_username' => 'acmeuser',
        ]);

        Setting::set('whm_hostname', Crypt::encryptString('whm.example.test'));
        Setting::set('whm_api_token', Crypt::encryptString('SECRET-TOKEN-123'));
    }

    public function test_show_makes_no_http_call(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->get(route('admin.services.show', $this->service))
            ->assertOk()
            ->assertSee('Acme Hosting')
            ->assertSee('Refresh from WHM');

        Http::assertNothingSent();
    }

    public function test_refresh_flashes_account_info(): void
    {
        Http::fake(['*' => Http::response([
            'metadata' => ['result' => 1],
            'data' => ['acct' => [['user' => 'acmeuser', 'domain' => 'acme.test', 'plan' => 'gold', 'suspended' => 0]]],
        ], 200)]);

        $this->actingAs($this->admin)
            ->post(route('admin.services.refresh-whm', $this->service))
            ->assertRedirect(route('admin.services.show', $this->service))
            ->assertSessionHas('whmInfo.domain', 'acme.test');

        $this->actingAs($this->admin)
            ->withSession(['whmInfo' => ['user' => 'acmeuser', 'domain' => 'acme.test', 'plan' => 'gold', 'suspended' => false]])
            ->get(route('admin.services.show', $this->service))
            ->assertOk()
            ->assertSee('acme.test')
            ->assertSee('gold');
    }

    public function test_refresh_failure_shows_message_and_page_still_renders(): void
    {
        Http::fake(['*' => Http::response('denied', 500)]);

        $this->actingAs($this->admin)
            ->post(route('admin.services.refresh-whm', $this->service))
            ->assertRedirect(route('admin.services.show', $this->service))
            ->assertSessionHas('whmError');

        $this->actingAs($this->admin)
            ->withSession(['whmError' => 'WHM request failed: HTTP 500'])
            ->get(route('admin.services.show', $this->service))
            ->assertOk()
            ->assertSee('WHM request failed: HTTP 500')
            ->assertSee('Acme Hosting');
    }
}
