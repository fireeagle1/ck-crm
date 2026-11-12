<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ShopOrderController as WebController;
use App\Http\Controllers\Api\Admin\ShopOrderController as ApiController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin cancel (web + API) goes through OrderCancellationService.
 *
 * **Validates: Requirements 4.4, 4.5, 1.8**
 */
class AdminShopOrderCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SQLite test schema keeps the original CHECK constraints; fulfilment 'cancelled' needs them off.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    /** @return array{Order, Product} */
    private function reservedOrder(int $stock = 10, int $qty = 3): array
    {
        $product = Product::factory()->create(['stock_quantity' => $stock]);
        $order = Order::factory()->create();
        $item = OrderItem::factory()->oneOff($qty)->create(['order_id' => $order->id, 'product_id' => $product->id]);
        DB::transaction(fn () => app(StockService::class)->reserve($product, $qty, $item));

        return [$order, $product];
    }

    public function test_web_cancel_restores_stock_once_and_second_cancel_is_safe(): void
    {
        [$order, $product] = $this->reservedOrder(10, 3);
        $this->assertSame(7, $product->fresh()->stock_quantity);

        app()->call([app(WebController::class), 'cancel'], ['order' => $order]);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('cancelled', $order->fresh()->fulfilment_status);

        $response = app()->call([app(WebController::class), 'cancel'], ['order' => $order->fresh()]);
        $this->assertSame('This order is already cancelled.', session('error'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_api_cancel_restores_stock_once_and_second_cancel_returns_422(): void
    {
        [$order, $product] = $this->reservedOrder(10, 2);

        $first = app()->call([app(ApiController::class), 'cancel'], ['order' => $order]);
        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $second = app()->call([app(ApiController::class), 'cancel'], ['order' => $order->fresh()]);
        $this->assertSame(422, $second->getStatusCode());
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_legacy_order_without_reserved_quantity_is_not_restocked(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $order = Order::factory()->create();
        OrderItem::factory()->oneOff(4)->create(['order_id' => $order->id, 'product_id' => $product->id]);

        app()->call([app(WebController::class), 'cancel'], ['order' => $order]);

        $this->assertSame('cancelled', $order->fresh()->fulfilment_status);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }
}
