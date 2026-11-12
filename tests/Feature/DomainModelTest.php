<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_and_domain_relations(): void
    {
        $domain = Domain::factory()->create();
        $service = Service::factory()->create(['domain_id' => $domain->id]);

        $this->assertTrue($domain->services->contains('service_id', $service->service_id));
        $this->assertSame($domain->id, $service->domain->id);
    }

    public function test_casts_and_enom_fields(): void
    {
        $domain = Domain::factory()->create(['admin_edited_fields' => ['expiry_date'], 'last_synced_at' => now()]);
        $domain = $domain->fresh();

        $this->assertSame(['expiry_date'], $domain->admin_edited_fields);
        $this->assertNotNull($domain->last_synced_at);
        $this->assertSame(['expiry_date', 'auto_renew', 'registrar', 'enom_domain_name_id'], Domain::ENOM_FIELDS);
    }

    public function test_date_scopes(): void
    {
        $soon = Domain::factory()->create(['expiry_date' => now()->addDays(10)]);
        $later = Domain::factory()->create(['expiry_date' => now()->addDays(90)]);
        $recent = Domain::factory()->create(['expiry_date' => now()->subDays(10)]);
        $ancient = Domain::factory()->create(['expiry_date' => now()->subYears(3)]);
        $none = Domain::factory()->create(['expiry_date' => null]);

        $this->assertEqualsCanonicalizing([$soon->id], Domain::expiring()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$recent->id], Domain::expired()->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$soon->id, $later->id, $recent->id, $none->id],
            Domain::visible()->pluck('id')->all()
        );
        $this->assertNotContains($ancient->id, Domain::visible()->pluck('id')->all());
    }

    public function test_enom_unassigned_and_missing_scopes(): void
    {
        $enomById = Domain::factory()->create(['enom_domain_name_id' => '123', 'registrar' => null]);
        $enomByRegistrar = Domain::factory()->create(['registrar' => 'eNom', 'enom_domain_name_id' => null]);
        $manual = Domain::factory()->create(['registrar' => 'Other', 'enom_domain_name_id' => null]);
        $unassigned = Domain::factory()->create(['company_id' => null]);
        $missing = Domain::factory()->create(['status' => 'missing_from_enom']);

        $enom = Domain::enomSourced()->pluck('id')->all();
        $this->assertContains($enomById->id, $enom);
        $this->assertContains($enomByRegistrar->id, $enom);
        $this->assertNotContains($manual->id, $enom);
        $this->assertContains($unassigned->id, Domain::unassigned()->pluck('id')->all());
        $this->assertSame([$missing->id], Domain::missingFromEnom()->pluck('id')->all());
    }

    public function test_service_domain_name_reads_through_to_domain(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'linked.com']);
        $linked = Service::factory()->create(['domain_id' => $domain->id, 'domain_name' => 'stale.com']);
        $unlinked = Service::factory()->create(['domain_id' => null, 'domain_name' => 'plain.com']);

        $this->assertSame('linked.com', $linked->domainName());
        $this->assertSame('plain.com', $unlinked->domainName());
    }

    public function test_rename_updates_linked_services(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'old.com']);
        $a = Service::factory()->create(['domain_id' => $domain->id, 'domain_name' => 'old.com']);
        $other = Service::factory()->create(['domain_id' => null, 'domain_name' => 'untouched.com']);

        $domain->update(['domain_name' => 'new.com']);

        $this->assertSame('new.com', $a->fresh()->domain_name);
        $this->assertSame('untouched.com', $other->fresh()->domain_name);
    }

    public function test_non_name_update_leaves_services_alone(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'keep.com']);
        $service = Service::factory()->create(['domain_id' => $domain->id, 'domain_name' => 'drifted.com']);

        $domain->update(['registrar' => 'eNom']);

        $this->assertSame('drifted.com', $service->fresh()->domain_name);
    }
}
