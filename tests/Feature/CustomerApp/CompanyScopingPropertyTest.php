<?php

namespace Tests\Feature\CustomerApp;

use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: customer-app-services, Property 8: Customer app access is confined to the owning company.
 *
 * For any Service owned by one company and any authenticated portal user, the portal service show
 * route (App\Http\Controllers\Portal\ServiceController::show, reached via
 * GET route('portal.services.show', $service)) grants access (HTTP 200) if and only if the acting
 * user's company_id equals the service's company_id, and denies access (HTTP 403) otherwise. The
 * guard under test is: if ($service->company_id !== $request->user()->company_id) abort(403).
 *
 * Validates: Requirements 5.5
 *
 * innmind/black-box is not a dependency of this project, so rather than adding a new dependency the
 * universal property is exercised with a loop that generates >= 100 random cases. Each case builds
 * two customers (company A and company B) with a portal user each, creates a Service owned by one of
 * the two companies (sometimes with a linked CustomerApp, sometimes not), then acts as a randomly
 * chosen user and hits the show route, asserting 200 when the acting user owns the service and 403
 * when they do not.
 */
class CompanyScopingPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const GENERATED_CASES = 150;

    public function test_portal_service_access_is_confined_to_the_owning_company(): void
    {
        $granted = 0;
        $denied = 0;

        for ($case = 0; $case < self::GENERATED_CASES; $case++) {
            // Two independent companies, each with its own verified portal user.
            $customerA = Customer::factory()->create();
            $customerB = Customer::factory()->create();

            $userA = User::factory()->create([
                'company_id' => $customerA->company_id,
                'email_verified_at' => now(),
            ]);
            $userB = User::factory()->create([
                'company_id' => $customerB->company_id,
                'email_verified_at' => now(),
            ]);

            // The service is owned by one of the two companies, chosen pseudo-randomly.
            $ownerIsA = (bool) random_int(0, 1);
            $owner = $ownerIsA ? $customerA : $customerB;

            $service = Service::create([
                'company_id' => $owner->company_id,
                'service_short' => 'Managed App '.$case,
                'status' => 'Active',
            ]);

            // Sometimes link a CustomerApp (with findings) so the DTO/app-summary branch of the
            // controller is exercised too; the ownership guard runs before that branch regardless.
            if (random_int(0, 1) === 1) {
                $app = CustomerApp::create([
                    'service_id' => $service->service_id,
                    'repo_url' => 'https://git.example.test/app-'.$case.'.git',
                    'hosting_location' => 'cpanel://host/app-'.$case,
                    'codebase_info' => 'PHP 8.3 / Laravel 13',
                ]);

                $openFindings = random_int(0, 3);
                for ($f = 0; $f < $openFindings; $f++) {
                    SecurityFinding::create([
                        'customer_app_id' => $app->id,
                        'severity' => 'medium',
                        'description' => 'Finding '.$f,
                        'status' => SecurityFinding::STATUS_OPEN,
                        'synced_at' => now(),
                    ]);
                }
            }

            // Act as a randomly chosen user (owner or non-owner).
            $actAsA = (bool) random_int(0, 1);
            $actingUser = $actAsA ? $userA : $userB;

            $response = $this->actingAs($actingUser)
                ->get(route('portal.services.show', $service));

            $actingOwnsService = $actingUser->company_id === $service->company_id;

            if ($actingOwnsService) {
                $response->assertStatus(200);
                $granted++;
            } else {
                $response->assertStatus(403);
                $denied++;
            }
        }

        // Sanity: the generated cases exercised both the grant and the deny branch.
        $this->assertGreaterThan(0, $granted, 'Expected at least one owning-company (200) case.');
        $this->assertGreaterThan(0, $denied, 'Expected at least one foreign-company (403) case.');
    }
}
