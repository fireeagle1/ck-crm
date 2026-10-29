<?php

namespace Tests\Feature\CustomerApp;

use App\Models\AppPackage;
use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use App\Services\FindingsIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: customer-app-services, Property 3: Findings ingestion is a full, idempotent bulk replacement.
 *
 * For any existing CustomerApp with any prior set of packages and findings, and any valid submitted
 * payload, after ingestion the app's stored packages and findings equal exactly the submitted set
 * (all prior records gone, all submitted records present with synced_at stamped); and applying the
 * same valid payload a second time leaves the stored state unchanged.
 *
 * Validates: Requirements 2.3, 2.5, 3.1, 3.2
 *
 * innmind/black-box is not a dependency of this project, so rather than adding a new dependency the
 * universal property is exercised with a loop that generates >= 100 random cases. Each case seeds a
 * random prior set of packages/findings, builds a random VALID payload (finding statuses drawn from
 * SecurityFinding::STATUSES), ingests it, asserts the stored set equals the payload with synced_at
 * stamped, then re-applies the same payload and asserts the stored state is unchanged (idempotent).
 */
class FindingsBulkReplaceIdempotenceTest extends TestCase
{
    use RefreshDatabase;

    private const GENERATED_CASES = 120;

    private FindingsIngestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FindingsIngestService();
    }

    public function test_ingestion_is_a_full_idempotent_bulk_replacement(): void
    {
        for ($case = 0; $case < self::GENERATED_CASES; $case++) {
            $app = $this->makeCustomerApp();

            // Seed a random PRIOR set of packages and findings that the ingest must fully replace.
            [$priorPackages, $priorFindings] = $this->generateCounts($case, 'prior');
            $this->seedPackages($app, $priorPackages);
            $this->seedFindings($app, $priorFindings);

            // Build a random VALID payload (the submitted set).
            [$payloadPackageCount, $payloadFindingCount] = $this->generateCounts($case, 'payload');
            $packages = $this->makePackagePayload($payloadPackageCount, $case);
            $findings = $this->makeFindingPayload($payloadFindingCount, $case);

            // Act: first ingestion.
            $counts = $this->service->ingest((string) $app->id, $packages, $findings);

            // Returned counts reflect exactly the submitted set.
            $this->assertSame($payloadPackageCount, $counts['packages'], "package count mismatch, case {$case}");
            $this->assertSame($payloadFindingCount, $counts['findings'], "finding count mismatch, case {$case}");

            // Property: the stored set equals the submitted set exactly, with synced_at stamped and
            // every prior record gone.
            $this->assertStoredEqualsPayload($app, $packages, $findings, $case, 'after first ingest');

            $firstState = $this->snapshot($app);

            // Act: re-apply the SAME payload.
            $this->service->ingest((string) $app->id, $packages, $findings);

            // Property: the stored *content* is unchanged — same packages and findings as before.
            $this->assertStoredEqualsPayload($app, $packages, $findings, $case, 'after second ingest');

            $secondState = $this->snapshot($app);

            $this->assertSame(
                $firstState,
                $secondState,
                "re-applying the same payload changed the stored content, case {$case}"
            );

            // Clean slate for the next generated case (cascade removes app + children).
            $app->service->customer()->delete();
        }
    }

    /**
     * Assert the app's stored packages and findings equal exactly the submitted payload,
     * every row carries a synced_at stamp, and no prior record survives.
     *
     * @param array<int, array{name:string, version:string}> $packages
     * @param array<int, array{severity:string, description:string, status:string}> $findings
     */
    private function assertStoredEqualsPayload(
        CustomerApp $app,
        array $packages,
        array $findings,
        int $case,
        string $phase
    ): void {
        $storedPackages = AppPackage::where('customer_app_id', $app->id)->get();
        $storedFindings = SecurityFinding::where('customer_app_id', $app->id)->get();

        // Count matches the submitted set exactly (all prior records gone).
        $this->assertCount(count($packages), $storedPackages, "package set size mismatch {$phase}, case {$case}");
        $this->assertCount(count($findings), $storedFindings, "finding set size mismatch {$phase}, case {$case}");

        // Every stored row has synced_at stamped (Req 2.3).
        foreach ($storedPackages as $package) {
            $this->assertNotNull($package->synced_at, "package synced_at not stamped {$phase}, case {$case}");
        }
        foreach ($storedFindings as $finding) {
            $this->assertNotNull($finding->synced_at, "finding synced_at not stamped {$phase}, case {$case}");
        }

        // The stored content equals the submitted content (order-independent multiset comparison).
        $expectedPackages = $this->normalisePackages($packages);
        $actualPackages = $storedPackages
            ->map(fn (AppPackage $p): string => $p->name.'@'.$p->version)
            ->sort()
            ->values()
            ->all();
        $this->assertSame($expectedPackages, $actualPackages, "package content mismatch {$phase}, case {$case}");

        $expectedFindings = $this->normaliseFindings($findings);
        $actualFindings = $storedFindings
            ->map(fn (SecurityFinding $f): string => $f->severity.'|'.$f->description.'|'.$f->status)
            ->sort()
            ->values()
            ->all();
        $this->assertSame($expectedFindings, $actualFindings, "finding content mismatch {$phase}, case {$case}");
    }

    /**
     * A content-only snapshot of the stored child rows (sorted, id-independent) so two ingests
     * can be compared for idempotence of content.
     *
     * @return array{packages:array<int,string>, findings:array<int,string>}
     */
    private function snapshot(CustomerApp $app): array
    {
        $packages = AppPackage::where('customer_app_id', $app->id)
            ->get()
            ->map(fn (AppPackage $p): string => $p->name.'@'.$p->version)
            ->sort()
            ->values()
            ->all();

        $findings = SecurityFinding::where('customer_app_id', $app->id)
            ->get()
            ->map(fn (SecurityFinding $f): string => $f->severity.'|'.$f->description.'|'.$f->status)
            ->sort()
            ->values()
            ->all();

        return ['packages' => $packages, 'findings' => $findings];
    }

    /**
     * @param array<int, array{name:string, version:string}> $packages
     * @return array<int, string>
     */
    private function normalisePackages(array $packages): array
    {
        return collect($packages)
            ->map(fn (array $p): string => $p['name'].'@'.$p['version'])
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param array<int, array{severity:string, description:string, status:string}> $findings
     * @return array<int, string>
     */
    private function normaliseFindings(array $findings): array
    {
        return collect($findings)
            ->map(fn (array $f): string => $f['severity'].'|'.$f['description'].'|'.$f['status'])
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Random non-negative counts, pinning the zero/boundary cases so empty payloads and empty
     * prior sets are always covered.
     *
     * @return array{0:int,1:int}
     */
    private function generateCounts(int $case, string $which): array
    {
        // Pin a few structural boundaries, then random.
        return match (true) {
            $case === 0 => [0, 0],                              // empty prior + empty payload
            $case === 1 && $which === 'payload' => [0, 0],      // valid empty payload clears all
            $case === 2 && $which === 'prior' => [0, 0],        // empty prior, non-empty payload
            default => [random_int(0, 6), random_int(0, 6)],
        };
    }

    /**
     * @return array<int, array{name:string, version:string}>
     */
    private function makePackagePayload(int $count, int $case): array
    {
        $packages = [];

        for ($i = 0; $i < $count; $i++) {
            $packages[] = [
                'name' => "vendor/pkg-{$case}-{$i}",
                'version' => random_int(0, 9).'.'.random_int(0, 20).'.'.random_int(0, 50),
            ];
        }

        return $packages;
    }

    /**
     * @return array<int, array{severity:string, description:string, status:string}>
     */
    private function makeFindingPayload(int $count, int $case): array
    {
        $severities = ['low', 'medium', 'high', 'critical'];
        $findings = [];

        for ($i = 0; $i < $count; $i++) {
            $findings[] = [
                'severity' => $severities[random_int(0, count($severities) - 1)],
                'description' => "Finding {$case}-{$i} details",
                // Only valid statuses drawn from the defined set (Req 3.4 valid path).
                'status' => SecurityFinding::STATUSES[random_int(0, count(SecurityFinding::STATUSES) - 1)],
            ];
        }

        return $findings;
    }

    private function makeCustomerApp(): CustomerApp
    {
        $customer = Customer::create([
            'company_name' => 'Acme '.uniqid('', true),
            'customer_name' => 'Owner',
            'phone_number' => '0000000000',
            'address_line1' => '1 Test Street',
            'city' => 'Testville',
            'postal_code' => 'TE5 7ST',
            'country' => 'GB',
        ]);

        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Managed App',
            'status' => 'Active',
        ]);

        return CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://git.example.test/app.git',
            'hosting_location' => 'cpanel://host/app',
            'codebase_info' => 'PHP 8.3 / Laravel 13',
        ]);
    }

    private function seedPackages(CustomerApp $app, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            AppPackage::create([
                'customer_app_id' => $app->id,
                'name' => 'prior/pkg-'.$i,
                'version' => '0.0.'.$i,
                'synced_at' => now()->subDay(),
            ]);
        }
    }

    private function seedFindings(CustomerApp $app, int $count): void
    {
        $statuses = SecurityFinding::STATUSES;

        for ($i = 0; $i < $count; $i++) {
            SecurityFinding::create([
                'customer_app_id' => $app->id,
                'severity' => ['low', 'medium', 'high', 'critical'][$i % 4],
                'description' => 'Prior finding '.$i,
                'status' => $statuses[$i % count($statuses)],
                'synced_at' => now()->subDay(),
            ]);
        }
    }
}
