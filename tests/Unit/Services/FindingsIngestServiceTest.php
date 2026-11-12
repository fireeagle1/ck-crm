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
use Illuminate\Support\Facades\DB;
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
        $customer = Customer::create([
            'company_name' => 'Acme Ltd',
            'customer_name' => 'Acme Owner',
        ]);

        $service = Service::create([
            'company_id' => $customer->company_id,
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

    public function test_numeric_primary_key_is_not_accepted(): void
    {
        $app = $this->makeApp();

        $this->expectException(UnknownCustomerAppException::class);

        try {
            $this->service->ingest((string) $app->id, $this->validPackages(), $this->validFindings());
        } finally {
            $this->assertSame(0, AppPackage::count());
            $this->assertSame(0, SecurityFinding::count());
        }
    }

    public function test_public_id_lookup_is_case_insensitive(): void
    {
        $app = $this->makeApp();

        $counts = $this->service->ingest(strtoupper($app->public_id), $this->validPackages(), $this->validFindings());

        $this->assertSame(['packages' => 2, 'findings' => 2], $counts);
    }

    public function test_more_than_1000_findings_rejected_without_persisting(): void
    {
        $app = $this->makeApp();
        $findings = array_fill(0, 1001, ['severity' => 'low', 'description' => 'x', 'status' => SecurityFinding::STATUS_OPEN]);

        try {
            $this->service->ingest($app->public_id, $this->validPackages(), $findings);
            $this->fail('Expected InvalidFindingsPayloadException was not thrown.');
        } catch (InvalidFindingsPayloadException $e) {
            $this->assertStringContainsString('1000', implode(' ', $e->errors()));
        }

        $this->assertSame(0, AppPackage::count());
        $this->assertSame(0, SecurityFinding::count());
    }

    public function test_exactly_1000_findings_accepted(): void
    {
        $app = $this->makeApp();
        $findings = [];
        for ($i = 0; $i < 1000; $i++) {
            $findings[] = ['severity' => 'low', 'description' => "x{$i}", 'status' => SecurityFinding::STATUS_OPEN];
        }

        $counts = $this->service->ingest($app->public_id, [], $findings);

        $this->assertSame(1000, $counts['findings']);
    }

    public function test_payload_over_1mb_rejected_without_persisting(): void
    {
        $app = $this->makeApp();
        $findings = [['severity' => 'low', 'description' => str_repeat('a', 1048577), 'status' => SecurityFinding::STATUS_OPEN]];

        try {
            $this->service->ingest($app->public_id, $this->validPackages(), $findings);
            $this->fail('Expected InvalidFindingsPayloadException was not thrown.');
        } catch (InvalidFindingsPayloadException $e) {
            $this->assertStringContainsString('bytes', implode(' ', $e->errors()));
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
            $this->service->ingest($app->public_id, $badPackages, $this->validFindings());
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

    public function test_successful_ingest_replaces_packages_and_upserts_findings(): void
    {
        $app = $this->makeApp();

        // Stale package is replaced; stale finding is kept as not_reported (never deleted).
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
            $app->public_id,
            $this->validPackages(),
            $this->validFindings(),
        );

        $this->assertSame(['packages' => 2, 'findings' => 2], $counts);

        $packages = AppPackage::where('customer_app_id', $app->id)->get();
        $findings = SecurityFinding::where('customer_app_id', $app->id)->get();

        $this->assertCount(2, $packages);
        $this->assertCount(3, $findings);
        $this->assertEqualsCanonicalizing(
            ['laravel/framework', 'guzzlehttp/guzzle'],
            $packages->pluck('name')->all(),
        );
        $this->assertNotContains('stale/package', $packages->pluck('name')->all());
        $this->assertSame(
            SecurityFinding::STATUS_NOT_REPORTED,
            $findings->firstWhere('description', 'Stale finding')->status,
        );

        // synced_at stamped on every new row.
        foreach ($packages as $package) {
            $this->assertNotNull($package->synced_at);
        }
        foreach ($findings as $finding) {
            $this->assertNotNull($finding->synced_at);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Fingerprint upsert rules (Req 17.2, 17.3, 17.4, 17.7)
    // ──────────────────────────────────────────────────────────────────

    public function test_fingerprint_for_normalises_whitespace_and_case(): void
    {
        $this->assertSame(hash('sha256', 'outdated php version'), SecurityFinding::fingerprintFor("  Outdated \n PHP\tVersion "));
    }

    public function test_matched_finding_updates_fields_and_keeps_row(): void
    {
        $app = $this->makeApp();
        $old = now()->subDay();
        $row = SecurityFinding::create([
            'customer_app_id' => $app->id, 'severity' => 'low', 'description' => 'SQL Injection', 'status' => 'open', 'synced_at' => $old,
        ]);

        $this->service->ingest($app->public_id, [], [
            ['severity' => 'critical', 'description' => ' sql   injection ', 'status' => 'open'],
        ]);

        $this->assertSame(1, SecurityFinding::count());
        $row->refresh();
        $this->assertSame('critical', $row->severity);
        $this->assertSame(' sql   injection ', $row->description);
        $this->assertNotNull($row->last_seen_at);
        $this->assertTrue($row->synced_at->gt($old));
    }

    public function test_resolved_and_dismissed_states_are_kept(): void
    {
        $app = $this->makeApp();
        foreach (['resolved', 'dismissed'] as $status) {
            SecurityFinding::create(['customer_app_id' => $app->id, 'severity' => 'low', 'description' => "F {$status}", 'status' => $status, 'synced_at' => now()]);
        }

        $this->service->ingest($app->public_id, [], [
            ['severity' => 'high', 'description' => 'F resolved', 'status' => 'open'],
            ['severity' => 'high', 'description' => 'F dismissed', 'status' => 'open'],
        ]);
        // Absent from payload: resolved/dismissed stay too.
        $this->service->ingest($app->public_id, [], []);

        $this->assertSame('resolved', SecurityFinding::where('description', 'F resolved')->value('status'));
        $this->assertSame('dismissed', SecurityFinding::where('description', 'F dismissed')->value('status'));
        $this->assertSame('high', SecurityFinding::where('description', 'F resolved')->value('severity'));
    }

    public function test_absent_open_becomes_not_reported_and_reopens_when_it_reappears(): void
    {
        $app = $this->makeApp();
        $payload = [['severity' => 'low', 'description' => 'Flaky', 'status' => 'open']];

        $this->service->ingest($app->public_id, [], $payload);
        $this->service->ingest($app->public_id, [], []);
        $this->assertSame('not_reported', SecurityFinding::first()->status);

        $this->service->ingest($app->public_id, [], $payload);
        $this->assertSame(1, SecurityFinding::count());
        $this->assertSame('open', SecurityFinding::first()->status);
    }

    public function test_duplicates_within_payload_collapse_last_wins(): void
    {
        $app = $this->makeApp();

        $counts = $this->service->ingest($app->public_id, [], [
            ['severity' => 'low', 'description' => 'Dup', 'status' => 'open'],
            ['severity' => 'high', 'description' => '  dup ', 'status' => 'dismissed'],
        ]);

        $this->assertSame(1, $counts['findings']);
        $this->assertSame(1, SecurityFinding::count());
        $this->assertSame('high', SecurityFinding::first()->severity);
        $this->assertSame('dismissed', SecurityFinding::first()->status);
    }

    public function test_reingesting_same_payload_is_idempotent(): void
    {
        $app = $this->makeApp();
        $this->service->ingest($app->public_id, $this->validPackages(), $this->validFindings());
        $ids = SecurityFinding::orderBy('id')->pluck('id')->all();
        $state = SecurityFinding::orderBy('id')->get(['severity', 'description', 'status'])->toArray();

        $this->service->ingest($app->public_id, $this->validPackages(), $this->validFindings());

        $this->assertSame($ids, SecurityFinding::orderBy('id')->pluck('id')->all());
        $this->assertSame($state, SecurityFinding::orderBy('id')->get(['severity', 'description', 'status'])->toArray());
        $this->assertSame(2, AppPackage::count());
    }

    public function test_mid_ingest_failure_leaves_stored_data_unchanged(): void
    {
        $app = $this->makeApp();
        AppPackage::create(['customer_app_id' => $app->id, 'name' => 'prior/pkg', 'version' => '1.0.0', 'synced_at' => now()]);
        SecurityFinding::create(['customer_app_id' => $app->id, 'severity' => 'low', 'description' => 'Existing', 'status' => 'open', 'synced_at' => now()]);
        SecurityFinding::create(['customer_app_id' => $app->id, 'severity' => 'low', 'description' => 'Other', 'status' => 'open', 'synced_at' => now()]);
        $before = SecurityFinding::orderBy('id')->get()->toArray();

        // Fail on the first findings insert, after packages were replaced and a row updated.
        DB::listen(function ($query): void {
            if (preg_match('/insert into [`"]security_findings[`"]/', $query->sql)) {
                throw new \RuntimeException('injected failure');
            }
        });

        try {
            $this->service->ingest($app->public_id, $this->validPackages(), [
                ['severity' => 'high', 'description' => 'Existing', 'status' => 'resolved'],
                ['severity' => 'high', 'description' => 'Brand new', 'status' => 'open'],
            ]);
            $this->fail('Expected injected failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('injected failure', $e->getMessage());
        }

        $this->assertSame($before, SecurityFinding::orderBy('id')->get()->toArray());
        $this->assertSame(['prior/pkg'], AppPackage::pluck('name')->all());
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
            $this->service->ingest($app->public_id, $this->validPackages(), $badFindings);
            $this->fail('Expected InvalidFindingsPayloadException was not thrown.');
        } catch (InvalidFindingsPayloadException $e) {
            $this->assertStringContainsString('status', implode(' ', $e->errors()));
        }

        // Nothing persisted — not even the valid packages in the same payload.
        $this->assertSame(0, AppPackage::count());
        $this->assertSame(0, SecurityFinding::count());
    }
}
