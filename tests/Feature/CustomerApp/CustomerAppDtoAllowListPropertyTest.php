<?php

namespace Tests\Feature\CustomerApp;

use App\Models\AppPackage;
use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use App\Support\Dto\CustomerAppSummaryDto;
use App\Support\SecuritySummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-based test for the customer-facing DTO allow-list.
 *
 * innmind/black-box is not available in this project, so the property is
 * exercised with a PHPUnit loop that generates >= 100 random app/service
 * pairs. Every pair has all technical fields populated with distinctive
 * random sentinel strings (repo_url, hosting_location, codebase_info, plus
 * random package names/versions and finding severities/descriptions). For
 * each generated case the test asserts:
 *
 *   (1) the DTO's keys are a subset of {name, status, billing,
 *       security_summary};
 *   (2) none of the forbidden sentinel values appear anywhere in the
 *       JSON-serialised DTO (i.e. no technical detail leaks, recursively);
 *   (3) the DTO values match the Service-derived name/status/billing and the
 *       status-only security summary;
 *   (4) the DTO is a plain hand-built array, never model serialisation.
 *
 * Feature: customer-app-services, Property 6: Customer output contains
 * exactly the allow-listed fields and no technical detail.
 *
 * **Validates: Requirements 5.1, 5.3, 5.4**
 */
class CustomerAppDtoAllowListPropertyTest extends TestCase
{
    use RefreshDatabase;

    /** The complete, exclusive allow-list for the customer surface. */
    private const ALLOW_LIST = ['name', 'install_url', 'status', 'billing', 'security_summary'];

    private const SEVERITIES = ['critical', 'high', 'medium', 'low', 'informational'];

    private const STATUSES = ['active', 'suspended', 'pending', 'cancelled'];

    private const FREQUENCIES = ['monthly', 'yearly', 'quarterly'];

