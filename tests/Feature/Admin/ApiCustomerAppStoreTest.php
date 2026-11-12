<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerApp;
use App\Models\Domain;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiCustomerAppStoreTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);
    }

    public function test_store_creates_app_with_optional_domain(): void
    {
        $this->actAsAdmin();
        $service = Service::factory()->create();
        $domain = Domain::factory()->create(['domain_name' => 'example.com']);

        $this->postJson('/api/admin/customer-apps', [
            'service_id' => $service->service_id,
            'domain_id' => $domain->id,
        ])->assertCreated();

        $app = CustomerApp::where('service_id', $service->service_id)->firstOrFail();
        $this->assertSame($domain->id, $app->domain_id);
    }

    public function test_duplicate_store_returns_422_validation_shape_on_service_id(): void
    {
        $this->actAsAdmin();
        $service = Service::factory()->create();
        CustomerApp::create(['service_id' => $service->service_id]);

        $this->postJson('/api/admin/customer-apps', ['service_id' => $service->service_id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['service_id'])
            ->assertJsonPath('errors.service_id.0', 'This service already has a linked app.');

        $this->assertSame(1, CustomerApp::where('service_id', $service->service_id)->count());
    }
}
