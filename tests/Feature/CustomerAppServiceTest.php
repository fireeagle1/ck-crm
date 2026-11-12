<?php

namespace Tests\Feature;

use App\Models\CustomerApp;
use App\Models\Domain;
use App\Models\Service;
use App\Services\CustomerAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerAppServiceTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): CustomerAppService
    {
        return app(CustomerAppService::class);
    }

    public function test_single_create_persists_fields(): void
    {
        $service = Service::factory()->create();

        $app = $this->svc()->createForService($service, [
            'display_name' => 'My App',
            'install_url' => 'https://given.example.com',
            'repo_url' => 'https://git.example.com/r',
        ]);

        $this->assertSame($service->service_id, $app->service_id);
        $this->assertSame('My App', $app->display_name);
        $this->assertSame('https://given.example.com', $app->install_url);
        $this->assertNotEmpty($app->public_id);
        $this->assertSame(1, CustomerApp::where('service_id', $service->service_id)->count());
    }

    public function test_second_create_is_rejected_on_service_id(): void
    {
        $service = Service::factory()->create();
        $this->svc()->createForService($service, []);

        try {
            $this->svc()->createForService($service, []);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('service_id', $e->errors());
        }

        $this->assertSame(1, CustomerApp::where('service_id', $service->service_id)->count());
    }

    public function test_race_unique_violation_becomes_validation_error(): void
    {
        $service = Service::factory()->create();

        // A competing request wins the insert: the DB raises the unique violation.
        CustomerApp::creating(function () {
            throw new UniqueConstraintViolationException(
                'sqlite',
                'insert into customer_apps',
                [],
                new \Exception('UNIQUE constraint failed: customer_apps.service_id')
            );
        });

        try {
            $this->svc()->createForService($service, []);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('service_id', $e->errors());
        }

        $this->assertSame(0, CustomerApp::where('service_id', $service->service_id)->count());
    }

    public function test_install_url_defaults_from_selected_domain(): void
    {
        $service = Service::factory()->create();
        $domain = Domain::factory()->create(['domain_name' => 'selected.com']);

        $app = $this->svc()->createForService($service, [], $domain);

        $this->assertSame('https://selected.com', $app->install_url);
        $this->assertSame($domain->id, $app->domain_id);
    }

    public function test_install_url_defaults_from_linked_service_domain(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'linked.com']);
        $service = Service::factory()->create(['domain_id' => $domain->id]);

        $app = $this->svc()->createForService($service, ['install_url' => null]);

        $this->assertSame('https://linked.com', $app->install_url);
    }

    public function test_explicit_install_url_wins_over_domain(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'selected.com']);
        $service = Service::factory()->create();

        $app = $this->svc()->createForService($service, ['install_url' => 'https://custom.test'], $domain);

        $this->assertSame('https://custom.test', $app->install_url);
    }

    public function test_no_domain_and_no_url_leaves_install_url_null(): void
    {
        $service = Service::factory()->create(['domain_id' => null]);

        $this->assertNull($this->svc()->createForService($service, [])->install_url);
    }
}
