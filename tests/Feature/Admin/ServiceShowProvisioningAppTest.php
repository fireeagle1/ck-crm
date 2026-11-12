<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerApp;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin service show page: provisioning status and web app link.
 *
 * Validates: Requirements 19.7, 21.1, 21.3
 */
class ServiceShowProvisioningAppTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->is_admin = true;
        $admin->save();

        return $admin;
    }

    public function test_service_without_app_shows_create_link_and_not_tracked(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee('Not tracked')
            ->assertSee('Create web app')
            ->assertSee(route('admin.customer-apps.create', $service), false);
    }

    public function test_service_with_app_links_to_existing_app(): void
    {
        $service = Service::factory()->create();
        $app = CustomerApp::factory()->forService($service)->create(['display_name' => 'Acme Portal']);

        $this->actingAs($this->admin())
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee('Acme Portal')
            ->assertSee(route('admin.customer-apps.show', $app), false)
            ->assertDontSee('Create web app');
    }

    public function test_failed_provisioning_shows_status_and_error(): void
    {
        $service = Service::factory()->create([
            'provisioning_status' => 'failed',
            'provisioning_error' => 'WHM said no: quota exceeded',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee('Failed')
            ->assertSee('WHM said no: quota exceeded')
            ->assertDontSee('Not tracked');
    }

    public function test_succeeded_provisioning_hides_error(): void
    {
        $service = Service::factory()->create([
            'provisioning_status' => 'succeeded',
            'provisioning_error' => 'stale error',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.services.show', $service))
            ->assertOk()
            ->assertSee('Succeeded')
            ->assertDontSee('stale error');
    }
}
