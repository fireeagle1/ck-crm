<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\WhmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeWhmService;
use Tests\TestCase;

/**
 * Requirements: 7.1, 7.5
 */
class SsoControllerTest extends TestCase
{
    use RefreshDatabase;

    private FakeWhmService $whm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->whm = (new FakeWhmService())->addAccount('alice', 'alice.com');
        $this->app->instance(WhmService::class, $this->whm);
    }

    private function userAndService(?string $cpanelUser = 'alice'): array
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create([
            'company_id' => $customer->company_id,
            'email_verified_at' => now(),
        ]);
        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Hosting',
            'service_type' => 'Web Hosting',
            'status' => 'Active',
            'cpanel_username' => $cpanelUser,
        ]);

        return [$user, $service];
    }

    public function test_owner_is_redirected_to_session_created_via_whm_service(): void
    {
        [$user, $service] = $this->userAndService();

        $response = $this->actingAs($user)->post(route('portal.services.sso.cpanel', $service));

        $response->assertRedirect('https://whm.fake.test/cpsess0000000000/login/?user=alice&service=cpaneld');
        $this->assertSame(1, $this->whm->callCount('createUserSession'));
    }

    public function test_webmail_uses_webmaild_service(): void
    {
        [$user, $service] = $this->userAndService();

        $this->actingAs($user)->post(route('portal.services.sso.webmail', $service))
            ->assertRedirect('https://whm.fake.test/cpsess0000000000/login/?user=alice&service=webmaild');
    }

    public function test_other_customers_are_blocked_without_calling_whm(): void
    {
        [, $service] = $this->userAndService();
        $otherCustomer = Customer::factory()->create();
        $other = User::factory()->create([
            'company_id' => $otherCustomer->company_id,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($other)->post(route('portal.services.sso.cpanel', $service))->assertForbidden();
        $this->assertSame(0, $this->whm->callCount('createUserSession'));
    }

    public function test_whm_failure_flashes_error_and_does_not_redirect_to_whm(): void
    {
        [$user, $service] = $this->userAndService();
        $this->whm->failAt('createUserSession');

        $this->actingAs($user)->from('/portal/dashboard')
            ->post(route('portal.services.sso.cpanel', $service))
            ->assertRedirect('/portal/dashboard')
            ->assertSessionHas('error');
    }
}
