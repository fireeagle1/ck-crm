<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Services\StockService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeStripeService;
use Tests\TestCase;

/**
 * _Requirements: 4.4, 4.5, 4.6_
 */
class CleanupAbandonedOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SQLite keeps the original CHECK constraints; 'expired' / 'cancelled' would be rejected.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        $this->app->instance(StripeService::class, new FakeStripeService());
    }

    private function orderWithStock(Product $product, int $qty, array $attrs = [], int $ageHours = 3): Order
    {
        $order = Order::factory()->create($attrs + ['payment_status' => 'pending']);
        $order->forceFill(['created_at' => now()->subHours($ageHours)])->saveQuietly();
        $item = OrderItem::factory()->oneOff($qty)->create(['order_id' => $order->id, 'product_id' => $product->id]);
        DB::transaction(fn () => app(StockService::class)->reserve($product, $qty, $item));

        return $order;
    }

    public function test_stock_restored_once_across_repeated_runs(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = $this->orderWithStock($product, 3, ['stripe_checkout_session_id' => 'cs_1']);
        $this->assertSame(7, $product->fresh()->stock_quantity);

        $this->artisan('app:cleanup-abandoned-orders')->assertSuccessful();
        $this->artisan('app:cleanup-abandoned-orders')->assertSuccessful();
        $this->artisan('app:cleanup-abandoned-orders')->assertSuccessful();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->fulfilment_status);
    }

    public function test_hosting_only_orders_expire_after_configured_hours(): void
    {
        config(['shop.abandoned_hosting_hours' => 48]);

        $old = Order::factory()->create(['payment_status' => 'pending']);
        $old->forceFill(['created_at' => now()->subHours(49)])->saveQuietly();
        $recent = Order::factory()->create(['payment_status' => 'pending']);
        $recent->forceFill(['created_at' => now()->subHours(10)])->saveQuietly();

        $oldItem = OrderItem::factory()->hosting('old.example.com')->create(['order_id' => $old->id]);
        $recentItem = OrderItem::factory()->hosting('recent.example.com')->create(['order_id' => $recent->id]);
        $oldService = Service::factory()->webHosting()->pending()->create(['order_item_id' => $oldItem->id]);
        $oldItem2 = OrderItem::factory()->hosting('old2.example.com')->create(['order_id' => $old->id]);
        $provisioned = Service::factory()->webHosting()->pending()->withCpanel()->create(['order_item_id' => $oldItem2->id]);
        $recentService = Service::factory()->webHosting()->pending()->create(['order_item_id' => $recentItem->id]);

        $this->artisan('app:cleanup-abandoned-orders')->assertSuccessful();

        $this->assertSame('expired', $old->fresh()->payment_status);
        $this->assertNull(Service::find($oldService->service_id));
        $this->assertSame('Cancelled', $provisioned->fresh()->status);

        $this->assertSame('pending', $recent->fresh()->payment_status);
        $this->assertNotNull(Service::find($recentService->service_id));
    }

    public function test_paid_orders_are_untouched(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = $this->orderWithStock($product, 2, ['stripe_checkout_session_id' => 'cs_1'], 100);
        $order->update(['payment_status' => 'paid']);

        $noSession = $this->orderWithStock($product, 1, [], 100);
        $noSession->update(['payment_status' => 'paid']);

        $this->artisan('app:cleanup-abandoned-orders')->assertSuccessful();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->fulfilment_status);
        $this->assertSame('paid', $noSession->fresh()->payment_status);
        $this->assertSame(7, $product->fresh()->stock_quantity);
    }
}
