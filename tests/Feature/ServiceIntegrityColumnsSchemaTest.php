<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema tests for migrations M1 and M1b.
 *
 * _Requirements: 2.5, 2.6_
 */
class ServiceIntegrityColumnsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_has_integrity_columns(): void
    {
        foreach (['provisioning_status', 'provisioning_error', 'provisioning_updated_at', 'domain_id'] as $col) {
            $this->assertTrue(Schema::hasColumn('services', $col), "services.$col missing");
        }
        $this->assertTrue(Schema::hasColumn('order_items', 'domain_id'));
    }

    public function test_new_service_columns_are_nullable(): void
    {
        $service = Service::factory()->create();
        $row = DB::table('services')->where('service_id', $service->service_id)->first();

        $this->assertNull($row->provisioning_status);
        $this->assertNull($row->provisioning_error);
        $this->assertNull($row->provisioning_updated_at);
        $this->assertNull($row->domain_id);
    }

    public function test_deleting_domain_nulls_service_domain_id(): void
    {
        $domain = Domain::factory()->create();
        $service = Service::factory()->create(['domain_id' => $domain->id]);

        $domain->forceDelete(); // FK nullOnDelete only fires on a real delete; delete() now soft-deletes

        $this->assertNull($service->fresh()->domain_id);
    }

    public function test_deleting_domain_nulls_order_item_domain_id(): void
    {
        $domain = Domain::factory()->create();
        $customerId = DB::table('customers')->value('company_id') ?? \App\Models\Customer::factory()->create()->company_id;
        $orderId = DB::table('orders')->insertGetId(array_merge(
            \App\Models\Order::factory()->make(['company_id' => $customerId])->getAttributes(),
            ['created_at' => now(), 'updated_at' => now()]
        ));
        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'product_name' => 'Domain',
            'product_type' => 'domain',
            'price' => 10,
            'domain_id' => $domain->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $domain->forceDelete(); // FK nullOnDelete only fires on a real delete; delete() now soft-deletes

        $this->assertNull(DB::table('order_items')->where('id', $itemId)->value('domain_id'));
    }

    public function test_migrations_are_re_runnable_and_reversible(): void
    {
        $up = fn (string $f) => (require database_path("migrations/$f"))->up();
        $down = fn (string $f) => (require database_path("migrations/$f"))->down();
        $m1 = '2026_10_30_100005_add_integrity_columns_to_services_table.php';
        $m1b = '2026_10_30_100006_add_domain_id_to_order_items_table.php';

        $up($m1);
        $up($m1b);
        $this->assertTrue(Schema::hasColumn('services', 'domain_id'));

        $down($m1b);
        $down($m1);
        $this->assertFalse(Schema::hasColumn('services', 'domain_id'));
        $this->assertFalse(Schema::hasColumn('services', 'provisioning_status'));
        $this->assertFalse(Schema::hasColumn('order_items', 'domain_id'));

        $up($m1);
        $up($m1b);
        $this->assertTrue(Schema::hasColumn('services', 'provisioning_updated_at'));
    }
}
