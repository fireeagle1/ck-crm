<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Services\OrderCancellationService;
use App\Services\StripeService;
use App\Support\StripeCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\FakeStripeService;
use Tests\TestCase;

/**
 * _Requirements: 4.4, 4.5, 5.3, 5.4_
 */
class OrderCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeService $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        // Migration 2026_10_30_100003 widens the order status enums on MySQL only, so the
        // SQLite test schema keeps the original CHECK constraints and would reject
        // 'expired' / fulfilment 'cancelled'. Skip CHECK enforcement for these tests.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        $this->stripe = new FakeStripeService();
        $this->app->instance(StripeService::class, $this->stripe);
    }

    private function service(): OrderCancellationService
    {
        return app(OrderCancellationService::class);
    }

    /** @return array{Order, Product, OrderItem} */
    private function orderWithStock(int $stock = 10, int $qty = 3): array
    {
        $product = Product::factory()->create(['stock_quantity' => $stock]);
        $order = Order::factory()->create();
        $item = OrderItem::factory()->oneOff($qty)->create(['order_id' => $order->id, 'product_id' => $product->id]);
        DB::transaction(fn () => app(\App\Services\StockService::class)->reserve($product, $qty, $item));

        return [$order, $product, $item];
    }

    public function test_expire_restocks_once_across_repeated_calls(): void
    {
        [$order, $product] = $this->orderWithStock(10, 3);
        $this->assertSame(7, $product->fresh()->stock_quantity);

        $this->assertTrue($this->service()->expire($order));
        $this->assertFalse($this->service()->expire($order));
        $this->assertFalse($this->service()->expire($order->fresh()));

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $order->refresh();
        $this->assertSame('expired', $order->payment_status);
        $this->assertSame('cancelled', $order->fulfilment_status);
    }

    public function test_fail_order_sets_failed_cancelled_and_restocks_once(): void
    {
        [$order, $product] = $this->orderWithStock(10, 2);

        $this->assertTrue($this->service()->failOrder($order));
        $this->assertFalse($this->service()->failOrder($order));

        $order->refresh();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('cancelled', $order->fulfilment_status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_cancel_keeps_payment_status_and_restocks_once(): void
    {
        [$order, $product] = $this->orderWithStock(10, 2);
        $order->update(['payment_status' => 'paid']);

        $this->assertTrue($this->service()->cancel($order));
        $this->assertFalse($this->service()->cancel($order));

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cancelled', $order->fulfilment_status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame([], $this->stripe->calls, 'Admin cancel must not touch Stripe');
    }

    public function test_paid_orders_are_not_failed_or_expired(): void
    {
        [$order] = $this->orderWithStock();
        $order->update(['payment_status' => 'paid']);

        $this->assertFalse($this->service()->failOrder($order));
        $this->assertFalse($this->service()->expire($order));
        $this->assertSame('pending', $order->fresh()->fulfilment_status);
    }

    public function test_bookings_are_cancelled(): void
    {
        [$order, $product, $item] = $this->orderWithStock();
        $booking = Booking::create([
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'company_id' => $order->company_id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'quantity' => 1,
            'total_price' => 10,
            'status' => 'confirmed',
        ]);

        $this->service()->expire($order);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_only_never_provisioned_pending_services_are_deleted(): void
    {
        $order = Order::factory()->create();
        $a = OrderItem::factory()->hosting('a.example.com')->create(['order_id' => $order->id]);
        $b = OrderItem::factory()->hosting('b.example.com')->create(['order_id' => $order->id]);
        $c = OrderItem::factory()->hosting('c.example.com')->create(['order_id' => $order->id]);

        $never = Service::factory()->webHosting()->pending()->create(['order_item_id' => $a->id]);
        $withCpanel = Service::factory()->webHosting()->pending()->withCpanel()->create(['order_item_id' => $b->id]);
        $active = Service::factory()->webHosting()->create(['order_item_id' => $c->id]);
        $a->update(['service_id' => $never->service_id]);

        $this->service()->failOrder($order);

        $this->assertNull(Service::find($never->service_id));
        $this->assertNull($a->fresh()->service_id);
        $this->assertSame('Cancelled', $withCpanel->fresh()->status);
        $this->assertSame('Active', $active->fresh()->status);
    }

    public function test_tracked_and_persisted_stripe_objects_are_cancelled(): void
    {
        [$order, , $item] = $this->orderWithStock();
        $order->update(['stripe_checkout_session_id' => 'cs_1']);
        $item->update(['stripe_subscription_id' => 'sub_1']);

        $created = (new StripeCreated())->withSubscription('sub_2')->withCoupon('co_1');
        $this->service()->failOrder($order, $created);

        $this->assertSame(['cs_1'], $this->stripe->cancelledIds('session'));
        $this->assertEqualsCanonicalizing(['sub_1', 'sub_2'], $this->stripe->cancelledIds('subscription'));
        $this->assertSame(0, $this->stripe->callsTo('expireCheckoutSession')[0]['transaction_level'] - $this->baseLevel());
    }

    public function test_stripe_failures_are_logged_and_noted_without_failing_cancellation(): void
    {
        [$order, $product] = $this->orderWithStock(10, 1);
        $order->update(['stripe_checkout_session_id' => 'cs_1']);

        $this->stripe->failAt('expireCheckoutSession', new HttpException(502, 'boom'));
        $this->stripe->failAt('cancelSubscription', new HttpException(502, 'boom'));
        Log::spy();

        $this->service()->failOrder($order, (new StripeCreated())->withSubscription('sub_9'));

        Log::shouldHaveReceived('error')->twice();
        $order->refresh();
        $this->assertSame('cancelled', $order->fulfilment_status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertStringContainsString('cs_1', $order->admin_notes);
        $this->assertStringContainsString('sub_9', $order->admin_notes);
    }

    /** Transaction level of the RefreshDatabase wrapper (Stripe calls must be outside our own transactions). */
    private function baseLevel(): int
    {
        return 1;
    }
}
