<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServiceDataCleanupChange;
use App\Services\DataCleanup\DuplicateValuesException;
use App\Services\DataCleanup\ServiceDomainBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M7b: DomainNameID backfill and unique(services.domain_id).
 *
 * _Requirements: 12.1, 13.2_
 */
class EnomIdBackfillAndUniqueDomainIdMigrationTest extends TestCase
{
    use RefreshDatabase;

    private int|string|null $companyId = null;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_30_100013_backfill_enom_ids_and_unique_services_domain_id.php');
    }

    private function domain(string $name, ?string $response, ?string $enomId = null): int
    {
        return DB::table('domains')->insertGetId([
            'domain_name' => $name,
            'enom_response' => $response,
            'enom_domain_name_id' => $enomId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function service(?int $domainId): int
    {
        return DB::table('services')->insertGetId([
            'company_id' => $this->companyId ??= Customer::factory()->create()->getKey(),
            'service_short' => 'x',
            'domain_id' => $domainId,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'service_id');
    }

    private function enomId(int $domainId): ?string
    {
        return DB::table('domains')->where('id', $domainId)->value('enom_domain_name_id');
    }

    public function test_unique_index_exists_after_migrations(): void
    {
        $this->assertTrue(Schema::hasIndex('services', ['domain_id'], 'unique'));
    }

    public function test_copies_valid_ids_and_ignores_malformed_empty_and_missing(): void
    {
        $valid = $this->domain('valid.com', json_encode(['DomainNameID' => 12345]));
        $string = $this->domain('string.com', json_encode(['DomainNameID' => ' 678 ']));
        $malformed = $this->domain('bad.com', '{not json');
        $empty = $this->domain('empty.com', '');
        $null = $this->domain('null.com', null);
        $missing = $this->domain('missing.com', json_encode(['other' => 1]));
        $blank = $this->domain('blank.com', json_encode(['DomainNameID' => '']));
        $nested = $this->domain('nested.com', json_encode(['DomainNameID' => ['x']]));

        $this->migration()->up();

        $this->assertSame('12345', $this->enomId($valid));
        $this->assertSame('678', $this->enomId($string));
        foreach ([$malformed, $empty, $null, $missing, $blank, $nested] as $id) {
            $this->assertNull($this->enomId($id));
        }
    }

    public function test_existing_value_is_not_overwritten_and_run_is_idempotent(): void
    {
        $kept = $this->domain('kept.com', json_encode(['DomainNameID' => 111]), '999');
        $fresh = $this->domain('fresh.com', json_encode(['DomainNameID' => 222]));

        $m = $this->migration();
        $m->up();
        $this->assertSame('999', $this->enomId($kept));
        $this->assertSame('222', $this->enomId($fresh));
        $this->assertSame(1, ServiceDataCleanupChange::where('migration', ServiceDomainBackfill::MIGRATION_ENOM)->count());

        $m->up();
        $this->assertSame('222', $this->enomId($fresh));
        $this->assertSame(1, ServiceDataCleanupChange::where('migration', ServiceDomainBackfill::MIGRATION_ENOM)->count());
        $this->assertTrue(Schema::hasIndex('services', ['domain_id'], 'unique'));
    }

    public function test_down_drops_index_and_reverts_backfill(): void
    {
        $id = $this->domain('rev.com', json_encode(['DomainNameID' => 333]));
        $m = $this->migration();
        $m->up();
        $this->assertSame('333', $this->enomId($id));

        $m->down();
        $this->assertFalse(Schema::hasIndex('services', ['domain_id'], 'unique'));
        $this->assertNull($this->enomId($id));

        $m->up();
        $this->assertTrue(Schema::hasIndex('services', ['domain_id'], 'unique'));
    }

    public function test_duplicate_domain_ids_abort_with_log_and_create_no_index(): void
    {
        $this->migration()->down();
        $d = $this->domain('dup.com', null);
        $a = $this->service($d);
        $b = $this->service($d);
        $this->service(null);
        $this->service(null);

        Log::spy();

        try {
            $this->migration()->up();
            $this->fail('Expected DuplicateValuesException');
        } catch (DuplicateValuesException $e) {
            $this->assertCount(1, $e->duplicates);
            $this->assertSame('domain_id', $e->duplicates[0]['column']);
            $this->assertSame([$a, $b], $e->duplicates[0]['ids']);
        }

        Log::shouldHaveReceived('error')->once();
        $this->assertFalse(Schema::hasIndex('services', ['domain_id'], 'unique'));
    }
}
