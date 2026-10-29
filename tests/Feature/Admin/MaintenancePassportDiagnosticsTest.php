<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the no-SSH maintenance diagnostics added to debug the production
 * "/oauth/authorize returns 404" symptom.
 *
 * The admin Maintenance screen must surface whether Passport is actually LOADED
 * and whether its /oauth/* routes are REGISTERED in the running process — the
 * blind spot that let the tables/keys/client checklist read all-green while the
 * authorize endpoint 404'd. It also exposes a `package_discover` action that
 * rebuilds the stale package manifest which causes that 404.
 */
class MaintenancePassportDiagnosticsTest extends TestCase
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

    public function test_maintenance_page_reports_oauth_route_registration(): void
    {
        // Passport is loaded in the test process, so the diagnostic must report
        // the healthy state: routes registered, provider booted, vendor present.
        $response = $this->actingAs($this->admin)->get('/admin/settings/maintenance');

        $response->assertOk();
        $response->assertSee('OAuth route registration');
        $response->assertSee('/oauth/authorize and /oauth/token routes registered');
        // Healthy state shows the "OK" marker, not the PROBLEM banner.
        $response->assertSee('OAuth route registration — OK');
    }

    public function test_package_discover_action_is_accepted_and_reports_passport(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/settings/maintenance/run', ['action' => 'package_discover']);

        $response->assertRedirect();
        // vendor/laravel/passport exists in this repo, so it reports success and
        // confirms discovery ran.
        $response->assertSessionHas('success');
    }

    public function test_run_action_rejects_values_outside_the_whitelist(): void
    {
        $response = $this->actingAs($this->admin)
            ->from('/admin/settings/maintenance')
            ->post('/admin/settings/maintenance/run', ['action' => 'rm_-rf_everything']);

        $response->assertSessionHasErrors('action');
    }

    public function test_maintenance_screen_is_admin_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/admin/settings/maintenance')
            ->assertForbidden();
    }
}
