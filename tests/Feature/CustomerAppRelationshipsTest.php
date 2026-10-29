<?php

namespace Tests\Feature;

use App\Models\AppPackage;
use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Relationship example tests for the Customer App models (task 2.4).
 *
 * Asserts the Service <-> CustomerApp one-to-one and the CustomerApp child
 * relationships resolve, that an app with no packages/findings is valid, and
 * that openFindings is filtered to the "open" status.
 *
 * **Validates: Requirements 1.2, 1.3, 2.4**
 */
class CustomerAppRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): Service
    {
        $customer = Customer::factory()->create();

        return Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Managed App',
            'status' => 'active',
        ]);
    }

    private function makeApp(?Service $service = null): CustomerApp
    {
        $service ??= $this->makeService();

        return CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://example.com/repo.git',
            'hosting_location' => 'cpanel-01',
            'codebase_info' => 'Laravel 13 / PHP 8.3',
        ]);
    }

    public function test_service_resolves_its_customer_app_one_to_one(): void
    {
        $service = $this->makeService();
        $app = $this->makeApp($service);

        // Relationship type (Req 1.3: one-to-one).
        $this->assertInstanceOf(HasOne::class, $service->customerApp());

        $resolved = $service->fresh()->customerApp;

        $this->assertInstanceOf(CustomerApp::class, $resolved);
        $this->assertSame($app->id, $resolved->id);
        $this->assertSame($service->service_id, $resolved->service_id);
    }

    public function test_customer_app_resolves_its_service(): void
    {
        $service = $this->makeService();
        $app = $this->makeApp($service);

        // Relationship type (Req 1.2: CustomerApp references exactly one Service).
        $this->assertInstanceOf(BelongsTo::class, $app->service());

        $resolved = $app->fresh()->service;

        $this->assertInstanceOf(Service::class, $resolved);
        $this->assertSame($service->service_id, $resolved->service_id);
    }

    public function test_customer_app_resolves_packages_and_findings(): void
    {
        $app = $this->makeApp();

        $this->assertInstanceOf(HasMany::class, $app->packages());
        $this->assertInstanceOf(HasMany::class, $app->findings());

        AppPackage::create([
            'customer_app_id' => $app->id,
            'name' => 'laravel/framework',
            'version' => '13.0.0',
        ]);

        SecurityFinding::create([
            'customer_app_id' => $app->id,
            'severity' => 'high',
            'description' => 'Example finding',
            'status' => SecurityFinding::STATUS_OPEN,
        ]);

        $app->refresh();

        $this->assertInstanceOf(Collection::class, $app->packages);
        $this->assertCount(1, $app->packages);
        $this->assertSame('laravel/framework', $app->packages->first()->name);

        $this->assertInstanceOf(Collection::class, $app->findings);
        $this->assertCount(1, $app->findings);
        $this->assertSame('high', $app->findings->first()->severity);
    }

    public function test_app_with_zero_packages_and_findings_is_valid(): void
    {
        // Req 2.4: a CustomerApp may have zero or more packages and findings.
        $app = $this->makeApp();

        $this->assertDatabaseHas('customer_apps', ['id' => $app->id]);

        $app->refresh();

        $this->assertCount(0, $app->packages);
        $this->assertCount(0, $app->findings);
        $this->assertCount(0, $app->openFindings);
        $this->assertSame(0, $app->openFindings()->count());
    }

    public function test_open_findings_returns_only_open_status(): void
    {
        $app = $this->makeApp();

        $this->assertInstanceOf(HasMany::class, $app->openFindings());

        foreach ([
            SecurityFinding::STATUS_OPEN,
            SecurityFinding::STATUS_OPEN,
            SecurityFinding::STATUS_RESOLVED,
            SecurityFinding::STATUS_DISMISSED,
        ] as $status) {
            SecurityFinding::create([
                'customer_app_id' => $app->id,
                'severity' => 'medium',
                'description' => 'Finding with status '.$status,
                'status' => $status,
            ]);
        }

        $app->refresh();

        // Four findings total, but only the two "open" ones surface via openFindings.
        $this->assertCount(4, $app->findings);
        $this->assertCount(2, $app->openFindings);
        $this->assertTrue(
            $app->openFindings->every(
                fn (SecurityFinding $finding) => $finding->status === SecurityFinding::STATUS_OPEN
            )
        );
    }
}
