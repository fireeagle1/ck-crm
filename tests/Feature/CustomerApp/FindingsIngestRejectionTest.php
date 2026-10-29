<?php

namespace Tests\Feature\CustomerApp;

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

/**
 * Feature: customer-app-services, Property 4: Invalid or unknown-app payloads are rejected with no mutation.
 *
 * For any ingestion request that either targets an app identifier matching no CustomerApp, or
 * carries a payload failing validation (missing package name/version, missing finding
 * severity/description, or a finding status outside SecurityFinding::STATUSES), the service
 * rejects the request with an error that identifies the unknown app (unknown-app case) or the
 * invalid fields (validation case), and the stored app_packages / security_findings rows are
 * completely unchanged.
 *
 * Code under test: App\Services\FindingsIngestService::ingest.
 *
 * Validates: Requirements 2.2, 3.3, 3.4
 *
 * innmind/black-box is not a dependency of this project, so rather than adding a new dependency
 * the universal property is exercised with a loop that generates >= 100 random cases: each case
 * seeds an existing app with a known prior set of packages/findings, then calls ingest with
 * either a random unknown identifier OR a random invalid payload, asserts the correct exception
 * is thrown with an identifying message, and asserts every stored row is byte-for-byte unchanged.
 */
class FindingsIngestRejectionTest extends TestCase
{
    use RefreshDatabase;

    private const GENERATED_CASES = 150;

