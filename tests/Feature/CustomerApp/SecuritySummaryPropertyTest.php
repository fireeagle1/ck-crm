<?php

namespace Tests\Feature\CustomerApp;

use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use App\Support\SecuritySummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-based test for the security summary derivation.
 *
 * innmind/black-box is not available in this project, so the property is
 * exercised with a PHPUnit loop that generates >= 100 random finding
 * multisets (a mix of open / resolved / dismissed findings, plus the
 * empty-app and null-app edge cases) and asserts the universal property
 * holds for every generated case.
 *
 * Feature: customer-app-services, Property 7: Security summary is a
 * status-only function of open findings.
 *
 * **Validates: Requirements 5.2, 6.1, 6.2, 6.3**
 */
class SecuritySummaryPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Severity labels, descriptions, and package-ish tokens that are seeded
     * onto findings. The summary must never surface any of these, because it
     * is derived from status alone (Req 6.3).
     */
    private const SEVERITIES = ['critical', 'high', 'medium', 'low', 'informational'];

    private const DESCRIPTIONS = [
        'SQL injection in the login form',
        'Outdated TLS configuration on the edge',
        'Reflected XSS in the search endpoint',
        'Hardcoded credentials committed to the repo',
        'Vulnerable dependency with a known CVE',
    ];

    /** Tokens that would indicate a leak of technical/package detail. */
    private const FORBIDDEN_SUBSTRINGS = [
        'critical', 'high', 'medium', 'low', 'informational',
        'sql', 'xss', 'tls', 'cve', 'credentials', 'injection',
        'laravel/framework', 'guzzlehttp/guzzle', 'repo', 'package',
        'severity', 'description', '1.0.0', '13.0.0',
    ];

    public function test_security_summary_is_a_status_only_function_of_open_findings(): void
    {
        $customer = Customer::factory()->create();

        $cases = 120;

        for ($i = 0; $i < $cases; $i++) {
            // Edge cases: a null app and an app with no findings at all.
            if ($i === 0) {
                $this->assertSummaryForOpenCount(SecuritySummaryService::for(null), 0);

                continue;
            }

            $app = $this->makeApp($customer);

            // Random multiset of findings: 0..12 findings, each with a random
            // status, severity, and description.
            $findingCount = random_int(0, 12);
            $expectedOpen = 0;

            for ($f = 0; $f < $findingCount; $f++) {
                $status = self::randomStatus();

                if ($status === SecurityFinding::STATUS_OPEN) {
                    $expectedOpen++;
                }

                SecurityFinding::create([
                    'customer_app_id' => $app->id,
                    'severity' => self::SEVERITIES[array_rand(self::SEVERITIES)],
                    'description' => self::DESCRIPTIONS[array_rand(self::DESCRIPTIONS)],
                    'status' => $status,
                ]);
            }

            $summary = SecuritySummaryService::for($app->fresh());

            $this->assertSummaryForOpenCount($summary, $expectedOpen);
        }
    }

    /**
     * Assert the summary matches the expected wording for the given open
     * count and that it never leaks severity / description / package text.
     */
    private function assertSummaryForOpenCount(string $summary, int $openCount): void
    {
        if ($openCount === 0) {
            // "passing" iff open-count == 0 (Req 6.1).
            $this->assertSame('Security checks passing', $summary);
        } elseif ($openCount === 1) {
            // Exact singular wording (Req 6.2).
            $this->assertSame('1 issue being addressed', $summary);
        } else {
            // Exact plural wording with the precise count (Req 6.2).
            $this->assertSame("{$openCount} issues being addressed", $summary);
        }

        // The "passing" string appears iff there are no open findings (Req 6.1).
        $this->assertSame(
            $openCount === 0,
            $summary === 'Security checks passing',
            "Summary '{$summary}' should be the passing string iff open count is zero (open={$openCount})."
        );

        // The summary carries no severity label, description, or package
        // reference (Req 6.3, 5.2) - it is a function of status alone.
        $haystack = strtolower($summary);
        foreach (self::FORBIDDEN_SUBSTRINGS as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $haystack,
                "Summary '{$summary}' leaked forbidden token '{$forbidden}'."
            );
        }
    }

    private static function randomStatus(): string
    {
        return SecurityFinding::STATUSES[array_rand(SecurityFinding::STATUSES)];
    }

    private function makeApp(Customer $customer): CustomerApp
    {
        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Managed App',
            'status' => 'active',
        ]);

        return CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://example.com/repo.git',
            'hosting_location' => 'cpanel-01',
            'codebase_info' => 'Laravel 13 / PHP 8.3',
        ]);
    }
}
