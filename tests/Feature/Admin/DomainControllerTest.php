<?php

namespace Tests\Feature\Admin;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Validates: Requirements 11.4, 13.3 */
class DomainControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->is_admin = true;
        $this->admin->save();
    }

    public function test_store_normalises_name_before_saving(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.domains.store'), ['domain_name' => ' WWW.Example.COM. '])
            ->assertRedirect(route('admin.domains.index'));

        $this->assertDatabaseHas('domains', ['domain_name' => 'example.com']);
    }

    public function test_variants_are_treated_as_duplicates(): void
    {
        Domain::factory()->create(['domain_name' => 'example.com']);

        foreach (['EXAMPLE.com', 'www.example.com', 'example.com.'] as $variant) {
            $this->actingAs($this->admin)
                ->post(route('admin.domains.store'), ['domain_name' => $variant])
                ->assertSessionHasErrors('domain_name');
        }

        $this->assertSame(1, Domain::withTrashed()->count());
    }

    public function test_update_variant_of_another_domain_is_rejected_but_own_name_allowed(): void
    {
        Domain::factory()->create(['domain_name' => 'taken.com']);
        $domain = Domain::factory()->create(['domain_name' => 'mine.com']);

        $this->actingAs($this->admin)
            ->put(route('admin.domains.update', $domain), ['domain_name' => 'WWW.Taken.com'])
            ->assertSessionHasErrors('domain_name');

        $this->actingAs($this->admin)
            ->put(route('admin.domains.update', $domain), ['domain_name' => 'MINE.com'])
            ->assertSessionHasNoErrors();
    }

    public function test_trashed_duplicate_error_mentions_restore_and_exposes_id(): void
    {
        $trashed = Domain::factory()->create(['domain_name' => 'gone.com']);
        $trashed->delete();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.domains.store'), ['domain_name' => 'Gone.com']);

        $response->assertSessionHasErrors('domain_name');
        $response->assertSessionHas('trashed_domain_id', $trashed->id);
        $this->assertStringContainsString('Restore', session('errors')->first('domain_name'));
        $this->assertSame(1, Domain::withTrashed()->count());
    }

    public function test_edits_to_enom_fields_are_flagged(): void
    {
        $domain = Domain::factory()->create([
            'domain_name' => 'flag.com',
            'registrar' => 'eNom',
            'expiry_date' => '2027-01-15',
            'admin_edited_fields' => null,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.domains.update', $domain), [
                'domain_name' => 'flag.com',
                'registrar' => 'eNom',
                'expiry_date' => '2028-02-01',
                'domain_admin_notes' => 'note',
            ])
            ->assertSessionHasNoErrors();

        $domain->refresh();
        $this->assertSame(['expiry_date'], $domain->admin_edited_fields);
        $this->assertSame('2028-02-01', $domain->expiry_date->toDateString());
        $this->assertSame('note', $domain->domain_admin_notes);
    }

    public function test_index_counts_equal_filtered_rows(): void
    {
        $customer = \App\Models\Customer::factory()->create();
        Domain::factory()->create(['company_id' => $customer->company_id, 'expiry_date' => now()->addDays(10)]);
        Domain::factory()->create(['company_id' => $customer->company_id, 'expiry_date' => now()->addDays(200)]);
        Domain::factory()->create(['company_id' => $customer->company_id, 'expiry_date' => now()->subDays(10)]);
        Domain::factory()->create(['company_id' => $customer->company_id, 'expiry_date' => now()->subYears(3)]); // hidden
        Domain::factory()->create(['company_id' => null, 'expiry_date' => now()->addDays(100)]);
        Domain::factory()->create([
            'company_id' => $customer->company_id,
            'expiry_date' => now()->addDays(100),
            'status' => Domain::STATUS_MISSING_FROM_ENOM,
        ]);

        $expected = [
            'all' => Domain::visible()->count(),
            'expiring' => Domain::expiring()->count(),
            'expired' => Domain::expired()->count(),
            'unassigned' => Domain::visible()->unassigned()->count(),
            'missing' => Domain::missingFromEnom()->count(),
        ];
        $this->assertSame(['all' => 5, 'expiring' => 1, 'expired' => 1, 'unassigned' => 1, 'missing' => 1], $expected);

        $keys = ['all' => 'totalDomains', 'expiring' => 'expiringCount', 'expired' => 'expiredCount',
            'unassigned' => 'unassignedCount', 'missing' => 'missingCount'];

        foreach ($expected as $filter => $count) {
            $response = $this->actingAs($this->admin)->get(route('admin.domains.index', ['filter' => $filter]));
            $response->assertOk();
            $this->assertSame($count, $response->viewData('domains')->total(), "rows for {$filter}");
            $this->assertSame($count, $response->viewData($keys[$filter]), "count for {$filter}");
        }
    }

    /** Validates: Requirements 20.1, 20.2 */
    public function test_unassigned_and_missing_filters_return_exactly_matching_non_trashed_domains(): void
    {
        $customer = \App\Models\Customer::factory()->create();
        $other = \App\Models\Customer::factory()->create();

        $unassigned = Domain::factory()->create(['company_id' => null, 'domain_name' => 'unassigned-a.com', 'expiry_date' => now()->addDays(90)]);
        $trashedUnassigned = Domain::factory()->create(['company_id' => null, 'domain_name' => 'unassigned-trashed.com', 'expiry_date' => now()->addDays(90)]);
        $trashedUnassigned->delete();
        $assigned = Domain::factory()->create(['company_id' => $customer->company_id, 'domain_name' => 'assigned-a.com', 'expiry_date' => now()->addDays(90)]);

        $missing = Domain::factory()->create([
            'company_id' => $customer->company_id, 'domain_name' => 'missing-a.com',
            'status' => Domain::STATUS_MISSING_FROM_ENOM, 'expiry_date' => now()->addDays(90),
        ]);
        $missingOther = Domain::factory()->create([
            'company_id' => $other->company_id, 'domain_name' => 'missing-b.com',
            'status' => Domain::STATUS_MISSING_FROM_ENOM, 'expiry_date' => now()->addDays(90),
        ]);
        $trashedMissing = Domain::factory()->create([
            'company_id' => $customer->company_id, 'domain_name' => 'missing-trashed.com',
            'status' => Domain::STATUS_MISSING_FROM_ENOM, 'expiry_date' => now()->addDays(90),
        ]);
        $trashedMissing->delete();

        $ids = fn ($response) => $response->viewData('domains')->pluck('id')->sort()->values()->all();

        $r = $this->actingAs($this->admin)->get(route('admin.domains.index', ['filter' => 'unassigned']));
        $r->assertOk();
        $this->assertSame([$unassigned->id], $ids($r));
        $this->assertSame(1, $r->viewData('unassignedCount'));
        $r->assertSee('Unassigned (1)', false)->assertSee('Missing from eNom (2)', false);
        $r->assertSee('aria-current="page"', false);
        $this->assertSame(1, substr_count($r->getContent(), 'aria-current="page"'));

        $r = $this->actingAs($this->admin)->get(route('admin.domains.index', ['filter' => 'missing']));
        $this->assertSame([$missing->id, $missingOther->id], $ids($r));
        $this->assertSame(2, $r->viewData('missingCount'));

        // Composes with the customer filter.
        $r = $this->actingAs($this->admin)->get(route('admin.domains.index', ['filter' => 'missing', 'customer' => $customer->company_id]));
        $this->assertSame([$missing->id], $ids($r));
        $this->assertSame(1, $r->viewData('missingCount'));

        $r = $this->actingAs($this->admin)->get(route('admin.domains.index', ['filter' => 'unassigned', 'customer' => $customer->company_id]));
        $this->assertSame([], $ids($r));
        $this->assertSame(0, $r->viewData('unassignedCount'));
    }

    public function test_customer_search_matches_and_caps_at_20(): void
    {
        \App\Models\Customer::factory()->create(['company_name' => 'Zebra Widgets Ltd']);
        foreach (range(1, 25) as $i) {
            \App\Models\Customer::factory()->create(['company_name' => "Acme Corp {$i}"]);
        }

        $this->actingAs($this->admin)->getJson(route('admin.domains.customer-search', ['q' => 'zebra']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Zebra Widgets Ltd');

        $this->actingAs($this->admin)->getJson(route('admin.domains.customer-search', ['q' => 'acme']))
            ->assertOk()->assertJsonCount(20);

        $this->actingAs($this->admin)->getJson(route('admin.domains.customer-search'))
            ->assertOk()->assertJsonCount(20);
    }

    /** Validates: Requirements 20.6 */
    public function test_link_service_sets_domain_id(): void
    {
        $domain = Domain::factory()->create();
        $service = \App\Models\Service::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.domains.link-service', $domain), ['service_id' => $service->service_id])
            ->assertSessionHasNoErrors();

        $this->assertSame($domain->id, $service->fresh()->domain_id);

        // Relinking the same service is allowed.
        $this->actingAs($this->admin)
            ->post(route('admin.domains.link-service', $domain), ['service_id' => $service->service_id])
            ->assertSessionHasNoErrors();
    }

    /** Validates: Requirements 20.6 */
    public function test_link_service_rejects_domain_already_linked_to_another_service(): void
    {
        $domain = Domain::factory()->create();
        $first = \App\Models\Service::factory()->create(['domain_id' => $domain->id]);
        $second = \App\Models\Service::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.domains.link-service', $domain), ['service_id' => $second->service_id])
            ->assertSessionHasErrors('service_id');

        $this->assertNull($second->fresh()->domain_id);
        $this->assertSame($domain->id, $first->fresh()->domain_id);
    }

    /** Validates: Requirements 22.2 */
    public function test_restore_restores_trashed_domain(): void
    {
        $domain = Domain::factory()->create();
        $domain->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.domains.restore', $domain->id))
            ->assertRedirect(route('admin.domains.edit', $domain->id));

        $this->assertNull($domain->fresh()->deleted_at);
    }

    public function test_link_service_and_restore_are_admin_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $domain = Domain::factory()->create();
        $trashed = Domain::factory()->create();
        $trashed->delete();

        $this->actingAs($user)->post(route('admin.domains.link-service', $domain), ['service_id' => 1])->assertForbidden();
        $this->actingAs($user)->post(route('admin.domains.restore', $trashed->id))->assertForbidden();
        $this->assertTrue($trashed->fresh() === null || $trashed->fresh()->trashed());
    }

    public function test_customer_search_is_admin_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->getJson(route('admin.domains.customer-search'))->assertForbidden();
    }

    public function test_create_and_edit_render_without_loading_all_customers(): void
    {
        $domain = Domain::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.domains.create'))
            ->assertOk()->assertSee('aria-autocomplete="list"', false);
        $this->actingAs($this->admin)->get(route('admin.domains.edit', $domain))
            ->assertOk()->assertSee('aria-autocomplete="list"', false);
    }
}