    private FindingsIngestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FindingsIngestService();
    }

    public function test_unknown_or_invalid_payloads_are_rejected_without_mutation(): void
    {
        for ($case = 0; $case < self::GENERATED_CASES; $case++) {
            $app = $this->seedApp($case);

            // Capture the full prior state of this app's child rows so we can prove the request
            // mutated nothing — not the rows for this app, and not the global table counts.
            $priorPackages = $this->snapshotPackages($app);
            $priorFindings = $this->snapshotFindings($app);
            $priorPackageTotal = AppPackage::count();
            $priorFindingTotal = SecurityFinding::count();

            if ($this->isUnknownAppCase($case)) {
                $identifier = $this->unknownIdentifier($app);

                try {
                    $this->service->ingest($identifier, $this->validPackages(), $this->validFindings());
                    $this->fail("case {$case}: expected UnknownCustomerAppException for identifier [{$identifier}]");
                } catch (UnknownCustomerAppException $e) {
                    // The error must identify the unknown app (Req 3.3).
                    $this->assertStringContainsString(
                        $identifier,
                        $e->getMessage(),
                        "case {$case}: unknown-app error should name the identifier [{$identifier}]"
                    );
                }
            } else {
                [$packages, $findings, $expectedFragments] = $this->invalidPayload($case);

                try {
                    $this->service->ingest((string) $app->id, $packages, $findings);
                    $this->fail("case {$case}: expected InvalidFindingsPayloadException for invalid payload");
                } catch (InvalidFindingsPayloadException $e) {
                    // The error must identify the invalid fields (Req 3.4, 2.2).
                    $this->assertNotEmpty(
                        $e->errors(),
                        "case {$case}: validation error should list offending fields"
                    );
                    foreach ($expectedFragments as $fragment) {
                        $this->assertTrue(
                            $this->anyErrorContains($e->errors(), $fragment),
                            "case {$case}: validation errors should mention [{$fragment}]; got: "
                                .implode(' | ', $e->errors())
                        );
                    }
                }
            }

            // Property: no mutation. The app's stored rows are identical to the prior snapshot and
            // the global table totals are unchanged (nothing inserted for any app either).
            $this->assertSame(
                $priorPackages,
                $this->snapshotPackages($app),
                "case {$case}: app_packages for the app must be unchanged after rejection"
            );
            $this->assertSame(
                $priorFindings,
                $this->snapshotFindings($app),
                "case {$case}: security_findings for the app must be unchanged after rejection"
            );
            $this->assertSame(
                $priorPackageTotal,
                AppPackage::count(),
                "case {$case}: total app_packages count must be unchanged after rejection"
            );
            $this->assertSame(
                $priorFindingTotal,
                SecurityFinding::count(),
                "case {$case}: total security_findings count must be unchanged after rejection"
            );

            // Clean slate for the next generated case.
            $app->service->customer()->delete();
        }
    }

    /**
     * Half the cases exercise the unknown-app path, the other half the invalid-payload path.
     */
    private function isUnknownAppCase(int $case): bool
    {
        return $case % 2 === 0;
    }

    /**
     * Seed an app with a known, non-empty prior set of packages and findings so "no mutation" is
     * a meaningful assertion (an empty app could not reveal a deletion).
     */
    private function seedApp(int $case): CustomerApp
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

        $app = CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://git.example.test/app.git',
            'hosting_location' => 'cpanel://host/app',
            'codebase_info' => 'PHP 8.3 / Laravel 13',
        ]);

        $packageCount = random_int(1, 5);
        for ($i = 0; $i < $packageCount; $i++) {
            AppPackage::create([
                'customer_app_id' => $app->id,
                'name' => 'prior/package-'.$i,
                'version' => '2.'.$i.'.0',
                'synced_at' => now(),
            ]);
        }

        $statuses = SecurityFinding::STATUSES;
        $findingCount = random_int(1, 5);
        for ($i = 0; $i < $findingCount; $i++) {
            SecurityFinding::create([
                'customer_app_id' => $app->id,
                'severity' => ['low', 'medium', 'high', 'critical'][$i % 4],
                'description' => 'Prior finding '.$i,
                'status' => $statuses[$i % count($statuses)],
                'synced_at' => now(),
            ]);
        }

        return $app->fresh();
    }

    /**
     * An identifier guaranteed not to resolve to an existing CustomerApp.
     */
    private function unknownIdentifier(CustomerApp $app): string
    {
        return match (random_int(0, 2)) {
            // Numeric id well beyond any seeded row.
            0 => (string) ($app->id + random_int(1000, 9999)),
            // Non-numeric garbage identifier.
            1 => 'missing-'.uniqid('', true),
            default => (string) random_int(100000, 999999),
        };
    }

    /**
     * Build a random invalid payload plus the message fragments we expect the validation error to
     * mention. Covers: missing package name/version, missing finding severity/description, and a
     * finding status outside SecurityFinding::STATUSES.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, string>}
     */
    private function invalidPayload(int $case): array
    {
        $variant = random_int(0, 4);

        return match ($variant) {
            0 => [
                // Package missing its name.
                [['version' => '1.0.0']],
                $this->validFindings(),
                ['packages[0].name'],
            ],
            1 => [
                // Package missing its version.
                [['name' => 'acme/widget']],
                $this->validFindings(),
                ['packages[0].version'],
            ],
            2 => [
                // Finding missing its severity.
                $this->validPackages(),
                [['description' => 'Something happened', 'status' => SecurityFinding::STATUS_OPEN]],
                ['findings[0].severity'],
            ],
            3 => [
                // Finding missing its description.
                $this->validPackages(),
                [['severity' => 'high', 'status' => SecurityFinding::STATUS_OPEN]],
                ['findings[0].description'],
            ],
            default => [
                // Finding status outside the defined set.
                $this->validPackages(),
                [[
                    'severity' => 'high',
                    'description' => 'Bad status',
                    'status' => 'not-a-real-status-'.$case,
                ]],
                ['findings[0].status'],
            ],
        };
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function validPackages(): array
    {
        return [
            ['name' => 'acme/alpha', 'version' => '1.0.0'],
            ['name' => 'acme/beta', 'version' => '2.3.4'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function validFindings(): array
    {
        return [
            ['severity' => 'medium', 'description' => 'A valid finding', 'status' => SecurityFinding::STATUS_OPEN],
        ];
    }

    /**
     * @param array<int, string> $errors
     */
    private function anyErrorContains(array $errors, string $fragment): bool
    {
        foreach ($errors as $error) {
            if (str_contains($error, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ordered snapshot of the app's packages as plain comparable arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    private function snapshotPackages(CustomerApp $app): array
    {
        return AppPackage::where('customer_app_id', $app->id)
            ->orderBy('id')
            ->get(['id', 'customer_app_id', 'name', 'version', 'synced_at'])
            ->map(static fn (AppPackage $p): array => [
                'id' => $p->id,
                'customer_app_id' => $p->customer_app_id,
                'name' => $p->name,
                'version' => $p->version,
                'synced_at' => optional($p->synced_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Ordered snapshot of the app's findings as plain comparable arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    private function snapshotFindings(CustomerApp $app): array
    {
        return SecurityFinding::where('customer_app_id', $app->id)
            ->orderBy('id')
            ->get(['id', 'customer_app_id', 'severity', 'description', 'status', 'synced_at'])
            ->map(static fn (SecurityFinding $f): array => [
                'id' => $f->id,
                'customer_app_id' => $f->customer_app_id,
                'severity' => $f->severity,
                'description' => $f->description,
                'status' => $f->status,
                'synced_at' => optional($f->synced_at)->toIso8601String(),
            ])
            ->all();
    }
}
