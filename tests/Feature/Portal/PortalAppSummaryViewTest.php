<?php

namespace Tests\Feature\Portal;

use App\Models\AppPackage;
use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use App\Models\User;
use App\Support\SecuritySummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: customer-app-services — portal app summary view data-minimisation.
 *
 * Exercises App\Http\Controllers\Portal\ServiceController::show rendering
 * resources/views/portal/services/show.blade.php for a portal user owning a
 * Service with a linked CustomerApp. The managed-app card is built from
 * CustomerAppSummaryDto::fromService($service), which exposes only the four
 * allow-listed keys (name, status, billing, security_summary).
 *
 * Asserts the customer surface:
 *  - renders the service name, status, and billing (Req 5.1);
 *  - renders the status-only security summary from SecuritySummaryService
 *    (Req 5.2, 6.2); and
 *  - exposes NO technical field — repo_url, hosting_location, codebase_info,
 *    package names/versions, or finding severities/descriptions are all
 *    structurally absent from the rendered HTML (Req 5.3, 5.6).
 *
 * Distinctive "DO-NOT-LEAK" sentinel strings are seeded into every technical
 * field so assertDontSee is meaningful: a leak would surface the sentinel.
 *
 * _Requirements: 5.1, 5.6_
 */
class PortalAppSummaryViewTest extends TestCase
{
    use RefreshDatabase;

    private const OPEN_FINDING_COUNT = 3;

    private const REPO_URL = 'https://secret-repo.example/DO-NOT-LEAK-repo.git';
    private const HOSTING_LOCATION = 'cpanel://secret-host.example/DO-NOT-LEAK-hosting';
    private const CODEBASE_INFO = 'Laravel 13 on PHP 8.3 — DO-NOT-LEAK-codebase internals';

    private const PACKAGE_NAME = 'vendor/DO-NOT-LEAK-package';
    private const PACKAGE_VERSION = '6.6.6-DO-NOT-LEAK';

    private const FINDING_SEVERITY = 'critical-DO-NOT-LEAK';
    private const FINDING_DESCRIPTION = 'SQL injection in checkout — DO-NOT-LEAK-finding';

    private const SERVICE_NAME = 'Acme Managed Portal App';
    private const SERVICE_STATUS = 'Active';
    private const SERVICE_MONTHLY_CHARGE = 49.99;
    private const SERVICE_PAYMENT_FREQUENCY = 'month';

    public function test_portal_app_view_shows_summary_only_and_leaks_no_technical_field(): void
    {
        $customer = Customer::factory()->create();

        $user = User::factory()->create([
            'company_id' => $customer->company_id,
            'email_verified_at' => now(),
        ]);

        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => self::SERVICE_NAME,
            'service_type' => 'Managed App',
            'status' => self::SERVICE_STATUS,
            'service_monthly_charge' => self::SERVICE_MONTHLY_CHARGE,
            'service_payment_frequency' => self::SERVICE_PAYMENT_FREQUENCY,
        ]);

        $app = CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => self::REPO_URL,
            'hosting_location' => self::HOSTING_LOCATION,
            'codebase_info' => self::CODEBASE_INFO,
        ]);

        // Distinctive package the customer must never see.
        AppPackage::create([
            'customer_app_id' => $app->id,
            'name' => self::PACKAGE_NAME,
            'version' => self::PACKAGE_VERSION,
            'synced_at' => now(),
        ]);

        // A known number of OPEN findings drives the summary wording, plus a
        // resolved finding to prove the summary counts open status only.
        for ($i = 0; $i < self::OPEN_FINDING_COUNT; $i++) {
            SecurityFinding::create([
                'customer_app_id' => $app->id,
                'severity' => self::FINDING_SEVERITY,
                'description' => self::FINDING_DESCRIPTION.' #'.$i,
                'status' => SecurityFinding::STATUS_OPEN,
                'synced_at' => now(),
            ]);
        }
        SecurityFinding::create([
            'customer_app_id' => $app->id,
            'severity' => self::FINDING_SEVERITY,
            'description' => self::FINDING_DESCRIPTION.' resolved',
            'status' => SecurityFinding::STATUS_RESOLVED,
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('portal.services.show', $service));

        $response->assertOk();

        // 1. The status-only security summary is present, matching the exact
        //    wording SecuritySummaryService produces for the seeded open count.
        $app->refresh();
        $expectedSummary = SecuritySummaryService::for($app);
        $this->assertSame('3 issues being addressed', $expectedSummary);
        $response->assertSeeText($expectedSummary);

        // 3. The service name, status, and billing ARE present.
        $response->assertSeeText(self::SERVICE_NAME);
        $response->assertSeeText(self::SERVICE_STATUS);
        $response->assertSeeText('£'.number_format(self::SERVICE_MONTHLY_CHARGE, 2).'/'.self::SERVICE_PAYMENT_FREQUENCY);

        // 2. NO technical field leaks into the rendered HTML. Each sentinel
        //    would only appear if a technical value reached the customer view.
        $response->assertDontSee(self::REPO_URL);
        $response->assertDontSee(self::HOSTING_LOCATION);
        $response->assertDontSee(self::CODEBASE_INFO);
        $response->assertDontSee(self::PACKAGE_NAME);
        $response->assertDontSee(self::PACKAGE_VERSION);
        $response->assertDontSee(self::FINDING_SEVERITY);
        $response->assertDontSee(self::FINDING_DESCRIPTION);

        // Belt-and-braces: the shared sentinel marker must be absent entirely.
        $response->assertDontSee('DO-NOT-LEAK');
    }
}
