<?php

namespace Tests\Feature\Admin;

use App\Models\Domain;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Validates: Requirements 20.5, 22.2 */
class DomainResetEditsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    public function test_edit_page_shows_sync_details(): void
    {
        $domain = Domain::factory()->create([
            'last_synced_at' => '2026-03-04 10:30:00',
            'admin_edited_fields' => ['expiry_date', 'registrar'],
        ]);
        $service = Service::factory()->webHosting()->create(['domain_id' => $domain->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.domains.edit', $domain))
            ->assertOk()
            ->assertSee('04 Mar 2026 10:30')
            ->assertSee(route('admin.services.show', $service), false)
            ->assertSee('Edited: expiry date')
            ->assertSee('Edited: registrar')
            ->assertSee('Reset to eNom control');
    }

    public function test_reset_clears_flags(): void
    {
        $domain = Domain::factory()->create(['admin_edited_fields' => ['expiry_date']]);

        $this->actingAs($this->admin())
            ->post(route('admin.domains.reset-edits', $domain))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEmpty($domain->fresh()->admin_edited_fields);
    }

    public function test_non_admin_is_denied(): void
    {
        $domain = Domain::factory()->create(['admin_edited_fields' => ['expiry_date']]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('admin.domains.reset-edits', $domain))->assertForbidden();

        $this->assertSame(['expiry_date'], $domain->fresh()->admin_edited_fields);
    }
}
