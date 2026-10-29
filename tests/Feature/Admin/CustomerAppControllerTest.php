<?php

namespace Tests\Feature\Admin;

use App\Models\AppPackage;
use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the admin web CustomerApp controller
 * (App\Http\Controllers\Admin\CustomerAppController) and its admin.customer-apps.* routes.
 *
 * The admin route group applies auth, verified, and EnsureIsAdmin middleware, so the acting
 * user is created with is_admin = true and a verified email.
 *
 * Validates: Requirements 4.1, 4.2, 4.3, 4.4
 */
class CustomerAppControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // EnsureIsAdmin checks $user->isAdmin() (the is_admin boolean); is_admin is guarded,
        // so it is set explicitly after creation. email_verified_at satisfies the `verified`
        // middleware on the admin route group.
        $this->admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->admin->is_admin = true;
        $this->admin->save();
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 4.1 / 4.3: admin show renders the full technical view
    // plus the linked service name, status, and billing.
    // ──────────────────────────────────────────────────────────────────

    public function test_show_renders_technical_metadata_packages_findings_and_service_summary(): void
    {
        $service = $this->makeService([
            'service_short' => 'Bespoke Booking App',
            'status' => 'Active',
            'service_monthly_charge' => 49.99,
            'service_payment_frequency' => 'Monthly',
        ]);

        $app = CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://github.com/ck/bespoke-booking',
            'hosting_location' => 'AWS eu-west-2 cluster node 7',
            'codebase_info' => 'Laravel 13, PHP 8.3, Vue frontend',
        ]);

        AppPackage::create([
            'customer_app_id' => $app->id,
            'name' => 'laravel/framework',
            'version' => '13.0.1',
            'synced_at' => now(),
        ]);
        AppPackage::create([
            'customer_app_id' => $app->id,
            'name' => 'guzzlehttp/guzzle',
            'version' => '7.8.0',
            'synced_at' => now(),
        ]);

        SecurityFinding::create([
            'customer_app_id' => $app->id,
            'severity' => 'high',
            'description' => 'Outdated TLS configuration on edge proxy',
            'status' => SecurityFinding::STATUS_OPEN,
            'synced_at' => now(),
        ]);
        SecurityFinding::create([
            'customer_app_id' => $app->id,
            'severity' => 'low',
            'description' => 'Verbose error pages enabled in staging',
            'status' => SecurityFinding::STATUS_RESOLVED,
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customer-apps.show', $app));

        $response->assertOk();

        // Technical metadata (Req 4.1)
        $response->assertSee('https://github.com/ck/bespoke-booking');
        $response->assertSee('AWS eu-west-2 cluster node 7');
        $response->assertSee('Laravel 13, PHP 8.3, Vue frontend');

        // All package names (Req 4.1)
        $response->assertSee('laravel/framework');
        $response->assertSee('guzzlehttp/guzzle');

        // All finding descriptions (Req 4.1)
        $response->assertSee('Outdated TLS configuration on edge proxy');
        $response->assertSee('Verbose error pages enabled in staging');

        // Linked service name, status, billing (Req 4.3)
        $response->assertSee('Bespoke Booking App');
        $response->assertSee('Active');
        $response->assertSee('£49.99/monthly');
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 4.2: create a CustomerApp against an existing Service.
    // ──────────────────────────────────────────────────────────────────

    public function test_store_creates_customer_app_with_submitted_fields_and_redirects_with_success(): void
    {
        $service = $this->makeService();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.customer-apps.store', $service), [
                'repo_url' => 'https://github.com/ck/new-app',
                'hosting_location' => 'cPanel server crm-01',
                'codebase_info' => 'Laravel monolith',
            ]);

        $app = CustomerApp::where('service_id', $service->service_id)->first();

        $this->assertNotNull($app, 'A CustomerApp should have been created for the service.');

        $response->assertRedirect(route('admin.customer-apps.show', $app));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_apps', [
            'service_id' => $service->service_id,
            'repo_url' => 'https://github.com/ck/new-app',
            'hosting_location' => 'cPanel server crm-01',
            'codebase_info' => 'Laravel monolith',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 4.2: update edits only the three technical fields.
    // ──────────────────────────────────────────────────────────────────

    public function test_update_persists_only_the_three_editable_fields(): void
    {
        $service = $this->makeService([
            'service_short' => 'Original Service Name',
            'status' => 'Active',
        ]);

        $app = CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://github.com/ck/old',
            'hosting_location' => 'old-host',
            'codebase_info' => 'old info',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.customer-apps.update', $app), [
                'repo_url' => 'https://github.com/ck/updated',
                'hosting_location' => 'new-host-location',
                'codebase_info' => 'updated codebase info',
            ]);

        $response->assertRedirect(route('admin.customer-apps.show', $app));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_apps', [
            'id' => $app->id,
            'service_id' => $service->service_id,
            'repo_url' => 'https://github.com/ck/updated',
            'hosting_location' => 'new-host-location',
            'codebase_info' => 'updated codebase info',
        ]);

        // The linked service identity is untouched by the app update.
        $this->assertDatabaseHas('services', [
            'service_id' => $service->service_id,
            'service_short' => 'Original Service Name',
            'status' => 'Active',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 1.4 / 4.4: duplicate create is rejected and does not
    // create a second row.
    // ──────────────────────────────────────────────────────────────────

    public function test_store_rejects_duplicate_app_for_a_service(): void
    {
        $service = $this->makeService();

        CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://github.com/ck/first',
            'hosting_location' => 'first-host',
            'codebase_info' => 'first info',
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.customer-apps.create', $service))
            ->post(route('admin.customer-apps.store', $service), [
                'repo_url' => 'https://github.com/ck/second',
                'hosting_location' => 'second-host',
                'codebase_info' => 'second info',
            ]);

        $response->assertRedirect(route('admin.customer-apps.create', $service));
        $response->assertSessionHas('error', 'This service already has a linked app.');

        // Still exactly one app, and it is the original.
        $this->assertSame(1, CustomerApp::where('service_id', $service->service_id)->count());
        $this->assertDatabaseMissing('customer_apps', [
            'service_id' => $service->service_id,
            'repo_url' => 'https://github.com/ck/second',
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeService(array $attributes = []): Service
    {
        $customer = Customer::factory()->create();

        return Service::create(array_merge([
            'company_id' => $customer->company_id,
            'service_short' => 'Managed App',
            'status' => 'Active',
        ], $attributes));
    }
}
