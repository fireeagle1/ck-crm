<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Services\AssetAssignmentService;
use App\Services\BookingService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\HostingServiceCreator;
use App\Services\NotificationService;
use App\Services\OrderCancellationService;
use App\Services\StockService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

/**
 * Unit tests for CheckoutService item partitioning logic.
 *
 * **Validates: Requirements 4.4, 4.5, 4.6**
 *
 * Property 9: Checkout Correctly Partitions Items by Payment Type
 * For any Cart containing a mix of product types, one-off items SHALL be grouped
 * into a single Stripe Checkout Session, and each recurring item (hosting or
 * equipment_rental) SHALL result in an individual Stripe Subscription.
 */
class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $stripeService;
    private MockInterface $bookingService;
    private MockInterface $notificationService;
    private CheckoutService $checkoutService;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Register stub routes needed by CheckoutService internally
        $this->app['router']->get('/portal/orders', fn () => '')->name('portal.orders.index');
        $this->app['router']->get('/portal/cart', fn () => '')->name('portal.cart.index');
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->stripeService = Mockery::mock(StripeService::class);
        $this->bookingService = Mockery::mock(BookingService::class);
        $this->notificationService = Mockery::mock(NotificationService::class);
        $this->checkoutService = $this->makeCheckoutService(
            $this->stripeService,
            $this->bookingService,
            $this->notificationService,
        );

        // Create a customer with a stripe_customer_id
        $this->customer = Customer::factory()->create([
            'stripe_customer_id' => 'cus_test_123',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Helper Methods ──────────────────────────────────────────────────

    private function makeCheckoutService(
        StripeService $stripe,
        BookingService $booking,
        NotificationService $notification,
    ): CheckoutService {
        $assetAssignment = Mockery::mock(AssetAssignmentService::class);
        $assetAssignment->shouldReceive('autoAssign')->andReturn(0);

        return new CheckoutService(
            $stripe,
            $booking,
            $notification,
            $assetAssignment,
            $this->app->make(StockService::class),
            $this->app->make(HostingServiceCreator::class),
            Mockery::mock(OrderCancellationService::class),
        );
    }

    /**
     * Build a stubbed CartService returning the given items.
     */
    private function cartFor(array $items): CartService
    {
        $hasOnlyHosting = !empty($items)
            && count(array_filter($items, fn ($i) => $i['product_type'] !== 'hosting')) === 0;

        $cart = Mockery::mock(CartService::class);
        $cart->shouldReceive('getItems')->andReturn($items);
        $cart->shouldReceive('hasOnlyHostingItems')->andReturn($hasOnlyHosting);
        $cart->shouldReceive('getDeliveryTotal')->andReturn(0.0);
        $cart->shouldReceive('clear');

        return $cart;
    }

    private function checkout(CheckoutService $service, array $items): \App\ValueObjects\CheckoutResult
    {
        return $service->processCheckout($this->customer, $this->cartFor($items));
    }

    private function createOneOffProduct(string $name = 'Widget', float $price = 29.99): Product
    {
        return Product::factory()->oneOff()->create([
            'name' => $name,
            'price' => $price,
        ]);
    }

    private function createHostingProduct(string $name = 'Basic Hosting', float $price = 9.99): Product
    {
        return Product::factory()->hosting()->create([
            'name' => $name,
            'price' => $price,
        ]);
    }

    private function createEquipmentRentalProduct(string $name = 'Router Rental', float $price = 15.00): Product
    {
        return Product::factory()->equipmentRental()->create([
            'name' => $name,
            'price' => $price,
        ]);
    }

    private function productToCartItem(Product $product, ?string $stripePriceId = null): array
    {
        $item = [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'product_type' => $product->product_type,
            'billing_frequency' => $product->billing_frequency,
        ];

        if ($product->product_type === 'hosting') {
            $item['domain_name'] = 'www.Example' . $product->id . '.com';
        }

        if ($stripePriceId) {
            $item['stripe_price_id'] = $stripePriceId;
        } elseif (in_array($product->product_type, ['hosting', 'equipment_rental'])) {
            $item['stripe_price_id'] = 'price_' . $product->product_type . '_' . $product->id;
        }

        return $item;
    }

    private function makeFakeStripeSession(string $id = 'cs_test_abc', string $url = 'https://checkout.stripe.com/test'): StripeSession
    {
        return StripeSession::constructFrom(['id' => $id, 'url' => $url]);
    }

    private function makeFakeStripeSubscription(string $id = 'sub_test_123'): StripeSubscription
    {
        return StripeSubscription::constructFrom(['id' => $id]);
    }

    // ─── Test Cases ──────────────────────────────────────────────────────

    /**
     * Test 1: Cart with only one-off items calls createCheckoutSession but NOT createSubscription.
     *
     * **Validates: Requirements 4.4**
     */
    public function test_only_one_off_items_creates_checkout_session_not_subscription(): void
    {
        $productA = $this->createOneOffProduct('Widget A', 19.99);
        $productB = $this->createOneOffProduct('Widget B', 39.99);

        $cartItems = [
            $this->productToCartItem($productA),
            $this->productToCartItem($productB),
        ];

        $this->stripeService
            ->shouldReceive('ensureCustomer')
            ->once()
            ->with($this->customer)
            ->andReturn('cus_test_123');

        $this->stripeService
            ->shouldReceive('createCheckoutSessionWithParams')
            ->once()
            ->withArgs(function (array $params) {
                return $params['customer'] === 'cus_test_123'
                    && count($params['line_items']) === 2;
            })
            ->andReturn($this->makeFakeStripeSession());

        $this->stripeService
            ->shouldNotReceive('createSubscription');

        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkout($this->checkoutService, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertEquals('https://checkout.stripe.com/test', $result->checkoutSessionUrl);
        $this->assertEmpty($result->subscriptionIds);
    }

    /**
     * Test 2: Cart with only hosting items calls createSubscription for each, not a checkout session.
     *
     * Rentals are no longer subscriptions: they are charged once via the checkout session.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_only_hosting_items_creates_subscriptions_not_checkout_session(): void
    {
        $hostingA = $this->createHostingProduct('Hosting Plan A', 9.99);
        $hostingB = $this->createHostingProduct('Hosting Plan B', 14.99);

        $cartItems = [
            $this->productToCartItem($hostingA),
            $this->productToCartItem($hostingB),
        ];

        $this->stripeService
            ->shouldReceive('ensureCustomer')
            ->once()
            ->with($this->customer)
            ->andReturn('cus_test_123');

        $this->stripeService
            ->shouldNotReceive('createCheckoutSessionWithParams');

        $this->stripeService
            ->shouldReceive('createSubscription')
            ->twice()
            ->andReturn(
                $this->makeFakeStripeSubscription('sub_hosting_1'),
                $this->makeFakeStripeSubscription('sub_hosting_2'),
            );

        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkout($this->checkoutService, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNull($result->checkoutSessionUrl);
        $this->assertEquals(['sub_hosting_1', 'sub_hosting_2'], $result->subscriptionIds);
    }

    /**
     * Test 3: Cart with mixed items calls both createCheckoutSession AND createSubscription.
     *
     * **Validates: Requirements 4.6**
     */
    public function test_mixed_cart_calls_both_checkout_session_and_subscriptions(): void
    {
        $oneOff = $this->createOneOffProduct('One-Off Widget', 49.99);
        $hosting = $this->createHostingProduct('Premium Hosting', 19.99);
        $rental = $this->createEquipmentRentalProduct('Managed Switch', 25.00);

        $cartItems = [
            $this->productToCartItem($oneOff),
            $this->productToCartItem($hosting),
            $this->productToCartItem($rental),
        ];

        $this->stripeService
            ->shouldReceive('ensureCustomer')
            ->once()
            ->with($this->customer)
            ->andReturn('cus_test_123');

        $this->stripeService
            ->shouldReceive('createCheckoutSessionWithParams')
            ->once()
            ->withArgs(function (array $params) {
                // One-off and rental go in the session; hosting does not
                $names = array_map(fn ($l) => $l['price_data']['product_data']['name'], $params['line_items']);

                return $params['customer'] === 'cus_test_123'
                    && $names === ['One-Off Widget', 'Managed Switch (Rental)'];
            })
            ->andReturn($this->makeFakeStripeSession('cs_mixed_test'));

        $this->stripeService
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn($this->makeFakeStripeSubscription('sub_hosting_mixed'));

        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkout($this->checkoutService, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNotNull($result->checkoutSessionUrl);
        $this->assertEquals(['sub_hosting_mixed'], $result->subscriptionIds);
    }

    /**
     * Test 4: Empty cart — neither createCheckoutSession nor createSubscription is called.
     *
     * **Validates: Requirements 4.4, 4.5, 4.6**
     */
    public function test_empty_cart_calls_neither_checkout_session_nor_subscription(): void
    {
        $cartItems = [];

        // processCheckout returns early for an empty cart, before touching Stripe
        $this->stripeService->shouldNotReceive('ensureCustomer');

        $this->stripeService
            ->shouldNotReceive('createCheckoutSessionWithParams');

        $this->stripeService
            ->shouldNotReceive('createSubscription');

        $result = $this->checkout($this->checkoutService, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNull($result->checkoutSessionUrl);
        $this->assertEmpty($result->subscriptionIds);
    }

    /**
     * Test 5 (Property): For any cart composition, all one-off items are processed via
     * checkout session and all recurring items via individual subscriptions.
     * The number of createSubscription calls equals the number of recurring items.
     * The line items count in createCheckoutSession equals the number of one-off items.
     *
     * **Validates: Requirements 4.4, 4.5, 4.6**
     */
    public function test_property_partitioning_call_counts_match_item_counts(): void
    {
        // Generate various cart compositions to verify the partitioning property
        $compositions = [
            // [one-off count, hosting count, equipment_rental count]
            [3, 0, 0],
            [0, 2, 0],
            [0, 0, 3],
            [2, 1, 1],
            [1, 2, 3],
            [5, 3, 2],
            [0, 1, 2],
            [1, 0, 0],
        ];

        foreach ($compositions as $compIndex => [$oneOffCount, $hostingCount, $rentalCount]) {
            // Build products and cart items
            $cartItems = [];
            for ($i = 0; $i < $oneOffCount; $i++) {
                $product = $this->createOneOffProduct("OneOff_{$i}", 10.00 + $i);
                $cartItems[] = $this->productToCartItem($product);
            }
            for ($i = 0; $i < $hostingCount; $i++) {
                $product = $this->createHostingProduct("Hosting_{$i}", 20.00 + $i);
                $cartItems[] = $this->productToCartItem($product);
            }
            for ($i = 0; $i < $rentalCount; $i++) {
                $product = $this->createEquipmentRentalProduct("Rental_{$i}", 30.00 + $i);
                $cartItems[] = $this->productToCartItem($product);
            }

            // Hosting is subscribed individually; one-off and rental go in the session
            $recurringCount = $hostingCount;
            $sessionItemCount = $oneOffCount + $rentalCount;

            // Reset mock expectations for each iteration
            $stripeService = Mockery::mock(StripeService::class);
            $bookingService = Mockery::mock(BookingService::class);
            $notificationService = Mockery::mock(NotificationService::class);
            $notificationService->shouldReceive('notifyAdminNewOrder')->once();
            $checkoutService = $this->makeCheckoutService($stripeService, $bookingService, $notificationService);

            $stripeService
                ->shouldReceive('ensureCustomer')
                ->once()
                ->andReturn('cus_test_123');

            // Track how many line items go to checkout session
            $capturedLineItemCount = null;
            if ($sessionItemCount > 0) {
                $stripeService
                    ->shouldReceive('createCheckoutSessionWithParams')
                    ->once()
                    ->withArgs(function (array $params) use (&$capturedLineItemCount) {
                        $capturedLineItemCount = count($params['line_items']);
                        return true;
                    })
                    ->andReturn($this->makeFakeStripeSession('cs_test_' . $compIndex));
            } else {
                $stripeService
                    ->shouldNotReceive('createCheckoutSessionWithParams');
            }

            // Each recurring item should produce one subscription call
            if ($recurringCount > 0) {
                $stripeService
                    ->shouldReceive('createSubscription')
                    ->times($recurringCount)
                    ->andReturnUsing(function () use ($compIndex) {
                        static $subCounter = 0;
                        $subCounter++;
                        return $this->makeFakeStripeSubscription("sub_prop_{$compIndex}_{$subCounter}");
                    });
            } else {
                $stripeService
                    ->shouldNotReceive('createSubscription');
            }

            $result = $this->checkout($checkoutService, $cartItems);

            $description = "Composition: one_off={$oneOffCount}, hosting={$hostingCount}, rental={$rentalCount}";
            $this->assertTrue($result->success, "Checkout should succeed for {$description}. Error: " . ($result->errorMessage ?? 'none'));

            // Verify one-off + rental items count in checkout session
            if ($sessionItemCount > 0) {
                $this->assertEquals(
                    $sessionItemCount,
                    $capturedLineItemCount,
                    "Checkout session should contain exactly {$sessionItemCount} line items for {$description}"
                );
            }

            // Verify subscription IDs count matches recurring items
            $this->assertCount(
                $recurringCount,
                $result->subscriptionIds,
                "Should have {$recurringCount} subscription IDs for {$description}"
            );

            Mockery::close();
        }
    }

    /**
     * Verify that each one-off item is correctly formatted as a Stripe line item
     * with the correct name, price (in pence), and quantity.
     *
     * **Validates: Requirements 4.4**
     */
    public function test_one_off_items_line_items_contain_correct_price_data(): void
    {
        $premium = $this->createOneOffProduct('Premium Widget', 49.99);
        $budget = $this->createOneOffProduct('Budget Widget', 9.50);

        $cartItems = [
            $this->productToCartItem($premium),
            $this->productToCartItem($budget),
        ];

        $capturedLineItems = null;

        $this->stripeService
            ->shouldReceive('ensureCustomer')
            ->once()
            ->andReturn('cus_test_123');

        $this->stripeService
            ->shouldReceive('createCheckoutSessionWithParams')
            ->once()
            ->withArgs(function (array $params) use (&$capturedLineItems) {
                $capturedLineItems = $params['line_items'];
                return true;
            })
            ->andReturn($this->makeFakeStripeSession());

        $this->stripeService
            ->shouldNotReceive('createSubscription');

        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkout($this->checkoutService, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));

        // Verify first item
        $this->assertEquals('Premium Widget', $capturedLineItems[0]['price_data']['product_data']['name']);
        $this->assertEquals(4999, $capturedLineItems[0]['price_data']['unit_amount']); // £49.99 = 4999 pence
        $this->assertEquals('gbp', $capturedLineItems[0]['price_data']['currency']);
        $this->assertEquals(1, $capturedLineItems[0]['quantity']);

        // Verify second item
        $this->assertEquals('Budget Widget', $capturedLineItems[1]['price_data']['product_data']['name']);
        $this->assertEquals(950, $capturedLineItems[1]['price_data']['unit_amount']); // £9.50 = 950 pence
    }

    /**
     * Verify that each hosting item gets its own createSubscription call
     * with correct stripe_price_id and metadata.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_hosting_items_each_create_individual_subscription_with_metadata(): void
    {
        $hosting = $this->createHostingProduct('Pro Hosting', 29.99);
        $hosting2 = $this->createHostingProduct('Business Hosting', 12.50);

        $cartItems = [
            $this->productToCartItem($hosting, 'price_hosting_custom'),
            $this->productToCartItem($hosting2, 'price_hosting_custom_2'),
        ];

        $capturedCalls = [];

        $this->stripeService
            ->shouldReceive('ensureCustomer')
            ->once()
            ->andReturn('cus_test_123');

        $this->stripeService
            ->shouldNotReceive('createCheckoutSessionWithParams');

        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $this->stripeService
            ->shouldReceive('createSubscription')
            ->twice()
            ->withArgs(function ($customerId, $priceId, $metadata) use (&$capturedCalls) {
                $capturedCalls[] = [
                    'customer_id' => $customerId,
                    'price_id' => $priceId,
                    'metadata' => $metadata,
                ];
                return true;
            })
            ->andReturn(
                $this->makeFakeStripeSubscription('sub_1'),
                $this->makeFakeStripeSubscription('sub_2'),
            );

        $result = $this->checkout($this->checkoutService, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));

        // Verify first subscription call (hosting)
        $this->assertEquals('cus_test_123', $capturedCalls[0]['customer_id']);
        $this->assertEquals('price_hosting_custom', $capturedCalls[0]['price_id']);
        $this->assertEquals($hosting->id, $capturedCalls[0]['metadata']['product_id']);
        $this->assertEquals('Pro Hosting', $capturedCalls[0]['metadata']['product_name']);
        $this->assertEquals('hosting', $capturedCalls[0]['metadata']['product_type']);
        $this->assertEquals($this->customer->company_id, $capturedCalls[0]['metadata']['company_id']);

        // Verify second subscription call (second hosting item)
        $this->assertEquals('cus_test_123', $capturedCalls[1]['customer_id']);
        $this->assertEquals('price_hosting_custom_2', $capturedCalls[1]['price_id']);
        $this->assertEquals($hosting2->id, $capturedCalls[1]['metadata']['product_id']);
        $this->assertEquals('Business Hosting', $capturedCalls[1]['metadata']['product_name']);
        $this->assertEquals('hosting', $capturedCalls[1]['metadata']['product_type']);
    }

    /**
     * Verify that hosting items (product_type 'hosting') are treated as recurring.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_hosting_items_are_treated_as_recurring(): void
    {
        $product = $this->createHostingProduct('Enterprise Hosting', 99.99);
        $cartItems = [$this->productToCartItem($product)];

        $this->stripeService->shouldReceive('ensureCustomer')->once()->andReturn('cus_test_123');
        $this->stripeService->shouldNotReceive('createCheckoutSessionWithParams');
        $this->stripeService
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn($this->makeFakeStripeSubscription('sub_hosting'));
        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkout($this->checkoutService, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNull($result->checkoutSessionUrl);
        $this->assertEquals(['sub_hosting'], $result->subscriptionIds);
    }

    /**
     * Verify that equipment_rental items are charged once via the checkout session,
     * not as subscriptions.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_equipment_rental_items_are_charged_via_checkout_session(): void
    {
        $product = $this->createEquipmentRentalProduct('Firewall Appliance', 45.00);
        $cartItems = [$this->productToCartItem($product)];

        $this->stripeService->shouldReceive('ensureCustomer')->once()->andReturn('cus_test_123');
        $this->stripeService->shouldNotReceive('createSubscription');
        $this->stripeService
            ->shouldReceive('createCheckoutSessionWithParams')
            ->once()
            ->andReturn($this->makeFakeStripeSession('cs_rental'));
        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkout($this->checkoutService, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNotNull($result->checkoutSessionUrl);
        $this->assertEmpty($result->subscriptionIds);
    }

    // ─── Stock Decrement Tests (processCheckout) ─────────────────────────

    /**
     * Test: Checkout decrements stock_quantity by the purchased quantity for one-off products.
     *
     * **Validates: Requirements 1.3, 1.6**
     */
    public function test_checkout_decrements_stock_by_purchased_quantity(): void
    {
        $product = Product::factory()->oneOff()->create([
            'name' => 'Widget',
            'price' => 10.00,
            'stock_quantity' => 20,
        ]);

        $cartItems = [
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 10.00,
                'product_type' => 'one_off',
                'billing_frequency' => null,
                'quantity' => 5,
                'total_price' => 50.00,
            ],
        ];

        $cartService = Mockery::mock(\App\Services\CartService::class);
        $cartService->shouldReceive('getItems')->andReturn($cartItems);
        $cartService->shouldReceive('hasOnlyHostingItems')->andReturn(false);
        $cartService->shouldReceive('getDeliveryTotal')->andReturn(0.0);
        $cartService->shouldReceive('clear')->once();

        $this->stripeService->shouldReceive('ensureCustomer')->once()->andReturn('cus_test_123');
        $this->stripeService->shouldReceive('createCheckoutSessionWithParams')->once()
            ->andReturn(\Stripe\Checkout\Session::constructFrom(['id' => 'cs_test', 'url' => 'https://checkout.stripe.com/test']));

        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkoutService->processCheckout($this->customer, $cartService, [
            'address_line1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB',
        ]);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));

        // Verify stock was decremented by 5
        $product->refresh();
        $this->assertEquals(15, $product->stock_quantity);
    }

    /**
     * Test: Checkout fails with user-friendly error when stock is insufficient.
     *
     * **Validates: Requirements 1.6**
     */
    public function test_checkout_fails_when_stock_insufficient(): void
    {
        $product = Product::factory()->oneOff()->create([
            'name' => 'Limited Widget',
            'price' => 10.00,
            'stock_quantity' => 3,
        ]);

        $cartItems = [
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 10.00,
                'product_type' => 'one_off',
                'billing_frequency' => null,
                'quantity' => 5,
                'total_price' => 50.00,
            ],
        ];

        $cartService = Mockery::mock(\App\Services\CartService::class);
        $cartService->shouldReceive('getItems')->andReturn($cartItems);
        $cartService->shouldReceive('hasOnlyHostingItems')->andReturn(false);
        $cartService->shouldReceive('getDeliveryTotal')->andReturn(0.0);

        $this->stripeService->shouldReceive('ensureCustomer')->once()->andReturn('cus_test_123');

        $result = $this->checkoutService->processCheckout($this->customer, $cartService, [
            'address_line1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString("Insufficient stock for 'Limited Widget'", $result->errorMessage);
        $this->assertStringContainsString('Only 3 units available', $result->errorMessage);

        // Verify stock was NOT decremented (transaction rolled back)
        $product->refresh();
        $this->assertEquals(3, $product->stock_quantity);
    }

    /**
     * Test: Checkout does not decrement stock for products with unlimited stock (null).
     *
     * **Validates: Requirements 1.3**
     */
    public function test_checkout_skips_decrement_for_unlimited_stock(): void
    {
        $product = Product::factory()->oneOff()->create([
            'name' => 'Unlimited Widget',
            'price' => 10.00,
            'stock_quantity' => null,
        ]);

        $cartItems = [
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 10.00,
                'product_type' => 'one_off',
                'billing_frequency' => null,
                'quantity' => 100,
                'total_price' => 1000.00,
            ],
        ];

        $cartService = Mockery::mock(\App\Services\CartService::class);
        $cartService->shouldReceive('getItems')->andReturn($cartItems);
        $cartService->shouldReceive('hasOnlyHostingItems')->andReturn(false);
        $cartService->shouldReceive('getDeliveryTotal')->andReturn(0.0);
        $cartService->shouldReceive('clear')->once();

        $this->stripeService->shouldReceive('ensureCustomer')->once()->andReturn('cus_test_123');
        $this->stripeService->shouldReceive('createCheckoutSessionWithParams')->once()
            ->andReturn(\Stripe\Checkout\Session::constructFrom(['id' => 'cs_test', 'url' => 'https://checkout.stripe.com/test']));

        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkoutService->processCheckout($this->customer, $cartService, [
            'address_line1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB',
        ]);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));

        // Verify stock remains null (unlimited)
        $product->refresh();
        $this->assertNull($product->stock_quantity);
    }

    /**
     * Test: Only one-off products get stock decremented, not rental or hosting.
     *
     * **Validates: Requirements 1.3**
     */
    public function test_checkout_only_decrements_stock_for_one_off_products(): void
    {
        $oneOff = Product::factory()->oneOff()->create([
            'name' => 'One-Off Widget',
            'price' => 10.00,
            'stock_quantity' => 20,
        ]);
        $rental = Product::factory()->equipmentRental()->create([
            'name' => 'Router Rental',
            'price' => 15.00,
            'stock_quantity' => 10,
        ]);

        $cartItems = [
            [
                'product_id' => $oneOff->id,
                'name' => $oneOff->name,
                'price' => 10.00,
                'product_type' => 'one_off',
                'billing_frequency' => null,
                'quantity' => 3,
                'total_price' => 30.00,
            ],
            [
                'product_id' => $rental->id,
                'name' => $rental->name,
                'price' => 15.00,
                'product_type' => 'equipment_rental',
                'billing_frequency' => null,
                'quantity' => 2,
                'total_price' => 30.00,
                'rental_start_date' => now()->addDays(1)->toDateString(),
                'rental_end_date' => now()->addDays(7)->toDateString(),
            ],
        ];

        $cartService = Mockery::mock(\App\Services\CartService::class);
        $cartService->shouldReceive('getItems')->andReturn($cartItems);
        $cartService->shouldReceive('hasOnlyHostingItems')->andReturn(false);
        $cartService->shouldReceive('getDeliveryTotal')->andReturn(0.0);
        $cartService->shouldReceive('clear')->once();

        $this->stripeService->shouldReceive('ensureCustomer')->once()->andReturn('cus_test_123');
        $this->stripeService->shouldReceive('createCheckoutSessionWithParams')->once()
            ->andReturn(\Stripe\Checkout\Session::constructFrom(['id' => 'cs_test', 'url' => 'https://checkout.stripe.com/test']));

        $this->bookingService->shouldReceive('createBooking')->once()
            ->andReturn(new \App\Models\Booking(['id' => 1]));

        // PDF generation is resolved from the container; stub it out here
        $pdfService = Mockery::mock(\App\Services\BookingConfirmationPdfService::class);
        $pdfService->shouldReceive('generate')->once();
        $this->app->instance(\App\Services\BookingConfirmationPdfService::class, $pdfService);

        $this->notificationService->shouldReceive('notifyAdminNewOrder')->once();

        $result = $this->checkoutService->processCheckout($this->customer, $cartService, [
            'address_line1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB',
        ]);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));

        // One-off product stock should be decremented
        $oneOff->refresh();
        $this->assertEquals(17, $oneOff->stock_quantity);

        // Rental product stock should NOT be decremented
        $rental->refresh();
        $this->assertEquals(10, $rental->stock_quantity);
    }
}
