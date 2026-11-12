<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\FakeStripeService;
use Tests\TestCase;

/**
 * Stripe failure after the order commit is compensated by OrderCancellationService::failOrder.
 *
 * **Validates: Requirements 5.3, 5.4**
 */
class CheckoutStripeFailureTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeService $stripe;
    private Customer $customer;
    private Product $oneOff;

    protected function setUp(): void
    {
        parent::setUp();

        // SQLite keeps the original order status CHECK constraints; 'failed' / 'cancelled'
        // are only allowed after the MySQL-only widening migration.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        $this->stripe = new FakeStripeService();
        $this->app->instance(StripeService::class, $this->stripe);

        $this->customer = Customer::factory()->create(['stripe_customer_id' => 'cus_existing']);
        $this->oneOff = Product::factory()->oneOff()->create(['stock_quantity' => 10]);
    }

    private function cartItems(int $hostingCount): array
    {
        $items = [[
            'product_id' => $this->oneOff->id,
            'name' => $this->oneOff->name,
            'price' => 20.0,
            'product_type' => 'one_off',
            'billing_frequency' => null,
            'quantity' => 2,
        ]];

        for ($i = 1; $i <= $hostingCount; $i++) {
            $hosting = Product::factory()->hosting()->create();
            $items[] = [
                'product_id' => $hosting->id,
                'name' => $hosting->name,
                'price' => 9.99,
                'product_type' => 'hosting',
                'billing_frequency' => 'monthly',
                'domain_name' => "failure-{$i}.example.com",
            ];
        }

        return $items;
    }

    private function cart(array $items): CartService
    {
        $cart = Mockery::mock(CartService::class);
        $cart->shouldReceive('getItems')->andReturn($items);
        $cart->shouldReceive('hasOnlyHostingItems')->andReturn(false);
        $cart->shouldReceive('getDeliveryTotal')->andReturn(0.0);
        $cart->shouldReceive('clear')->never();

        return $cart;
    }

    private function runCheckout(array $items, array $options = []): \App\ValueObjects\CheckoutResult
    {
        return app(CheckoutService::class)->processCheckout($this->customer, $this->cart($items), [], $options);
    }

    private function assertOrderFailedAndCleanedUp(): Order
    {
        $order = Order::where('company_id', $this->customer->company_id)->firstOrFail();

        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('cancelled', $order->fulfilment_status);
        $this->assertSame(10, $this->oneOff->fresh()->stock_quantity, 'stock restored');
        $this->assertSame(0, Service::where('status', 'Pending')->count(), 'no Pending service left');

        return $order;
    }

    public function test_coupon_failure_fails_order_and_restores_stock(): void
    {
        $this->stripe->failAt('createDiscountCoupon');

        $result = $this->runCheckout($this->cartItems(1), ['discount_code' => 'X', 'discount_amount' => 5]);

        $this->assertFalse($result->success);
        $this->assertNull($result->order);
        $this->assertSame(0, $this->stripe->callCount('createCheckoutSessionWithParams'));
        $this->assertSame(0, $this->stripe->callCount('createSubscription'));
        $this->assertSame([], $this->stripe->createdIds('subscription'));
        $this->assertOrderFailedAndCleanedUp();
    }

    public function test_session_failure_fails_order_and_restores_stock(): void
    {
        $this->stripe->failAt('createCheckoutSessionWithParams');

        $result = $this->runCheckout($this->cartItems(1));

        $this->assertFalse($result->success);
        $this->assertSame(0, $this->stripe->callCount('createSubscription'));
        $this->assertSame([], $this->stripe->createdIds('session'));
        $this->assertOrderFailedAndCleanedUp();
    }

    public function test_first_subscription_failure_expires_session(): void
    {
        $this->stripe->failAt('createSubscription');

        $result = $this->runCheckout($this->cartItems(2));

        $this->assertFalse($result->success);
        $this->assertSame($this->stripe->createdIds('session'), $this->stripe->cancelledIds('session'));
        $this->assertCount(1, $this->stripe->cancelledIds('session'));
        $this->assertSame([], $this->stripe->cancelledIds('subscription'));
        $this->assertOrderFailedAndCleanedUp();
    }

    public function test_second_subscription_failure_cancels_session_and_first_subscription(): void
    {
        $this->stripe->failAt('createSubscription', null, 1);

        $result = $this->runCheckout($this->cartItems(2));

        $this->assertFalse($result->success);
        $this->assertCount(1, $this->stripe->createdIds('subscription'));
        $this->assertSame($this->stripe->createdIds('subscription'), $this->stripe->cancelledIds('subscription'));
        $this->assertSame($this->stripe->createdIds('session'), $this->stripe->cancelledIds('session'));
        $this->assertOrderFailedAndCleanedUp();
    }

    public function test_successful_checkout_does_not_compensate(): void
    {
        $notifications = Mockery::mock(\App\Services\NotificationService::class)->shouldIgnoreMissing();
        $this->app->instance(\App\Services\NotificationService::class, $notifications);

        $cart = Mockery::mock(CartService::class);
        $cart->shouldReceive('getItems')->andReturn($this->cartItems(1));
        $cart->shouldReceive('hasOnlyHostingItems')->andReturn(false);
        $cart->shouldReceive('getDeliveryTotal')->andReturn(0.0);
        $cart->shouldReceive('clear')->once();

        $result = app(CheckoutService::class)->processCheckout($this->customer, $cart);

        $this->assertTrue($result->success);
        $this->assertSame([], $this->stripe->cancelledIds('session'));
        $this->assertSame(1, Service::where('status', 'Pending')->where('service_type', 'Web Hosting')->count());
        $this->assertSame(8, $this->oneOff->fresh()->stock_quantity);
    }
}
