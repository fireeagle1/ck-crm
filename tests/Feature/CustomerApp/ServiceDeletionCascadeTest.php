<?php

namespace Tests\Feature\CustomerApp;

use App\Models\AppPackage;
use App\Models\CustomerApp;
use App\Models\Customer;
use App\Models\SecurityFinding;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature: customer-app-services, Property 2: Service deletion removes the whole technical subtree.
 *
 * For any CustomerApp with N AppPackage records and M SecurityFinding records, deleting the
 * linked Service leaves zero customer_apps, app_packages, and security_findings rows associated
 * with that app (no orphaned technical rows remain), for all N >= 0 and M >= 0.
 *
 * The cascade is DB-level (FK onDelete cascade). sqlite in-memory enforces foreign keys because
 * Laravel issues PRAGMA foreign_keys = ON for sqlite connections, so this test exercises the real
 * cascade chain services -> customer_apps -> app_packages / security_findings.
 *
 * Validates: Requirements 1.6, 2.4
 *
 * innmind/black-box is not a dependency of this project, so rather than adding a new dependency
 * the universal property is exercised with a loop that generates >= 100 random (N, M) pairs,
 * including the N = 0 and M = 0 boundaries.
 */
class ServiceDeletionCascadeTest extends TestCase
{
    use RefreshDatabase;

    private const GENERATED_CASES = 120;

    public function test_foreign_key_enforcement_is_active_on_the_test_connection(): void
    {
        // The cascade only holds if the connection enforces foreign keys. Guard the property with
        // an explicit assertion so a silent "FK disabled" environment fails loudly rather than
        // passing vacuously.
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_deleting_service_removes_the_whole_technical_subtree(): void
    {
        for ($case = 0; $case < self::GENERATED_CASES; $case++) {
            // Ensure the boundaries N = 0 and M = 0 are always covered, then random values.
            [$packageCount, $findingCount] = $this->generateCounts($case);

            $service = $this->makeService();
            $app = $this->makeCustomerApp($service);
            $this->seedPackages($app, $packageCount);
            $this->seedFindings($app, $findingCount);

            // Pre-condition: the rows exist exactly as generated.
            $this->assertDatabaseCount('customer_apps', 1);
            $this->assertSame(
                $packageCount,
                AppPackage::where('customer_app_id', $app->id)->count(),
                "seeded package count mismatch for case {$case} (N={$packageCount})"
            );
            $this->assertSame(
                $findingCount,
                SecurityFinding::where('customer_app_id', $app->id)->count(),
                "seeded finding count mismatch for case {$case} (M={$findingCount})"
            );

            // Act: delete the linked Service.
            $service->delete();

            // Property: no technical rows for this app survive.
            $this->assertDatabaseCount('customer_apps', 0);
            $this->assertSame(
                0,
                AppPackage::where('customer_app_id', $app->id)->count(),
                "orphaned app_packages after deletion for case {$case} (N={$packageCount}, M={$findingCount})"
            );
            $this->assertSame(
                0,
                SecurityFinding::where('customer_app_id', $app->id)->count(),
                "orphaned security_findings after deletion for case {$case} (N={$packageCount}, M={$findingCount})"
            );
            $this->assertDatabaseCount('app_packages', 0);
            $this->assertDatabaseCount('security_findings', 0);

            // Clean slate for the next generated case.
            $service->customer()->delete();
        }
    }

    /**
     * @return array{0:int,1:int} [N packages, M findings]
     */
    private function generateCounts(int $case): array
    {
        return match ($case) {
            0 => [0, 0],   // both boundaries
            1 => [0, 5],   // N boundary
            2 => [5, 0],   // M boundary
            default => [random_int(0, 8), random_int(0, 8)],
        };
    }

    private function makeService(): Service
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

        return Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Managed App',
            'status' => 'Active',
        ]);
    }

    private function makeCustomerApp(Service $service): CustomerApp
    {
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
                'name' => 'package/'.$i,
                'version' => '1.'.$i.'.0',
                'synced_at' => now(),
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
                'description' => 'Finding '.$i,
                'status' => $statuses[$i % count($statuses)],
                'synced_at' => now(),
            ]);
        }
    }
}
