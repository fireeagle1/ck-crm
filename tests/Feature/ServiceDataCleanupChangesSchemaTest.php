<?php

namespace Tests\Feature;

use App\Models\ServiceDataCleanupChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema test for migration M2.
 *
 * _Requirements: 1.11, 2.4_
 */
class ServiceDataCleanupChangesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('service_data_cleanup_changes', [
            'id', 'migration', 'table_name', 'row_id', 'column_name', 'old_value', 'new_value', 'created_at',
        ]));
    }

    public function test_record_persists_change_with_nullable_values(): void
    {
        $change = ServiceDataCleanupChange::record('m3', 'services', 42, 'domain_name', null, 'example.com');

        $fresh = ServiceDataCleanupChange::findOrFail($change->id);
        $this->assertSame('m3', $fresh->migration);
        $this->assertSame('services', $fresh->table_name);
        $this->assertSame('42', $fresh->row_id);
        $this->assertNull($fresh->old_value);
        $this->assertSame('example.com', $fresh->new_value);
        $this->assertNotNull($fresh->created_at);
    }

    public function test_migration_is_reversible(): void
    {
        $m = require database_path('migrations/2026_10_30_100007_create_service_data_cleanup_changes_table.php');

        $m->down();
        $this->assertFalse(Schema::hasTable('service_data_cleanup_changes'));

        $m->up();
        $m->up();
        $this->assertTrue(Schema::hasTable('service_data_cleanup_changes'));
    }
}
