<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidFindingsPayloadException;
use App\Exceptions\UnknownCustomerAppException;
use App\Models\AppPackage;
use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use App\Services\FindingsIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindingsIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    private FindingsIngestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FindingsIngestService();
    }

    private function makeApp(): CustomerApp
    {
        $service = Service::create([
            'company_id' => 1,
            'service_short' => 'Test App',
            'status' => 'active',
        ]);

        return CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://github.com/example/app',
            'hosting_location' => 'cpanel-01',
            'codebase_info' => 'Laravel 13',
        ]);
    }

    private function validPackages(): array
    {
        return [
            ['name' => 'laravel/framework', 'version' => '12.0.0'],
            ['name' => 'guzzlehttp/guzzle', 'version' => '7.8.0'],
        ];
    }

    private function validFindings(): array
    {
        return [
            ['severity' => 'high', 'description' => 'SQL injection risk', 'status' => SecurityFinding::STATUS_OPEN],
            ['severity' => 'low', 'description' => 'Outdated dependency', 'status' => SecurityFinding::STATUS_RESOLVED],
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // 1. Unknown app id -> UnknownCustomerAppException, nothing persisted
    // ──────────────────────────────────────────────────────────────────

    public function test_unknown_app_identifier_throws_and_persists_nothing(): void
    {
        $this->makeApp(); // exists, but we target a different identifier

        try {
            $this->service->ingest('999999', $this->validPackages(), $this->validFindings());
            $this->fail('Expected UnknownCustomerAppException was not thrown.');
        } catch (UnknownCustomerAppException $e) {
            $this->assertStringContainsString('999999', $e->getMessage());
        }

        $this->assertSame(0, AppPackage::count());
        $this->assertSame(0, SecurityFinding::count());
    }

    // ──────────────────────────────────────────────────────────────────
    // 2. Invalid payload (missing package version) -> exception, no mutation
    // ──────────────────────────────────────────────────────────────────

    public function test_invalid_payload_missing_package_version_rejected_without_mutation(): void
    {
        $app = $this->makeApp();

        // Seed prior rows to prove they are left untouched on rejection.
        AppPackage::create([
            'customer_app_id' => $app->id,
            'name' => 'prior/package',
            'version' => '1.0.0',
            'synced_at' => now(),
        ]);
        SecurityFinding::create([
            'customer_app_id' => $app->id,
            'severity' => 'medium',
            'description' => 'Prior finding',
            'status' => SecurityFinding::STATUS_OPEN,
            'synced_at' => now(),
        ]);

        $badPackages = [
            ['name' => 'laravel/framework'], // missing version
        ];

        try {
            $this->service->ingest((string) $app->id, $badPackages, $this->validFindings());
            $this->fail('Expected InvalidFindingsPayloadException was not thrown.');
        } catch (InvalidFindingsPayloadException $e) {
            $this->assertNotEmpty($e->errors());
            $this->assertStringContainsString('version', implode(' ', $e->errors()));
        }

        // Prior state unchanged: exactly the one seeded row of each kind.
        $this->assertSame(1, AppPackage::count());
        $this->assertSame(1, SecurityFinding::count());
        $this->assertSame('prior/package', AppPackage::first()->name);
        $this->assertSame('Prior finding', SecurityFinding::first()->description);
    }

    // ──────────────────────────────────────────────────────────────────
    // 3. Successful transactional replace
    // ──────────────────────────────────────────────────────────────────

    public function test_successful_ingest_replaces_prior_rows_and_returns_counts(): void
    {
        $app = $this->makeApp();

        // Prior rows that must be gone after a successful replace.
        AppPackage::create([
            'customer_app_id' => $app->id,
            'name' => 'stale/package',
            'version' => '0.1.0',
            'synced_at' => now()->subDay(),
        ]);
        SecurityFinding::create([
            'customer_app_id' => $app->id,
            'severity' => 'critical',
            'description' => 'Stale finding',
            'status' => SecurityFinding::STATUS_OPEN,
            'synced_at' => now()->subDay(),
        ]);

        $counts = $this->service->ingest(
            (string) $app->id,
            $this->validPackages(),
            $this->validFindings(),
        );

        $this->assertSame(['packages' => 2, 'findings' => 2], $counts);

        // Prior rows gone, new rows present.
        $packages = AppPackage::where('customer_app_id', $app->id)->get();
        $findings = SecurityFinding::where('customer_app_id', $app->id)->get();

        $this->assertCount(2, $packages);
        $this->assertCount(2, $findings);
        $this->assertEqualsCanonicalizing(
            ['laravel/framework', 'guzzlehttp/guzzle'],
            $packages->pluck('name')->all(),
        );
        $this->assertNotContains('stale/package', $packages->pluck('name')->all());
        $this->assertNotContains('Stale finding', $findings->pluck('description')->all());

        // synced_at stamped on every new row.
        foreach ($packages as $package) {
            $this->assertNotNull($package->synced_at);
        }
        foreach ($findings as $finding) {
            $this->assertNotNull($finding->synced_at);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // 4. Finding status outside SecurityFinding::STATUSES -> rejected
    // ──────────────────────────────────────────────────────────────────

    public function test_finding_status_outside_defined_set_rejected_without_mutation(): void
    {
        $app = $this->makeApp();

        $badFindings = [
            ['severity' => 'high', 'description' => 'Bad status value', 'status' => 'escalated'],
        ];

        try {
            $this->service->ingest((string) $app->id, $this->validPackages(), $badFindings);
            $this->fail('Expected InvalidFindingsPayloadException was not thrown.');
        } catch (InvalidFindingsPayloadException $e) {
            $this->assertStringContainsString('status', implode(' ', $e->errors()));
        }

        // Nothing persisted — not even the valid packages in the same payload.
        $this->assertSame(0, AppPackage::count());
        $this->assertSame(0, SecurityFinding::count());
    }
}
