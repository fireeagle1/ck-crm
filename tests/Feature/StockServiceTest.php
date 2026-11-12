<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * _Requirements: 4.1, 4.3, 4.5, 4.6_
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(?int $stock, int $qty): array
    {
        $product = Product::factory()->create(['stock_quantity' => $stock]);
        $order = Order::factory()->create();
        $item = OrderItem::factory()->oneOff($qty)->create(['order_id' => $order->id, 'product_id' => $product->id]);

        return [$product, $order, $item];
    }

    public function test_reserve_decrements_by_full_quantity_and_records_it(): void
    {
        [$product, , $item] = $this->fixture(10, 3);

        DB::transaction(fn () => app(StockService::class)->reserve($product, 3, $item));

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(3, (int) $item->fresh()->stock_reserved_quantity);
    }

    public function test_reserve_throws_on_insufficient_stock_without_changes(): void
    {
        [$product, , $item] = $this->fixture(2, 3);

        try {
            DB::transaction(fn () => app(StockService::class)->reserve($product, 3, $item));
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertNull($item->fresh()->stock_reserved_quantity);
    }

    public function test_unlimited_stock_reserves_zero_and_never_restocks(): void
    {
        [$product, $order, $item] = $this->fixture(null, 4);

        DB::transaction(function () use ($product, $order, $item) {
            $svc = app(StockService::class);
            $svc->reserve($product, 4, $item);
            $this->assertSame(0, $svc->restoreForOrder($order));
        });

        $this->assertNull($product->fresh()->stock_quantity);
        $this->assertSame(0, (int) $item->fresh()->stock_reserved_quantity);
    }

    public function test_restore_for_order_restocks_once_when_called_twice(): void
    {
        [$product, $order, $item] = $this->fixture(10, 3);

        DB::transaction(function () use ($product, $order, $item) {
            $svc = app(StockService::class);
            $svc->reserve($product, 3, $item);
            $this->assertSame(1, $svc->restoreForOrder($order));
            $this->assertSame(0, $svc->restoreForOrder($order));
        });

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(0, (int) $item->fresh()->stock_reserved_quantity);
    }

    public function test_restore_ignores_items_without_reservation(): void
    {
        [$product, $order] = $this->fixture(5, 2);

        $restored = DB::transaction(fn () => app(StockService::class)->restoreForOrder($order));

        $this->assertSame(0, $restored);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }
}
