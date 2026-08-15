<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

/**
 * Feature tests for the checkout flow.
 *
 * **Validates: Requirements 4.7, 4.8**
 *
 * Property 10: Stripe Customer Ensured Before Payment
 * For any Customer proceeding to checkout, if the Customer lacks a stripe_customer_id,
 * one SHALL be created and stored before payment processing. If the Customer already
 * has a stripe_customer_id, it SHALL remain unchanged (idempotent).
 *
 * Property 11: Failed Payment Preserves Cart
 * For any checkout attempt that results in a Stripe error, the Cart contents SHALL
 * remain intact and unchanged in the session.
 */
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create([
            'stripe_customer_id' => null,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->customer->company_id,
            'email_verified_at' => now(),
        ]);
    }

    // ─── Helper Methods ──────────────────────────────────────────────────

    private function buildCartItem(Product $product): array
    {
        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'product_type' => $product->product_type,
            'billing_frequency' => $product->billing_frequency,
        ];
    }

    private function makeFakeStripeSession(string $id = 'cs_test_abc', string $url = 'https://checkout.stripe.com/test'): StripeSession
    {
        return StripeSession::constructFrom(['id' => $id, 'url' => $url]);
    }

    private function makeFakeStripeSubscription(string $id = 'sub_test_123'): StripeSubscription
    {
        return StripeSubscription::constructFrom(['id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 10: Stripe Customer Ensured Before Payment
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 4.7**
     *
     * Property 10: Customer without stripe_customer_id — ensureCustomer is called
     * before any payment processing occurs, and a stripe_customer_id is stored.
     */
    public function test_property_customer_without_stripe_id_has_ensure_customer_called(): void
    {
        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        $cartItems = [$this->buildCartItem($product)];

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('ensureCustomer')
                ->once()
                ->andReturnUsing(function (Customer $customer) {
                    $customer->update(['stripe_customer_id' => 'cus_new_stripe_id']);
                    return 'cus_new_stripe_id';
                });

            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn($this->makeFakeStripeSession());

            $mock->shouldReceive('createSubscription')->never();
        });

        $response = $this->actingAs($this->user)
            ->withSession(['shop_cart' => $cartItems])
            ->post(route('portal.cart.checkout'));

        // Should redirect (to Stripe checkout URL)
        $response->assertRedirect();

        // Verify customer now has a stripe_customer_id
        $this->customer->refresh();
        $this->assertEquals('cus_new_stripe_id', $this->customer->stripe_customer_id);
    }

    /**
     * **Validates: Requirements 4.7**
     *
     * Property 10: Customer with existing stripe_customer_id — ensureCustomer is
     * still called (idempotent check) and the existing ID is preserved.
     */
    public function test_property_customer_with_existing_stripe_id_ensure_customer_still_called(): void
    {
        // Set up customer with existing stripe ID
        $this->customer->update(['stripe_customer_id' => 'cus_existing_123']);

        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        $cartItems = [$this->buildCartItem($product)];

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('ensureCustomer')
                ->once()
                ->andReturnUsing(function (Customer $customer) {
                    // Customer already has ID — just return it (idempotent)
                    return $customer->stripe_customer_id;
                });

            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn($this->makeFakeStripeSession());

            $mock->shouldReceive('createSubscription')->never();
        });

        $response = $this->actingAs($this->user)
            ->withSession(['shop_cart' => $cartItems])
            ->post(route('portal.cart.checkout'));

        $response->assertRedirect();

        // Verify stripe_customer_id remains unchanged
        $this->customer->refresh();
        $this->assertEquals('cus_existing_123', $this->customer->stripe_customer_id);
    }

    /**
     * **Validates: Requirements 4.7**
     *
     * Property 10 (property-based): For multiple checkout scenarios with varied
     * customer states, ensureCustomer is always called before payment.
     */
    public function test_property_ensure_customer_always_called_before_payment_processing(): void
    {
        $scenarios = [
            ['stripe_customer_id' => null, 'expected_return' => 'cus_created_new'],
            ['stripe_customer_id' => 'cus_already_exists', 'expected_return' => 'cus_already_exists'],
            ['stripe_customer_id' => null, 'expected_return' => 'cus_created_another'],
        ];

        $ensureCustomerCallCount = 0;

        $this->mock(StripeService::class, function (MockInterface $mock) use (&$ensureCustomerCallCount, $scenarios) {
            $mock->shouldReceive('ensureCustomer')
                ->times(count($scenarios))
                ->andReturnUsing(function (Customer $c) use (&$ensureCustomerCallCount, $scenarios) {
                    $scenario = $scenarios[$ensureCustomerCallCount];
                    $ensureCustomerCallCount++;
                    if (!$c->stripe_customer_id) {
                        $c->update(['stripe_customer_id' => $scenario['expected_return']]);
                    }
                    return $c->stripe_customer_id;
                });

            $mock->shouldReceive('createCheckoutSession')
                ->times(count($scenarios))
                ->andReturn($this->makeFakeStripeSession('cs_scenario'));

            $mock->shouldReceive('createSubscription')->never();
        });

        foreach ($scenarios as $index => $scenario) {
            $customer = Customer::factory()->create([
                'stripe_customer_id' => $scenario['stripe_customer_id'],
            ]);

            $user = User::factory()->create([
                'company_id' => $customer->company_id,
                'email_verified_at' => now(),
            ]);

            $product = Product::factory()->oneOff()->create([
                'is_archived' => false,
                'stock_quantity' => 10,
            ]);

            $cartItems = [$this->buildCartItem($product)];

            $response = $this->actingAs($user)
                ->withSession(['shop_cart' => $cartItems])
                ->post(route('portal.cart.checkout'));

            $response->assertRedirect();
        }

        // All scenarios had ensureCustomer called
        $this->assertEquals(count($scenarios), $ensureCustomerCallCount);
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 11: Failed Payment Preserves Cart
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 4.8**
     *
     * Property 11: When Stripe payment fails (CheckoutService returns success=false),
     * cart contents are preserved in session.
     */
    public function test_property_failed_payment_preserves_cart_contents(): void
    {
        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        $cartItems = [$this->buildCartItem($product)];

        // Mock StripeService to simulate a failure at ensureCustomer
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('ensureCustomer')
                ->once()
                ->andThrow(new \Exception('Stripe API error: card declined'));
        });

        $response = $this->actingAs($this->user)
            ->withSession(['shop_cart' => $cartItems])
            ->post(route('portal.cart.checkout'));

        // Should redirect back to cart with error
        $response->assertRedirect(route('portal.cart.index'));
        $response->assertSessionHas('error');

        // Cart contents must be preserved
        $response->assertSessionHas('shop_cart', $cartItems);
    }

    /**
     * **Validates: Requirements 4.8**
     *
     * Property 11: Failed checkout with multiple items preserves the entire cart.
     */
    public function test_property_failed_checkout_with_multiple_items_preserves_entire_cart(): void
    {
        $productA = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 5,
        ]);
        $productB = Product::factory()->hosting()->create([
            'is_archived' => false,
        ]);
        $productC = Product::factory()->equipmentRental()->create([
            'is_archived' => false,
            'stock_quantity' => 3,
        ]);

        $cartItems = [
            $this->buildCartItem($productA),
            $this->buildCartItem($productB),
            $this->buildCartItem($productC),
        ];

        // Mock StripeService — ensureCustomer succeeds but createCheckoutSession fails
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('ensureCustomer')
                ->once()
                ->andReturnUsing(function (Customer $customer) {
                    $customer->update(['stripe_customer_id' => 'cus_test_multi']);
                    return 'cus_test_multi';
                });

            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andThrow(new \Exception('Stripe network error'));

            // Subscription calls may happen before checkout session depending on order
            $mock->shouldReceive('createSubscription')
                ->zeroOrMoreTimes()
                ->andThrow(new \Exception('Stripe network error'));
        });

        $response = $this->actingAs($this->user)
            ->withSession(['shop_cart' => $cartItems])
            ->post(route('portal.cart.checkout'));

        // Should redirect to cart with error
        $response->assertRedirect(route('portal.cart.index'));
        $response->assertSessionHas('error');

        // All three items must remain in the cart
        $response->assertSessionHas('shop_cart', $cartItems);
    }

    /**
     * **Validates: Requirements 4.8**
     *
     * Property 11 (property-based): For various failure types,
     * cart is always preserved on failure.
     */
    public function test_property_cart_preserved_on_any_stripe_failure(): void
    {
        $failureMessages = [
            'Card declined',
            'Insufficient funds',
            'Network timeout',
            'Authentication required',
        ];

        $callIndex = 0;

        $this->mock(StripeService::class, function (MockInterface $mock) use ($failureMessages, &$callIndex) {
            $mock->shouldReceive('ensureCustomer')
                ->times(count($failureMessages))
                ->andReturnUsing(function () use ($failureMessages, &$callIndex) {
                    $message = $failureMessages[$callIndex];
                    $callIndex++;
                    throw new \Exception($message);
                });
        });

        foreach ($failureMessages as $errorMessage) {
            $product = Product::factory()->oneOff()->create([
                'is_archived' => false,
                'stock_quantity' => 10,
            ]);

            $cartItems = [$this->buildCartItem($product)];

            $response = $this->actingAs($this->user)
                ->withSession(['shop_cart' => $cartItems])
                ->post(route('portal.cart.checkout'));

            $response->assertRedirect(route('portal.cart.index'));
            $response->assertSessionHas('shop_cart', $cartItems);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Successful Checkout Clears Cart
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 4.7, 4.8**
     *
     * Successful checkout clears the cart and redirects to Stripe.
     */
    public function test_successful_checkout_clears_cart_and_redirects(): void
    {
        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        $cartItems = [$this->buildCartItem($product)];

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('ensureCustomer')
                ->once()
                ->andReturnUsing(function (Customer $customer) {
                    $customer->update(['stripe_customer_id' => 'cus_test_success']);
                    return 'cus_test_success';
                });

            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn($this->makeFakeStripeSession('cs_success', 'https://checkout.stripe.com/pay/cs_success'));

            $mock->shouldReceive('createSubscription')->never();
        });

        $response = $this->actingAs($this->user)
            ->withSession(['shop_cart' => $cartItems])
            ->post(route('portal.cart.checkout'));

        // Should redirect to Stripe checkout URL
        $response->assertRedirect('https://checkout.stripe.com/pay/cs_success');

        // Cart should be cleared after successful checkout
        $response->assertSessionMissing('shop_cart');
    }

    /**
     * **Validates: Requirements 4.7**
     *
     * Successful checkout with recurring-only items redirects to orders page.
     */
    public function test_successful_recurring_checkout_redirects_to_orders(): void
    {
        $product = Product::factory()->hosting()->create([
            'is_archived' => false,
        ]);

        $cartItems = [$this->buildCartItem($product)];

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('ensureCustomer')
                ->once()
                ->andReturnUsing(function (Customer $customer) {
                    $customer->update(['stripe_customer_id' => 'cus_test_recurring']);
                    return 'cus_test_recurring';
                });

            $mock->shouldReceive('createCheckoutSession')->never();

            $mock->shouldReceive('createSubscription')
                ->once()
                ->andReturn($this->makeFakeStripeSubscription('sub_hosting_success'));
        });

        $response = $this->actingAs($this->user)
            ->withSession(['shop_cart' => $cartItems])
            ->post(route('portal.cart.checkout'));

        // Recurring-only orders redirect to orders page
        $response->assertRedirect(route('portal.orders.index'));
        $response->assertSessionHas('success');

        // Cart should be cleared
        $response->assertSessionMissing('shop_cart');
    }

    // ──────────────────────────────────────────────────────────────────
    // Empty Cart Cannot Checkout
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 4.8**
     *
     * Empty cart cannot proceed to checkout — redirected with error.
     */
    public function test_empty_cart_cannot_proceed_to_checkout(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['shop_cart' => []])
            ->post(route('portal.cart.checkout'));

        $response->assertRedirect(route('portal.cart.index'));
        $response->assertSessionHas('error', 'Your cart is empty.');
    }

    /**
     * **Validates: Requirements 4.8**
     *
     * Cart with no session key set cannot proceed to checkout.
     */
    public function test_no_session_cart_cannot_proceed_to_checkout(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('portal.cart.checkout'));

        $response->assertRedirect(route('portal.cart.index'));
        $response->assertSessionHas('error', 'Your cart is empty.');
    }
}
