<?php

namespace Tests\Feature\CustomerApp;

use App\Http\Controllers\Admin\CustomerAppController;
use App\Models\CustomerApp;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Feature: customer-app-services, Property 1: At most one CustomerApp per Service.
 *
 * For any Service and any sequence of CustomerApp create attempts against it, at most one
 * CustomerApp persists for that service_id; the first create succeeds and every subsequent
 * create for the same service is rejected and reported, leaving the stored count at one.
 *
 * Validates: Requirements 1.3, 1.4
 *
 * The one-to-one invariant is enforced at two levels, and both are exercised here:
 *   (1) the DB unique index on customer_apps.service_id - a second direct Model::create against
 *       the same service_id throws a QueryException (sqlite in-memory enforces unique indexes
 *       created by the migration);
 *   (2) the admin controller's $service->customerApp()->exists() guard - a second create via the
 *       controller is rejected with a reported "already has a linked app" message and no new row.
 *
 * innmind/black-box is not a dependency of this project, so rather than adding a new dependency
 * the universal property is exercised with a loop that generates >= 100 random cases, each making
 * a random number (1..6) of create attempts against a single service_id.
 */
class OneToOneInvariantPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const GENERATED_CASES = 120;

    public function test_db_unique_index_allows_only_one_customer_app_per_service(): void
    {
        for ($case = 0; $case < self::GENERATED_CASES; $case++) {
            $service = $this->makeService();
            $attempts = $this->attemptsForCase($case); // 1..6 direct create attempts

            $succeeded = 0;
            $rejected = 0;
            $firstAppId = null;

            for ($attempt = 0; $attempt < $attempts; $attempt++) {
                try {
                    $app = CustomerApp::create([
                        'service_id' => $service->service_id,
                        'repo_url' => 'https://git.example.test/app-'.$attempt.'.git',
                        'hosting_location' => 'cpanel://host/app-'.$attempt,
                        'codebase_info' => 'PHP 8.3 / Laravel 13',
                    ]);

                    $succeeded++;
                    $firstAppId ??= $app->id;
                } catch (QueryException $e) {
                    // The unique index backstop rejected the duplicate create.
                    $rejected++;
                }
            }

            // First wins: exactly one create succeeded, every subsequent attempt was rejected.
            $this->assertSame(
                1,
                $succeeded,
                "expected exactly one successful direct create for case {$case} (attempts={$attempts})"
            );
            $this->assertSame(
                $attempts - 1,
                $rejected,
                "expected every create after the first to be rejected for case {$case} (attempts={$attempts})"
            );

            // At most (exactly) one row persists for this service_id.
            $this->assertSame(
                1,
                CustomerApp::where('service_id', $service->service_id)->count(),
                "expected exactly one persisted CustomerApp for case {$case} (attempts={$attempts})"
            );

            // The persisted row is the first one that won.
            $persisted = CustomerApp::where('service_id', $service->service_id)->first();
            $this->assertSame($firstAppId, $persisted->id);

            // Clean slate for the next generated case.
            $service->customer()->delete();
        }
    }

    public function test_admin_controller_guard_rejects_and_reports_duplicate_creates(): void
    {
        $controller = new CustomerAppController;

        for ($case = 0; $case < self::GENERATED_CASES; $case++) {
            $service = $this->makeService();
            $attempts = $this->attemptsForCase($case); // 1..6 controller store attempts

            $rejectedReports = 0;

            for ($attempt = 0; $attempt < $attempts; $attempt++) {
                // Flush any flash state from a prior attempt/case so each response's session
                // reflects only this store() call (the controller flashes onto the active session).
                session()->flush();

                $request = Request::create('/admin/services/'.$service->service_id.'/apps', 'POST', [
                    'repo_url' => 'https://git.example.test/app-'.$attempt.'.git',
                    'hosting_location' => 'cpanel://host/app-'.$attempt,
                    'codebase_info' => 'PHP 8.3 / Laravel 13',
                ]);

                $response = $controller->store($request, $service->fresh());

                if ($attempt === 0) {
                    // First create succeeds and redirects to the show route.
                    $this->assertNull(
                        $response->getSession()?->get('error'),
                        "first create should not be rejected for case {$case}"
                    );
                } else {
                    // Every subsequent create is rejected and the rejection is reported.
                    $error = $response->getSession()?->get('error');
                    $this->assertNotNull(
                        $error,
                        "duplicate create should be reported for case {$case} (attempt={$attempt})"
                    );
                    $this->assertStringContainsStringIgnoringCase('already has a linked app', (string) $error);
                    $rejectedReports++;
                }
            }

            // Every attempt after the first was reported as rejected.
            $this->assertSame($attempts - 1, $rejectedReports, "rejection-report count mismatch for case {$case}");

            // At most (exactly) one row persists for this service_id.
            $this->assertSame(
                1,
                CustomerApp::where('service_id', $service->service_id)->count(),
                "expected exactly one persisted CustomerApp via the controller for case {$case} (attempts={$attempts})"
            );

            $service->customer()->delete();
        }
    }

    /**
     * 1..6 create attempts, with the single-attempt and max-attempt boundaries always covered.
     */
    private function attemptsForCase(int $case): int
    {
        return match ($case) {
            0 => 1, // boundary: a single create (no duplicates to reject)
            1 => 2, // boundary: the minimal duplicate case
            2 => 6, // boundary: the maximum attempt count
            default => random_int(1, 6),
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
}
