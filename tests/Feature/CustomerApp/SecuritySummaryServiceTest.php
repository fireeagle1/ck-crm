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
 * Focused example unit tests for SecuritySummaryService::for() branches
 * (task 4.3). Each test pins one branch of the summary derivation:
 *   - null app / zero open findings -> "Security checks passing" (Req 6.1)
 *   - exactly one open finding       -> "1 issue being addressed" (Req 6.2)
 *   - many open findings             -> "{N} issues being addressed" (Req 6.2)
 *   - resolved / dismissed findings are never counted (Req 6.3)
 *
 * **Validates: Requirements 6.1, 6.2, 6.3**
 */
class SecuritySummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_app_is_passing(): void
    {
        // Req 6.1: no app means nothing to address.
        $this->assertSame(
            'Security checks passing',
            SecuritySummaryService::for(null)
        );
    }

    public function test_app_with_zero_findings_is_passing(): void
    {
        // Req 6.1: an app with no findings at all is passing.
        $app = $this->makeApp();

        $this->assertSame(
            'Security checks passing',
            SecuritySummaryService::for($app)
        );
    }

    public function test_single_open_finding_uses_singular_wording(): void
    {
        // Req 6.2: exactly one open finding -> singular wording.
        $app = $this->makeApp();
        $this->makeFinding($app, SecurityFinding::STATUS_OPEN);

        $this->assertSame(
            '1 issue being addressed',
            SecuritySummaryService::for($app)
        );
    }

    public function test_multiple_open_findings_use_plural_wording_with_count(): void
    {
        // Req 6.2: more than one open finding -> plural wording with the count.
        $app = $this->makeApp();
        $this->makeFinding($app, SecurityFinding::STATUS_OPEN);
        $this->makeFinding($app, SecurityFinding::STATUS_OPEN);
        $this->makeFinding($app, SecurityFinding::STATUS_OPEN);

        $this->assertSame(
            '3 issues being addressed',
            SecuritySummaryService::for($app)
        );
    }

    public function test_resolved_and_dismissed_findings_are_not_counted(): void
    {
        // Req 6.3: the open-count ignores resolved/dismissed findings, so a
        // mix of 2 open + 1 resolved + 1 dismissed reports exactly 2.
        $app = $this->makeApp();
        $this->makeFinding($app, SecurityFinding::STATUS_OPEN);
        $this->makeFinding($app, SecurityFinding::STATUS_OPEN);
        $this->makeFinding($app, SecurityFinding::STATUS_RESOLVED);
        $this->makeFinding($app, SecurityFinding::STATUS_DISMISSED);

        $this->assertSame(
            '2 issues being addressed',
            SecuritySummaryService::for($app)
        );
    }

    private function makeApp(): CustomerApp
    {
        $customer = Customer::factory()->create();

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

    private function makeFinding(CustomerApp $app, string $status): SecurityFinding
    {
        return SecurityFinding::create([
            'customer_app_id' => $app->id,
            'severity' => 'high',
            'description' => 'Example finding',
            'status' => $status,
        ]);
    }
}
