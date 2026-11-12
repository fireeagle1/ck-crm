<?php

namespace Tests\Feature;

use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema test for migration M7 (domains sync columns and soft deletes).
 *
 * _Requirements: 13.1, 15.3_
 */
class DomainSyncColumnsSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['status', 'enom_domain_name_id', 'last_synced_at', 'admin_edited_fields', 'deleted_at'];

    public function test_columns_exist_with_expected_defaults(): void
    {
        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('domains', $column), $column);
        }

        $domain = Domain::factory()->create();
        $row = DB::table('domains')->where('id', $domain->id)->first();
        $this->assertSame('active', $row->status);
        $this->assertNull($row->enom_domain_name_id);
        $this->assertNull($row->last_synced_at);
        $this->assertNull($row->admin_edited_fields);
        $this->assertNull($row->deleted_at);
    }

    public function test_status_and_enom_id_are_indexed(): void
    {
        $indexed = collect(Schema::getIndexes('domains'))->pluck('columns')->map(fn ($c) => $c[0] ?? null);
        $this->assertTrue($indexed->contains('status'));
        $this->assertTrue($indexed->contains('enom_domain_name_id'));
    }

    public function test_migration_is_re_runnable_and_reversible(): void
    {
        $migration = require database_path('migrations/2026_10_30_100012_add_sync_columns_and_soft_deletes_to_domains_table.php');

        $migration->up();
        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('domains', $column), $column);
        }

        $migration->down();
        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('domains', $column), $column);
        }

        $migration->up();
        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('domains', $column), $column);
        }
    }
}
