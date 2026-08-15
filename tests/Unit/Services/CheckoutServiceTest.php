<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Services\BookingService;
use App\Services\CheckoutService;
use App\Services\NotificationService;
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
        $this->checkoutService = new CheckoutService(
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
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(function ($customerId, $lineItems, $successUrl, $cancelUrl) {
                return $customerId === 'cus_test_123'
                    && count($lineItems) === 2;
            })
            ->andReturn($this->makeFakeStripeSession());

        $this->stripeService
            ->shouldNotReceive('createSubscription');

        $result = $this->checkoutService->processCheckoutFromArray($this->customer, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertEquals('https://checkout.stripe.com/test', $result->checkoutSessionUrl);
        $this->assertEmpty($result->subscriptionIds);
    }

    /**
     * Test 2: Cart with only recurring items calls createSubscription for each, not createCheckoutSession.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_only_recurring_items_creates_subscriptions_not_checkout_session(): void
    {
        $hosting = $this->createHostingProduct('Hosting Plan A', 9.99);
        $rental = $this->createEquipmentRentalProduct('Router', 15.00);

        $cartItems = [
            $this->productToCartItem($hosting),
            $this->productToCartItem($rental),
        ];

        $this->stripeService
            ->shouldReceive('ensureCustomer')
            ->once()
            ->with($this->customer)
            ->andReturn('cus_test_123');

        $this->stripeService
            ->shouldNotReceive('createCheckoutSession');

        $this->stripeService
            ->shouldReceive('createSubscription')
            ->twice()
            ->andReturn(
                $this->makeFakeStripeSubscription('sub_hosting_1'),
                $this->makeFakeStripeSubscription('sub_rental_1'),
            );

        $result = $this->checkoutService->processCheckoutFromArray($this->customer, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNull($result->checkoutSessionUrl);
        $this->assertCount(2, $result->subscriptionIds);
        $this->assertEquals(['sub_hosting_1', 'sub_rental_1'], $result->subscriptionIds);
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
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(function ($customerId, $lineItems, $successUrl, $cancelUrl) {
                // Only the one-off item should be in the checkout session
                return $customerId === 'cus_test_123'
                    && count($lineItems) === 1
                    && $lineItems[0]['price_data']['product_data']['name'] === 'One-Off Widget';
            })
            ->andReturn($this->makeFakeStripeSession('cs_mixed_test'));

        $this->stripeService
            ->shouldReceive('createSubscription')
            ->twice()
            ->andReturn(
                $this->makeFakeStripeSubscription('sub_hosting_mixed'),
                $this->makeFakeStripeSubscription('sub_rental_mixed'),
            );

        $result = $this->checkoutService->processCheckoutFromArray($this->customer, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNotNull($result->checkoutSessionUrl);
        $this->assertCount(2, $result->subscriptionIds);
    }

    /**
     * Test 4: Empty cart — neither createCheckoutSession nor createSubscription is called.
     *
     * **Validates: Requirements 4.4, 4.5, 4.6**
     */
    public function test_empty_cart_calls_neither_checkout_session_nor_subscription(): void
    {
        $cartItems = [];

        $this->stripeService
            ->shouldReceive('ensureCustomer')
            ->once()
            ->with($this->customer)
            ->andReturn('cus_test_123');

        $this->stripeService
            ->shouldNotReceive('createCheckoutSession');

        $this->stripeService
            ->shouldNotReceive('createSubscription');

        $result = $this->checkoutService->processCheckoutFromArray($this->customer, $cartItems);

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

            $recurringCount = $hostingCount + $rentalCount;

            // Reset mock expectations for each iteration
            $stripeService = Mockery::mock(StripeService::class);
            $bookingService = Mockery::mock(BookingService::class);
            $notificationService = Mockery::mock(NotificationService::class);
            $checkoutService = new CheckoutService($stripeService, $bookingService, $notificationService);

            $stripeService
                ->shouldReceive('ensureCustomer')
                ->once()
                ->andReturn('cus_test_123');

            // Track how many line items go to checkout session
            $capturedLineItemCount = null;
            if ($oneOffCount > 0) {
                $stripeService
                    ->shouldReceive('createCheckoutSession')
                    ->once()
                    ->withArgs(function ($customerId, $lineItems) use (&$capturedLineItemCount) {
                        $capturedLineItemCount = count($lineItems);
                        return true;
                    })
                    ->andReturn($this->makeFakeStripeSession('cs_test_' . $compIndex));
            } else {
                $stripeService
                    ->shouldNotReceive('createCheckoutSession');
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

            $result = $checkoutService->processCheckoutFromArray($this->customer, $cartItems);

            $description = "Composition: one_off={$oneOffCount}, hosting={$hostingCount}, rental={$rentalCount}";
            $this->assertTrue($result->success, "Checkout should succeed for {$description}. Error: " . ($result->errorMessage ?? 'none'));

            // Verify one-off items count in checkout session
            if ($oneOffCount > 0) {
                $this->assertEquals(
                    $oneOffCount,
                    $capturedLineItemCount,
                    "Checkout session should contain exactly {$oneOffCount} line items for {$description}"
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
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(function ($customerId, $lineItems) use (&$capturedLineItems) {
                $capturedLineItems = $lineItems;
                return true;
            })
            ->andReturn($this->makeFakeStripeSession());

        $this->stripeService
            ->shouldNotReceive('createSubscription');

        $result = $this->checkoutService->processCheckoutFromArray($this->customer, $cartItems);

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
     * Verify that each recurring item gets its own createSubscription call
     * with correct stripe_price_id and metadata.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_recurring_items_each_create_individual_subscription_with_metadata(): void
    {
        $hosting = $this->createHostingProduct('Pro Hosting', 29.99);
        $rental = $this->createEquipmentRentalProduct('Managed Router', 12.50);

        $cartItems = [
            $this->productToCartItem($hosting, 'price_hosting_custom'),
            $this->productToCartItem($rental, 'price_rental_custom'),
        ];

        $capturedCalls = [];

        $this->stripeService
            ->shouldReceive('ensureCustomer')
            ->once()
            ->andReturn('cus_test_123');

        $this->stripeService
            ->shouldNotReceive('createCheckoutSession');

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

        $result = $this->checkoutService->processCheckoutFromArray($this->customer, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));

        // Verify first subscription call (hosting)
        $this->assertEquals('cus_test_123', $capturedCalls[0]['customer_id']);
        $this->assertEquals('price_hosting_custom', $capturedCalls[0]['price_id']);
        $this->assertEquals($hosting->id, $capturedCalls[0]['metadata']['product_id']);
        $this->assertEquals('Pro Hosting', $capturedCalls[0]['metadata']['product_name']);
        $this->assertEquals('hosting', $capturedCalls[0]['metadata']['product_type']);
        $this->assertEquals($this->customer->company_id, $capturedCalls[0]['metadata']['company_id']);

        // Verify second subscription call (equipment rental)
        $this->assertEquals('cus_test_123', $capturedCalls[1]['customer_id']);
        $this->assertEquals('price_rental_custom', $capturedCalls[1]['price_id']);
        $this->assertEquals($rental->id, $capturedCalls[1]['metadata']['product_id']);
        $this->assertEquals('Managed Router', $capturedCalls[1]['metadata']['product_name']);
        $this->assertEquals('equipment_rental', $capturedCalls[1]['metadata']['product_type']);
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
        $this->stripeService->shouldNotReceive('createCheckoutSession');
        $this->stripeService
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn($this->makeFakeStripeSubscription('sub_hosting'));

        $result = $this->checkoutService->processCheckoutFromArray($this->customer, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNull($result->checkoutSessionUrl);
        $this->assertEquals(['sub_hosting'], $result->subscriptionIds);
    }

    /**
     * Verify that equipment_rental items are treated as recurring.
     *
     * **Validates: Requirements 4.5**
     */
    public function test_equipment_rental_items_are_treated_as_recurring(): void
    {
        $product = $this->createEquipmentRentalProduct('Firewall Appliance', 45.00);
        $cartItems = [$this->productToCartItem($product)];

        $this->stripeService->shouldReceive('ensureCustomer')->once()->andReturn('cus_test_123');
        $this->stripeService->shouldNotReceive('createCheckoutSession');
        $this->stripeService
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn($this->makeFakeStripeSubscription('sub_rental'));

        $result = $this->checkoutService->processCheckoutFromArray($this->customer, $cartItems);

        $this->assertTrue($result->success, 'Checkout failed: ' . ($result->errorMessage ?? 'unknown'));
        $this->assertNull($result->checkoutSessionUrl);
        $this->assertEquals(['sub_rental'], $result->subscriptionIds);
    }
}