    public function test_dto_exposes_only_allow_listed_fields_and_no_technical_detail(): void
    {
        $customer = Customer::factory()->create();

        $cases = 120;

        for ($i = 0; $i < $cases; $i++) {
            // Distinctive sentinel strings unique to this case; if any of them
            // surface in the DTO it proves a technical-detail leak.
            $repoUrl = "https://git.example.test/REPO-SENTINEL-{$i}-".bin2hex(random_bytes(4)).'.git';
            $hosting = "HOSTING-SENTINEL-{$i}-".bin2hex(random_bytes(4));
            $codebase = "CODEBASE-SENTINEL-{$i}-".bin2hex(random_bytes(4));

            // Randomise billing shape: roughly half free, half charged.
            $charged = random_int(0, 1) === 1;

            $service = Service::create([
                'company_id' => $customer->company_id,
                'service_short' => "App Name {$i}",
                'status' => self::STATUSES[array_rand(self::STATUSES)],
                'service_monthly_charge' => $charged ? random_int(100, 50000) / 100 : null,
                'service_payment_frequency' => $charged ? self::FREQUENCIES[array_rand(self::FREQUENCIES)] : null,
                'stripe_subscription_id' => ($charged && random_int(0, 1) === 1)
                    ? 'sub_'.bin2hex(random_bytes(6))
                    : null,
            ]);

            $app = CustomerApp::create([
                'service_id' => $service->service_id,
                'repo_url' => $repoUrl,
                'hosting_location' => $hosting,
                'codebase_info' => $codebase,
            ]);

            // Random packages with sentinel name/version values.
            $forbidden = [$repoUrl, $hosting, $codebase];

            $packageCount = random_int(0, 6);
            for ($p = 0; $p < $packageCount; $p++) {
                $name = "PKG-NAME-SENTINEL-{$i}-{$p}-".bin2hex(random_bytes(3));
                $version = "PKG-VERSION-SENTINEL-{$i}-{$p}-".bin2hex(random_bytes(3));
                $forbidden[] = $name;
                $forbidden[] = $version;

                AppPackage::create([
                    'customer_app_id' => $app->id,
                    'name' => $name,
                    'version' => $version,
                ]);
            }

            // Random findings with sentinel severity/description values.
            $findingCount = random_int(0, 6);
            $expectedOpen = 0;
            for ($f = 0; $f < $findingCount; $f++) {
                $status = SecurityFinding::STATUSES[array_rand(SecurityFinding::STATUSES)];
                if ($status === SecurityFinding::STATUS_OPEN) {
                    $expectedOpen++;
                }

                // Severity carries a sentinel suffix so a leak of the exact
                // stored severity value is detectable, while still being a
                // recognisable label prefix.
                $severity = self::SEVERITIES[array_rand(self::SEVERITIES)]
                    ."-SEV-SENTINEL-{$i}-{$f}-".bin2hex(random_bytes(3));
                $description = "DESC-SENTINEL-{$i}-{$f}-".bin2hex(random_bytes(4));
                $forbidden[] = $severity;
                $forbidden[] = $description;

                SecurityFinding::create([
                    'customer_app_id' => $app->id,
                    'severity' => $severity,
                    'description' => $description,
                    'status' => $status,
                ]);
            }

            $dto = CustomerAppSummaryDto::fromService($service->fresh());

            $context = "case {$i}";

            // (1) Keys are a subset of the allow-list (Req 5.1, 5.4).
            $extraKeys = array_diff(array_keys($dto), self::ALLOW_LIST);
            $this->assertSame(
                [],
                array_values($extraKeys),
                "{$context}: DTO exposed keys outside the allow-list: ".implode(', ', $extraKeys)
            );

            // (2) No forbidden sentinel appears anywhere in the serialised DTO
            //     (recursively, via JSON) (Req 5.3).
            $json = json_encode($dto, JSON_THROW_ON_ERROR);
            foreach ($forbidden as $sentinel) {
                $this->assertStringNotContainsString(
                    $sentinel,
                    $json,
                    "{$context}: DTO leaked forbidden technical value '{$sentinel}'."
                );
            }

            // (3) The DTO values match the Service-derived fields and the
            //     status-only security summary (Req 5.1).
            $this->assertSame($service->service_short, $dto['name'], "{$context}: name mismatch.");
            $this->assertSame($service->status, $dto['status'], "{$context}: status mismatch.");
            $this->assertSame(
                SecuritySummaryService::for($app->fresh()),
                $dto['security_summary'],
                "{$context}: security_summary mismatch."
            );
            $this->assertSame(
                $this->expectedSummary($expectedOpen),
                $dto['security_summary'],
                "{$context}: security_summary not a status-only function of open findings."
            );
            $this->assertSame(
                $this->expectedBilling($service->fresh()),
                $dto['billing'],
                "{$context}: billing mismatch."
            );

            // (4) The DTO is a plain hand-built array, never produced by
            //     serialising an Eloquent model (Req 5.4). A model's toArray()
            //     would include technical columns / timestamps / the id and
            //     would fail the allow-list check above, but assert the shape
            //     explicitly for clarity.
            $this->assertIsArray($dto, "{$context}: DTO must be a plain array.");
            $this->assertArrayNotHasKey('id', $dto, "{$context}: DTO must not carry a model id.");
            $this->assertArrayNotHasKey('repo_url', $dto, "{$context}: DTO must not carry repo_url.");
            $this->assertArrayNotHasKey('hosting_location', $dto, "{$context}: DTO must not carry hosting_location.");
            $this->assertArrayNotHasKey('codebase_info', $dto, "{$context}: DTO must not carry codebase_info.");

            // Reset for the next case so each app/service pair is independent.
            $service->delete();
        }
    }

    private function expectedSummary(int $openCount): string
    {
        if ($openCount === 0) {
            return 'Security checks passing';
        }

        return $openCount === 1
            ? '1 issue being addressed'
            : "{$openCount} issues being addressed";
    }

    private function expectedBilling(Service $service): string
    {
        $charge = $service->service_monthly_charge;
        $hasCharge = $charge !== null && (float) $charge > 0;

        if (empty($service->stripe_subscription_id) && ! $hasCharge) {
            return 'Free';
        }

        $amount = '£'.number_format((float) $charge, 2);

        return $service->service_payment_frequency
            ? "{$amount}/{$service->service_payment_frequency}"
            : $amount;
    }
}
