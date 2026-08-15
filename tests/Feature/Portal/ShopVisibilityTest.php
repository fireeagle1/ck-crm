<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\Product;
use App\Models\ProductVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopVisibilityTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────
    // Property 3: Archived Products Are Hidden From Shop
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 1.5**
     *
     * Property 3: For any archived Product, querying the Shop SHALL never
     * include that Product in results, regardless of customer or visibility rule.
     */
    public function test_property_archived_products_are_hidden_from_shop(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create([
            'company_id' => $customer->company_id,
            'email_verified_at' => now(),
        ]);

        // Create a mix of archived and non-archived products
        $activeProduct = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        $archivedProduct = Product::factory()->oneOff()->create([
            'is_archived' => true,
            'stock_quantity' => 10,
        ]);

        $anotherActive = Product::factory()->hosting()->create([
            'is_archived' => false,
        ]);

        $anotherArchived = Product::factory()->hosting()->create([
            'is_archived' => true,
        ]);

        $response = $this->actingAs($user)->get(route('portal.shop.index'));

        $response->assertStatus(200);
        $response->assertSee($activeProduct->name);
        $response->assertSee($anotherActive->name);
        $response->assertDontSee($archivedProduct->name);
        $response->assertDontSee($anotherArchived->name);
    }

    /**
     * **Validates: Requirements 1.5**
     *
     * Archived products with visibility rules are still hidden.
     */
    public function test_archived_product_with_visibility_all_is_still_hidden(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create([
            'company_id' => $customer->company_id,
            'email_verified_at' => now(),
        ]);

        $product = Product::factory()->oneOff()->create([
            'is_archived' => true,
            'stock_quantity' => 5,
        ]);

        // Even with visibility_type 'all', archived product should not show
        ProductVisibility::create([
            'product_id' => $product->id,
            'visibility_type' => 'all',
        ]);

        $response = $this->actingAs($user)->get(route('portal.shop.index'));

        $response->assertStatus(200);
        $response->assertDontSee($product->name);
    }

    /**
     * **Validates: Requirements 1.5**
     *
     * Property-based: Generate multiple archived products with random states
     * and verify none appear in the shop.
     */
    public function test_property_no_archived_product_ever_appears_in_shop(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create([
            'company_id' => $customer->company_id,
            'email_verified_at' => now(),
        ]);

        $iterations = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $archivedProduct = Product::factory()->create([
                'is_archived' => true,
                'stock_quantity' => random_int(0, 100),
            ]);

            $response = $this->actingAs($user)->get(route('portal.shop.index'));

            $response->assertStatus(200);
            $response->assertDontSee($archivedProduct->name);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 4: Zero-Stock Products Cannot Be Purchased
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 1.7**
     *
     * Property 4: For any Product with stock_quantity equal to zero,
     * the system SHALL prevent that Product from being added to a Cart.
     * Zero-stock products still appear in the shop listing (with an unavailable badge).
     */
    public function test_property_zero_stock_product_appears_in_shop_but_marked_unavailable(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create([
            'company_id' => $customer->company_id,
            'email_verified_at' => now(),
        ]);

        $zeroStockProduct = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 0,
        ]);

        // The product should still appear in the shop listing (as unavailable)
        $response = $this->actingAs($user)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertSee($zeroStockProduct->name);
        $response->assertSee('Unavailable');
    }

    /**
     * **Validates: Requirements 1.7**
     *
     * Property 4: Zero-stock product cannot be added to CartService.
     */
    public function test_property_zero_stock_product_cannot_be_added_to_cart_service(): void
    {
        $zeroStockProduct = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 0,
        ]);

        $cartService = app(\App\Services\CartService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Product \"{$zeroStockProduct->name}\" is not available for purchase.");

        $cartService->addItem($zeroStockProduct);
    }

    /**
     * **Validates: Requirements 1.7**
     *
     * Product with positive stock can be added to CartService.
     */
    public function test_product_with_positive_stock_can_be_added_to_cart_service(): void
    {
        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 5,
        ]);

        $cartService = app(\App\Services\CartService::class);
        $cartService->addItem($product);

        $items = $cartService->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals($product->id, $items[0]['product_id']);
    }

    /**
     * **Validates: Requirements 1.7**
     *
     * Product with unlimited stock (null) can be added to CartService.
     */
    public function test_product_with_unlimited_stock_can_be_added_to_cart_service(): void
    {
        $product = Product::factory()->hosting()->create([
            'is_archived' => false,
            'stock_quantity' => null,
        ]);

        $cartService = app(\App\Services\CartService::class);
        $cartService->addItem($product);

        $items = $cartService->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals($product->id, $items[0]['product_id']);
    }

    /**
     * **Validates: Requirements 1.7**
     *
     * Property-based: verify the isAvailable() model method
     * correctly identifies zero-stock products as unavailable.
     */
    public function test_property_is_available_returns_false_for_zero_stock(): void
    {
        $iterations = 20;

        for ($i = 0; $i < $iterations; $i++) {
            $product = Product::factory()->make([
                'is_archived' => false,
                'stock_quantity' => 0,
            ]);

            $this->assertFalse(
                $product->isAvailable(),
                "Property 4 failed: Product with stock_quantity=0 should not be available."
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 5: Visibility Rules Correctly Restrict Product Access
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 2.2**
     *
     * Property 5 (customer restriction): A product with visibility_type='customers'
     * is visible only to designated customers.
     */
    public function test_property_customer_visibility_restricts_to_designated_customers(): void
    {
        $designatedCustomer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $userDesignated = User::factory()->create([
            'company_id' => $designatedCustomer->company_id,
            'email_verified_at' => now(),
        ]);

        $userOther = User::factory()->create([
            'company_id' => $otherCustomer->company_id,
            'email_verified_at' => now(),
        ]);

        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        // Assign visibility rule restricting to designatedCustomer
        $visibility = ProductVisibility::create([
            'product_id' => $product->id,
            'visibility_type' => 'customers',
        ]);
        $visibility->customers()->attach($designatedCustomer->company_id);

        // Designated customer sees it
        $response = $this->actingAs($userDesignated)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertSee($product->name);

        // Other customer does NOT see it
        $response = $this->actingAs($userOther)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertDontSee($product->name);
    }

    /**
     * **Validates: Requirements 2.3**
     *
     * Property 5 (tier restriction): A product with visibility_type='tiers'
     * is visible only to customers in the designated tier.
     */
    public function test_property_tier_visibility_restricts_to_customers_in_tier(): void
    {
        $tier = CustomerTier::create([
            'name' => 'Premium',
            'slug' => 'premium',
        ]);

        $customerInTier = Customer::factory()->create();
        $customerNotInTier = Customer::factory()->create();

        // Assign tier to customerInTier
        $customerInTier->tiers()->attach($tier->id);

        $userInTier = User::factory()->create([
            'company_id' => $customerInTier->company_id,
            'email_verified_at' => now(),
        ]);

        $userNotInTier = User::factory()->create([
            'company_id' => $customerNotInTier->company_id,
            'email_verified_at' => now(),
        ]);

        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        // Assign visibility rule restricting to the tier
        $visibility = ProductVisibility::create([
            'product_id' => $product->id,
            'visibility_type' => 'tiers',
        ]);
        $visibility->tiers()->attach($tier->id);

        // Customer in tier sees the product
        $response = $this->actingAs($userInTier)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertSee($product->name);

        // Customer not in tier does NOT see the product
        $response = $this->actingAs($userNotInTier)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertDontSee($product->name);
    }

    /**
     * **Validates: Requirements 2.4**
     *
     * Property 5 (visibility_type 'all'): A product with visibility_type='all'
     * is visible to all customers.
     */
    public function test_property_visibility_all_shows_product_to_all_customers(): void
    {
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();

        $user1 = User::factory()->create([
            'company_id' => $customer1->company_id,
            'email_verified_at' => now(),
        ]);

        $user2 = User::factory()->create([
            'company_id' => $customer2->company_id,
            'email_verified_at' => now(),
        ]);

        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        ProductVisibility::create([
            'product_id' => $product->id,
            'visibility_type' => 'all',
        ]);

        // Both customers should see the product
        $response = $this->actingAs($user1)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertSee($product->name);

        $response = $this->actingAs($user2)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertSee($product->name);
    }

    /**
     * **Validates: Requirements 2.4**
     *
     * Property 5 (no visibility rule): A product with no visibility rule
     * is visible to all customers (default behavior).
     */
    public function test_property_no_visibility_rule_shows_product_to_all_customers(): void
    {
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();

        $user1 = User::factory()->create([
            'company_id' => $customer1->company_id,
            'email_verified_at' => now(),
        ]);

        $user2 = User::factory()->create([
            'company_id' => $customer2->company_id,
            'email_verified_at' => now(),
        ]);

        // Product with NO visibility rule
        $product = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
        ]);

        // Both customers should see it
        $response = $this->actingAs($user1)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertSee($product->name);

        $response = $this->actingAs($user2)->get(route('portal.shop.index'));
        $response->assertStatus(200);
        $response->assertSee($product->name);
    }

    /**
     * **Validates: Requirements 2.2, 2.3, 2.4**
     *
     * Property 5 (comprehensive property-based): Generate multiple products
     * with varied visibility rules and verify correct access for multiple customers.
     */
    public function test_property_visibility_rules_comprehensive(): void
    {
        // Set up customers and tiers
        $tier = CustomerTier::create(['name' => 'Gold', 'slug' => 'gold']);

        $customerWithTier = Customer::factory()->create();
        $customerWithTier->tiers()->attach($tier->id);

        $designatedCustomer = Customer::factory()->create();
        $unrelatedCustomer = Customer::factory()->create();

        $userWithTier = User::factory()->create([
            'company_id' => $customerWithTier->company_id,
            'email_verified_at' => now(),
        ]);
        $userDesignated = User::factory()->create([
            'company_id' => $designatedCustomer->company_id,
            'email_verified_at' => now(),
        ]);
        $userUnrelated = User::factory()->create([
            'company_id' => $unrelatedCustomer->company_id,
            'email_verified_at' => now(),
        ]);

        // Product visible to all (explicit rule)
        $productAll = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
            'name' => 'ProductVisibleAll',
        ]);
        ProductVisibility::create([
            'product_id' => $productAll->id,
            'visibility_type' => 'all',
        ]);

        // Product visible to specific customer
        $productCustomer = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
            'name' => 'ProductForDesignated',
        ]);
        $visCustomer = ProductVisibility::create([
            'product_id' => $productCustomer->id,
            'visibility_type' => 'customers',
        ]);
        $visCustomer->customers()->attach($designatedCustomer->company_id);

        // Product visible to tier
        $productTier = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
            'name' => 'ProductForGoldTier',
        ]);
        $visTier = ProductVisibility::create([
            'product_id' => $productTier->id,
            'visibility_type' => 'tiers',
        ]);
        $visTier->tiers()->attach($tier->id);

        // Product with no rule (visible to all)
        $productNoRule = Product::factory()->oneOff()->create([
            'is_archived' => false,
            'stock_quantity' => 10,
            'name' => 'ProductNoRule',
        ]);

        // Verify: userWithTier sees all, tier, no-rule but NOT customer-restricted
        $response = $this->actingAs($userWithTier)->get(route('portal.shop.index'));
        $response->assertSee('ProductVisibleAll');
        $response->assertSee('ProductForGoldTier');
        $response->assertSee('ProductNoRule');
        $response->assertDontSee('ProductForDesignated');

        // Verify: userDesignated sees all, customer-restricted, no-rule but NOT tier-restricted
        $response = $this->actingAs($userDesignated)->get(route('portal.shop.index'));
        $response->assertSee('ProductVisibleAll');
        $response->assertSee('ProductForDesignated');
        $response->assertSee('ProductNoRule');
        $response->assertDontSee('ProductForGoldTier');

        // Verify: userUnrelated sees only all and no-rule
        $response = $this->actingAs($userUnrelated)->get(route('portal.shop.index'));
        $response->assertSee('ProductVisibleAll');
        $response->assertSee('ProductNoRule');
        $response->assertDontSee('ProductForDesignated');
        $response->assertDontSee('ProductForGoldTier');
    }
}
