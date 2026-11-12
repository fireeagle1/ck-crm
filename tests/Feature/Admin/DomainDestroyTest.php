<?php

namespace Tests\Feature\Admin;

use App\Models\Domain;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_unlinked_domain_is_soft_deleted(): void
    {
        $domain = Domain::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.domains.destroy', $domain))
            ->assertRedirect(route('admin.domains.index'))
            ->assertSessionHas('success');

        $this->assertNull(Domain::find($domain->id));
        $this->assertNotNull(Domain::withTrashed()->find($domain->id)?->deleted_at);
    }

    public function test_linked_domain_is_refused_and_names_service(): void
    {
        $domain = Domain::factory()->create();
        $service = Service::factory()->create([
            'domain_id' => $domain->id,
            'domain_name' => $domain->domain_name,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.domains.destroy', $domain))
            ->assertSessionHas('error', fn ($m) => str_contains($m, (string) $service->service_short));

        $this->assertNotNull(Domain::find($domain->id));
    }
}
