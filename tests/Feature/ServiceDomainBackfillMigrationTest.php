<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceDataCleanupChange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * _Requirements: 1.9, 1.10, 1.11, 2.3, 11.9, 12.1_
 */
class ServiceDomainBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_30_100008_backfill_services_domains_data.php');
    }

    private function snapshot(): array
    {
        return [
            DB::table('domains')->orderBy('id')->get()->toArray(),
            DB::table('services')->orderBy('service_id')->get()->toArray(),
            DB::table('order_items')->orderBy('id')->get()->toArray(),
        ];
    }

    public function test_up_down_up_restores_original_values(): void
    {
        Domain::factory()->create(['domain_name' => 'WWW.Example.com.']);
        Service::factory()->create([
            'domain_name' => ' Example.COM ',
            'cpanel_username' => ' UserA ',
            'service_type' => 'hosting',
            'status' => 'pending',
        ]);
        Service::factory()->create(['domain_name' => 'not a domain!', 'status' => 'weird-status']);
        OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'domain_name' => 'EXAMPLE.com']);

        $original = $this->snapshot();
        $m = $this->migration();

        $m->up();
        $migrated = $this->snapshot();
        $this->assertNotEquals($original, $migrated);
        $this->assertGreaterThan(0, ServiceDataCleanupChange::count());

        $m->down();
        $this->assertEquals($original, $this->snapshot());
        $this->assertSame(0, ServiceDataCleanupChange::count());

        $m->up();
        $this->assertEquals($migrated, $this->snapshot());
    }
}
