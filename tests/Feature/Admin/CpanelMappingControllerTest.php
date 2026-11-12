<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\User;
use App\Services\WhmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeWhmService;
use Tests\TestCase;

/**
 * Validates: Requirements 2.1, 7.1, 8.9
 */
class CpanelMappingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FakeWhmService $whm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->is_admin = true;
        $this->admin->save();

        $this->whm = new FakeWhmService();
        $this->app->instance(WhmService::class, $this->whm);
    }

    private function submit(array $rows)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.services.cpanel-mapping'))
            ->put(route('admin.services.cpanel-mapping.update'), [
                'mappings' => array_values($rows),
            ]);
    }

    public function test_duplicate_is_rejected_naming_the_other_service(): void
    {
        Service::factory()->withCpanel('taken')->create(['service_short' => 'Holder Hosting']);
        $b = Service::factory()->create();

        $this->submit([['service_id' => $b->service_id, 'cpanel_username' => 'TAKEN']])
            ->assertSessionHasErrors('mappings.0.cpanel_username');

        $this->assertStringContainsString(
            'Holder Hosting',
            session('errors')->first('mappings.0.cpanel_username')
        );
        $this->assertNull($b->fresh()->cpanel_username);
    }

    public function test_rejected_batch_applies_no_change(): void
    {
        Service::factory()->withCpanel('taken')->create();
        $a = Service::factory()->create();
        $b = Service::factory()->create();

        $this->submit([
            ['service_id' => $a->service_id, 'cpanel_username' => 'fresh'],
            ['service_id' => $b->service_id, 'cpanel_username' => 'taken'],
        ])->assertSessionHasErrors('mappings.1.cpanel_username');

        $this->assertNull($a->fresh()->cpanel_username);
        $this->assertNull($b->fresh()->cpanel_username);
    }

    public function test_valid_batch_is_normalised_to_lowercase(): void
    {
        $a = Service::factory()->create();

        $this->submit([['service_id' => $a->service_id, 'cpanel_username' => '  MixedCase ']])
            ->assertSessionHasNoErrors();

        $this->assertSame('mixedcase', $a->fresh()->cpanel_username);
    }

    public function test_index_lists_accounts_from_whm_service(): void
    {
        $this->whm->addAccount('acct1', 'one.example.com');

        $this->actingAs($this->admin)
            ->get(route('admin.services.cpanel-mapping'))
            ->assertOk()
            ->assertSee('acct1');

        $this->assertSame(1, $this->whm->callCount('listAccounts'));
    }

    public function test_whm_failure_shows_message_and_empty_list(): void
    {
        $this->whm->addAccount('acct1', 'one.example.com')->failAt('listAccounts');

        $this->actingAs($this->admin)
            ->get(route('admin.services.cpanel-mapping'))
            ->assertOk()
            ->assertSee('Could not load accounts from WHM')
            ->assertDontSee('acct1');
    }
}
