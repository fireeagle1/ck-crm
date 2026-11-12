<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceDomainFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_factory_states_create_rows(): void
    {
        $default = Service::factory()->create();
        $this->assertNotNull($default->company_id);

        $hosting = Service::factory()->webHosting()->create();
        $this->assertSame('Web Hosting', $hosting->service_type);
        $this->assertNotEmpty($hosting->domain_name);

        $pending = Service::factory()->pending()->create();
        $this->assertSame('Pending', $pending->status);

        $cpanel = Service::factory()->webHosting()->withCpanel()->create();
        $this->assertNotEmpty($cpanel->cpanel_username);

        foreach (['queued', 'running', 'succeeded', 'failed'] as $status) {
            $service = Service::factory()->webHosting()->provisioning($status)->create();
            $this->assertDatabaseHas('services', ['service_id' => $service->service_id]);
        }
    }

    public function test_domain_factory_states_create_rows(): void
    {
        $default = Domain::factory()->create();
        $this->assertNotNull($default->company_id);

        $enom = Domain::factory()->enomSourced('12345')->create();
        $this->assertSame('eNom', $enom->registrar);
        $this->assertStringContainsString('12345', $enom->enom_response);

        $unassigned = Domain::factory()->unassigned()->create();
        $this->assertNull($unassigned->company_id);

        $missing = Domain::factory()->missingFromEnom()->create();
        $this->assertSame('eNom', $missing->registrar);
        $this->assertSame('missing_from_enom', $missing->fresh()->status);
        $this->assertNotNull($missing->fresh()->enom_domain_name_id);
        $this->assertSame('12345', $enom->fresh()->enom_domain_name_id);
        $this->assertSame('active', $enom->fresh()->status);
    }
}
