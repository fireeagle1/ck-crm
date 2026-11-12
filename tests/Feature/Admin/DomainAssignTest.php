<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Validates: Requirements 20.3, 20.4, 22.2 */
class DomainAssignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    public function test_assign_changes_only_company_id(): void
    {
        $customer = Customer::factory()->create();
        $domain = Domain::factory()->create([
            'company_id' => null,
            'registrar' => 'eNom',
            'expiry_date' => '2027-01-15',
            'auto_renew' => true,
            'enom_domain_name_id' => 12345,
            'admin_edited_fields' => ['registrar'],
        ]);
        $before = $domain->fresh();

        $this->actingAs($this->admin())
            ->post(route('admin.domains.assign', $domain), ['company_id' => $customer->company_id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $after = $domain->fresh();
        $this->assertEquals($customer->company_id, $after->company_id);
        $this->assertSame($before->registrar, $after->registrar);
        $this->assertSame($before->expiry_date->toDateString(), $after->expiry_date->toDateString());
        $this->assertSame($before->auto_renew, $after->auto_renew);
        $this->assertSame($before->enom_domain_name_id, $after->enom_domain_name_id);
        $this->assertSame(['registrar'], $after->admin_edited_fields);
    }

    public function test_assign_rejects_unknown_customer(): void
    {
        $domain = Domain::factory()->create(['company_id' => null]);

        $this->actingAs($this->admin())
            ->post(route('admin.domains.assign', $domain), ['company_id' => 99999999])
            ->assertSessionHasErrors('company_id');

        $this->assertNull($domain->fresh()->company_id);
    }

    public function test_assign_is_admin_only(): void
    {
        $customer = Customer::factory()->create();
        $domain = Domain::factory()->create(['company_id' => null]);
        $payload = ['company_id' => $customer->company_id];

        $this->post(route('admin.domains.assign', $domain), $payload)->assertRedirect();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post(route('admin.domains.assign', $domain), $payload)->assertForbidden();

        $this->assertNull($domain->fresh()->company_id);
    }

    public function test_unassigned_rows_render_inline_picker(): void
    {
        Domain::factory()->create(['company_id' => null, 'expiry_date' => now()->addDays(100)]);

        $this->actingAs($this->admin())
            ->get(route('admin.domains.index', ['filter' => 'unassigned']))
            ->assertOk()
            ->assertSee('aria-autocomplete="list"', false);
    }
}
