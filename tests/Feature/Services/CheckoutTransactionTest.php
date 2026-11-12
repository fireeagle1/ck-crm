<?php

namespace Tests\Feature\Services;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Services\AssetAssignmentService;
use App\Services\BookingService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\HostingServiceCreator;
use App\Services\NotificationService;
use App\Services\OrderCancellationService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\FakeStripeService;
use Tests\TestCase;

/**
 * Task 4.10: one local transaction, then Stripe calls outside any transaction.
 *
 * **Validates: Requirements 1.6, 4.1, 5.1, 5.2, 5.6, 11.8**
 */
class CheckoutTransactionTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeService $stripe;
    private CheckoutService $checkout;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/portal/orders', fn () => '')->name('portal.orders.index');
        $this->app['router']->get('/portal/cart', fn () => '')->name('portal.cart.index');
        $this->app['router']->getRoutes()->refreshNameLookups();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifyAdminNewOrder');

        $this->stripe = new FakeStripeService();
        $this->checkout = new CheckoutService(
            $this->stripe,
            Mockery::mock(BookingService::class),
            $notifications,
            Mockery::mock(AssetAssignmentService::class),
            $this->app->make(StockService::class),
            $this->app->make(HostingServiceCreator::class),
            Mockery::mock(OrderCancellationService::class),
        );

        $this->customer = Customer::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function cart(array $items): CartService
    {
        $cart = Mockery::mock(CartService::class);
        $cart->shouldReceive('getItems')->andReturn($items);
        $cart->shouldReceive('hasOnlyHostingItems')->andReturn(
            count(array_filter($items, fn ($i) => $i['product_type'] !== 'hosting')) === 0
        );
        $cart->shouldReceive('getDeliveryTotal')->andReturn(5.0);
        $cart->shouldReceive('clear');

        return $cart;
    }

    private function oneOffItem(Product $p, int $qty): array
    {
        return [
            'product_id' => $p->id, 'name' => $p->name, 'price' => 10.0, 'product_type' => 'one_off',
            'billing_frequency' => null, 'quantity' => $qty, 'total_price' => 10.0 * $qty,
        ];
    }

    private function hostingItem(Product $p, string $domain): array
    {
        return [
            'product_id' => $p->id, 'name' => $p->name, 'price' => 9.99, 'product_type' => 'hosting',
            'billing_frequency' => 'monthly', 'quantity' => 1, 'domain_name' => $domain,
            'stripe_price_id' => 'price_x',
        ];
    }

    private function address(): array
    {
        return ['address_line1' => '1 High St', 'city' => 'London', 'postal_code' => 'SW1A 1AA', 'country' => 'GB'];
    }

    public function test_every_stripe_call_runs_outside_a_transaction_and_ids_are_stored(): void
    {
        config(['services.stripe.currency' => 'eur']);
        // RefreshDatabase wraps each test in one transaction; "level 0" means the test's baseline level.
        $baseline = \Illuminate\Support\Facades\DB::transactionLevel();
        $oneOff = Product::factory()->oneOff()->create(['stock_quantity' => 20]);
        $hosting = Product::factory()->hosting()->create();

        $result = $this->checkout->processCheckout(
            $this->customer,
            $this->cart([$this->oneOffItem($oneOff, 3), $this->hostingItem($hosting, 'WWW.Example.COM.')]),
            $this->address(),
            ['discount_amount' => 2.5, 'discount_code' => 'X'],
        );

        $this->assertTrue($result->success, (string) $result->errorMessage);
        $this->assertNotEmpty($this->stripe->calls);
        $this->assertSame([$baseline], array_values(array_unique($this->stripe->transactionLevels())));

        foreach (['ensureCustomer', 'createDiscountCoupon', 'createCheckoutSessionWithParams', 'createSubscription'] as $m) {
            $this->assertSame(1, $this->stripe->callCount($m), $m);
        }

        $order = Order::with('items')->findOrFail($result->order->id);
        $this->assertSame($this->stripe->createdIds('session')[0], $order->stripe_checkout_session_id);

        $params = $this->stripe->callsTo('createCheckoutSessionWithParams')[0]['args']['params'];
        $this->assertSame((string) $order->id, $params['client_reference_id']);
        $this->assertSame((string) $order->id, $params['metadata']['order_id']);
        $this->assertSame($this->stripe->createdIds('coupon')[0], $params['discounts'][0]['coupon']);

        $this->assertSame('eur', $this->stripe->callsTo('createDiscountCoupon')[0]['args']['params']['currency']);
        foreach ($params['line_items'] as $line) {
            $this->assertSame('eur', $line['price_data']['currency']);
        }

        $hostItem = $order->items->firstWhere('product_type', 'hosting');
        $this->assertSame('example.com', $hostItem->domain_name);
        $this->assertSame($this->stripe->createdIds('subscription')[0], $hostItem->stripe_subscription_id);
        $meta = $this->stripe->callsTo('createSubscription')[0]['args']['metadata'];
        $this->assertSame('example.com', $meta['domain_name']);
    }

    public function test_currency_defaults_to_gbp(): void
    {
        $oneOff = Product::factory()->oneOff()->create(['stock_quantity' => null]);

        $this->checkout->processCheckout($this->customer, $this->cart([$this->oneOffItem($oneOff, 1)]), $this->address());

        $params = $this->stripe->callsTo('createCheckoutSessionWithParams')[0]['args']['params'];
        foreach ($params['line_items'] as $line) {
            $this->assertSame('gbp', $line['price_data']['currency']);
        }
    }

    public function test_hosting_service_and_stock_effects_with_quantity_above_one(): void
    {
        $oneOff = Product::factory()->oneOff()->create(['stock_quantity' => 10]);
        $hosting = Product::factory()->hosting()->create();

        $result = $this->checkout->processCheckout(
            $this->customer,
            $this->cart([$this->oneOffItem($oneOff, 4), $this->hostingItem($hosting, 'Shop-Test.co.uk')]),
            $this->address(),
        );

        $this->assertTrue($result->success, (string) $result->errorMessage);
        $this->assertSame(6, $oneOff->fresh()->stock_quantity);

        $order = Order::with('items')->findOrFail($result->order->id);
        $this->assertSame(4, $order->items->firstWhere('product_type', 'one_off')->stock_reserved_quantity);

        $service = Service::where('order_item_id', $order->items->firstWhere('product_type', 'hosting')->id)->first();
        $this->assertNotNull($service);
        $this->assertSame(ServiceType::WebHosting->value, $service->service_type);
        $this->assertSame(ServiceStatus::Pending->value, $service->status);
        $this->assertSame('shop-test.co.uk', $service->domain_name);
        $this->assertSame(1, Service::count());
    }

    public function test_insufficient_stock_rolls_back_and_makes_no_stripe_create_calls(): void
    {
        $oneOff = Product::factory()->oneOff()->create(['stock_quantity' => 2]);

        $result = $this->checkout->processCheckout($this->customer, $this->cart([$this->oneOffItem($oneOff, 5)]), $this->address());

        $this->assertFalse($result->success);
        $this->assertSame(2, $oneOff->fresh()->stock_quantity);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $this->stripe->callCount('createCheckoutSessionWithParams'));
    }
}
