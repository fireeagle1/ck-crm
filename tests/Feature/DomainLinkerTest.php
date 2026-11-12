<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Domain;
use App\Services\DomainLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainLinkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_domain_is_reused(): void
    {
        $existing = Domain::factory()->create(['domain_name' => 'example.com']);
        $customer = Customer::factory()->create();

        $found = app(DomainLinker::class)->findOrCreate('example.com', $customer->company_id);

        $this->assertSame($existing->id, $found->id);
        $this->assertSame(1, Domain::count());
        $this->assertNotSame($customer->company_id, $found->company_id);
    }

    public function test_missing_domain_is_created_for_customer(): void
    {
        $customer = Customer::factory()->create();

        $domain = app(DomainLinker::class)->findOrCreate('new-site.co.uk', $customer->company_id);

        $this->assertTrue($domain->exists);
        $this->assertSame('new-site.co.uk', $domain->domain_name);
        $this->assertSame($customer->company_id, $domain->company_id);
        $this->assertNull($domain->registrar);
        if (Schema::hasColumn('domains', 'status')) {
            $this->assertSame('active', $domain->fresh()->status);
        }
    }

    public function test_trashed_domain_is_matched_and_never_restored(): void
    {
        $trashed = Domain::factory()->create(['domain_name' => 'gone.com']);
        $trashed->delete();
        $customer = Customer::factory()->create();

        $found = app(DomainLinker::class)->findOrCreate('gone.com', $customer->company_id);

        $this->assertSame($trashed->id, $found->id);
        $this->assertTrue($found->trashed());
        $this->assertSoftDeleted('domains', ['id' => $trashed->id]);
        $this->assertSame(1, Domain::withTrashed()->count());
    }

    public function test_name_variants_map_to_one_row(): void
    {
        $customer = Customer::factory()->create();
        $linker = app(DomainLinker::class);

        $a = $linker->findOrCreate('example.org', $customer->company_id);
        $b = $linker->findOrCreate('WWW.Example.org.', $customer->company_id);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Domain::count());
    }
}
