<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\DataCleanup\DuplicateValuesException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * _Requirements: 3.1, 3.2_
 */
class OrderItemLinkMigrationTest extends TestCase
{
    use RefreshDatabase;

    private int|string|null $companyId = null;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_30_100010_add_order_item_link_and_stock_reservation.php');
    }

    private function service(array $attrs = []): int
    {
        return DB::table('services')->insertGetId($attrs + [
            'company_id' => $this->companyId ??= Customer::factory()->create()->getKey(),
            'service_short' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'service_id');
    }

    public function test_columns_and_unique_index_exist_after_migrations(): void
    {
        $this->assertTrue(Schema::hasColumn('services', 'order_item_id'));
        $this->assertTrue(Schema::hasColumn('order_items', 'stock_reserved_quantity'));
        $this->assertTrue(Schema::hasIndex('services', ['order_item_id'], 'unique'));
    }

    public function test_unique_index_allows_multiple_nulls_but_rejects_duplicates(): void
    {
        $item = OrderItem::factory()->create();
        $this->service(['order_item_id' => null]);
        $this->service(['order_item_id' => null]);
        $this->service(['order_item_id' => $item->id]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->service(['order_item_id' => $item->id]);
    }

    public function test_duplicate_order_item_ids_abort_the_migration(): void
    {
        $m = $this->migration();
        $m->down();

        $item = OrderItem::factory()->create();
        // Simulate a pre-existing column without the unique index.
        \Illuminate\Support\Facades\Schema::table('services', fn ($t) => $t->unsignedBigInteger('order_item_id')->nullable());
        $a = $this->service(['order_item_id' => $item->id]);
        $b = $this->service(['order_item_id' => $item->id]);

        try {
            $m->up();
            $this->fail('Expected DuplicateValuesException');
        } catch (DuplicateValuesException $e) {
            $this->assertSame([$a, $b], $e->duplicates[0]['ids']);
        }

        $this->assertFalse(Schema::hasIndex('services', ['order_item_id'], 'unique'));
    }

    public function test_backfill_links_services_and_reserves_stock_only_for_pending_one_offs_and_is_idempotent(): void
    {
        $m = $this->migration();
        $m->down();
        $m->up(); // restore schema; backfill is exercised below on re-run

        $tracked = Product::factory()->create(['stock_quantity' => 10]);
        $unlimited = Product::factory()->create(['stock_quantity' => null]);

        $pending = Order::factory()->create();
        $paid = Order::factory()->paid()->create();

        $svcId = $this->service();
        $linked = OrderItem::factory()->hosting()->create(['order_id' => $pending->id, 'service_id' => $svcId]);
        $a = OrderItem::factory()->oneOff(3)->create(['order_id' => $pending->id, 'product_id' => $tracked->id]);
        $b = OrderItem::factory()->oneOff(2)->create(['order_id' => $pending->id, 'product_id' => $unlimited->id]);
        $c = OrderItem::factory()->oneOff(4)->create(['order_id' => $paid->id, 'product_id' => $tracked->id]);

        $m->up();
        $m->up();

        $this->assertSame($linked->id, (int) DB::table('services')->where('service_id', $svcId)->value('order_item_id'));
        $this->assertSame(3, (int) $a->fresh()->stock_reserved_quantity);
        $this->assertSame(0, (int) $b->fresh()->stock_reserved_quantity);
        $this->assertNull($c->fresh()->stock_reserved_quantity);
        $this->assertNull($linked->fresh()->stock_reserved_quantity);
    }

    public function test_down_removes_columns_and_up_is_reversible(): void
    {
        $m = $this->migration();
        $m->down();
        $this->assertFalse(Schema::hasColumn('services', 'order_item_id'));
        $this->assertFalse(Schema::hasColumn('order_items', 'stock_reserved_quantity'));

        $m->up();
        $this->assertTrue(Schema::hasIndex('services', ['order_item_id'], 'unique'));
    }
}
