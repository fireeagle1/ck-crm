<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema test for migration M6 (services.whm_package).
 *
 * _Requirements: 8.1_
 */
class ServiceWhmPackageSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_whm_package_is_nullable_and_fillable(): void
    {
        $this->assertTrue(Schema::hasColumn('services', 'whm_package'));

        $plain = Service::factory()->create();
        $this->assertNull(DB::table('services')->where('service_id', $plain->service_id)->value('whm_package'));

        $withPkg = Service::factory()->create(['whm_package' => 'ckhostco_Basic']);
        $this->assertSame('ckhostco_Basic', $withPkg->fresh()->whm_package);
    }

    public function test_migration_is_re_runnable_and_reversible(): void
    {
        $migration = require database_path('migrations/2026_10_30_100011_add_whm_package_to_services_table.php');

        $migration->up();
        $this->assertTrue(Schema::hasColumn('services', 'whm_package'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('services', 'whm_package'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('services', 'whm_package'));
    }
}
