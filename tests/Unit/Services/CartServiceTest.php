<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Services\CartService;
use InvalidArgumentException;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = new CartService();
    }

    // ──────────────────────────────────────────────────────────────────
    // addItem
    // ──────────────────────────────────────────────────────────────────

    public function test_can_add_available_product_to_cart(): void
    {
        $product = $this->makeAvailableProduct();

        $this->cartService->addItem($product);

        $items = $this->cartService->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals($product->id, $items[0]['product_id']);
        $this->assertEquals($product->name, $items[0]['name']);
        $this->assertEquals((float) $product->price, $items[0]['price']);
        $this->assertEquals($product->product_type, $items[0]['product_type']);
        $this->assertEquals($product->billing_frequency, $items[0]['billing_frequency']);
    }

    public function test_cannot_add_archived_product_to_cart(): void
    {
        $product = $this->makeProduct([
            'is_archived' => true,
            'stock_quantity' => 10,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Product \"{$product->name}\" is not available for purchase.");

        $this->cartService->addItem($product);
    }

    public function test_cannot_add_out_of_stock_product_to_cart(): void
    {
        $product = $this->makeProduct([
            'is_archived' => false,
            'stock_quantity' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Product \"{$product->name}\" is not available for purchase.");

        $this->cartService->addItem($product);
    }

    public function test_can_add_product_with_unlimited_stock(): void
    {
        $product = $this->makeProduct([
            'is_archived' => false,
            'stock_quantity' => null, // unlimited
            'product_type' => 'hosting',
            'billing_frequency' => 'monthly',
        ]);

        $this->cartService->addItem($product);

        $this->assertCount(1, $this->cartService->getItems());
    }

    // ──────────────────────────────────────────────────────────────────
    // removeItem
    // ──────────────────────────────────────────────────────────────────

    public function test_can_remove_item_by_index(): void
    {
        $product1 = $this->makeAvailableProduct(['name' => 'Product A', 'price' => 10.00]);
        $product2 = $this->makeAvailableProduct(['name' => 'Product B', 'price' => 20.00]);
        $product3 = $this->makeAvailableProduct(['name' => 'Product C', 'price' => 30.00]);

        $this->cartService->addItem($product1);
        $this->cartService->addItem($product2);
        $this->cartService->addItem($product3);

        $this->cartService->removeItem(1); // Remove Product B

        $items = $this->cartService->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals('Product A', $items[0]['name']);
        $this->assertEquals('Product C', $items[1]['name']);
    }

    public function test_remove_item_with_invalid_index_does_nothing(): void
    {
        $product = $this->makeAvailableProduct();
        $this->cartService->addItem($product);

        $this->cartService->removeItem(99);

        $this->assertCount(1, $this->cartService->getItems());
    }

    // ──────────────────────────────────────────────────────────────────
    // getTotal
    // ──────────────────────────────────────────────────────────────────

    public function test_get_total_returns_sum_of_all_item_prices(): void
    {
        $this->cartService->addItem($this->makeAvailableProduct(['price' => 10.50]));
        $this->cartService->addItem($this->makeAvailableProduct(['price' => 20.75]));
        $this->cartService->addItem($this->makeAvailableProduct(['price' => 5.25]));

        $this->assertEquals(36.50, $this->cartService->getTotal());
    }

    public function test_get_total_returns_zero_for_empty_cart(): void
    {
        $this->assertEquals(0.0, $this->cartService->getTotal());
    }

    // ──────────────────────────────────────────────────────────────────
    // clear
    // ──────────────────────────────────────────────────────────────────

    public function test_clear_empties_the_cart(): void
    {
        $this->cartService->addItem($this->makeAvailableProduct());
        $this->cartService->addItem($this->makeAvailableProduct());

        $this->cartService->clear();

        $this->assertEmpty($this->cartService->getItems());
        $this->assertTrue($this->cartService->isEmpty());
    }

    // ──────────────────────────────────────────────────────────────────
    // isEmpty
    // ──────────────────────────────────────────────────────────────────

    public function test_is_empty_returns_true_for_empty_cart(): void
    {
        $this->assertTrue($this->cartService->isEmpty());
    }

    public function test_is_empty_returns_false_when_cart_has_items(): void
    {
        $this->cartService->addItem($this->makeAvailableProduct());

        $this->assertFalse($this->cartService->isEmpty());
    }

    // ──────────────────────────────────────────────────────────────────
    // getOneOffItems
    // ──────────────────────────────────────────────────────────────────

    public function test_get_one_off_items_returns_only_one_off_type(): void
    {
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'one_off',
            'billing_frequency' => null,
        ]));
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'hosting',
            'billing_frequency' => 'monthly',
        ]));
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'equipment_rental',
            'billing_frequency' => 'quarterly',
        ]));
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'one_off',
            'billing_frequency' => null,
        ]));

        $oneOffItems = $this->cartService->getOneOffItems();

        $this->assertCount(2, $oneOffItems);
        foreach ($oneOffItems as $item) {
            $this->assertEquals('one_off', $item['product_type']);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // getRecurringItems
    // ──────────────────────────────────────────────────────────────────

    public function test_get_recurring_items_returns_only_hosting_and_equipment_rental(): void
    {
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'one_off',
            'billing_frequency' => null,
        ]));
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'hosting',
            'billing_frequency' => 'monthly',
        ]));
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'equipment_rental',
            'billing_frequency' => 'annually',
        ]));

        $recurringItems = $this->cartService->getRecurringItems();

        $this->assertCount(2, $recurringItems);
        foreach ($recurringItems as $item) {
            $this->assertContains($item['product_type'], ['equipment_rental', 'hosting']);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 8: Cart Total Equals Sum of Item Prices
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 4.2**
     *
     * Property 8: For any Cart containing one or more items, the displayed
     * total amount SHALL equal the arithmetic sum of all individual item prices.
     *
     * This test generates random combinations of products and verifies the
     * cart total always equals the sum of individual prices.
     */
    public function test_property_cart_total_equals_sum_of_item_prices(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            // Clear cart for each iteration
            $this->cartService->clear();

            $numItems = random_int(1, 10);
            $expectedTotal = 0.0;

            for ($j = 0; $j < $numItems; $j++) {
                // Generate random price between 0.01 and 999.99
                $price = round(random_int(1, 99999) / 100, 2);
                $expectedTotal += $price;

                $product = $this->makeAvailableProduct(['price' => $price]);
                $this->cartService->addItem($product);
            }

            $this->assertEqualsWithDelta(
                $expectedTotal,
                $this->cartService->getTotal(),
                0.001,
                "Property 8 failed on iteration {$i}: Cart total should equal sum of item prices. "
                . "Expected {$expectedTotal}, got {$this->cartService->getTotal()} with {$numItems} items."
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * Create a Product model instance (not persisted to DB) that is available for purchase.
     */
    private function makeAvailableProduct(array $overrides = []): Product
    {
        static $idCounter = 1;

        $defaults = [
            'id' => $idCounter++,
            'name' => 'Test Product ' . $idCounter,
            'description' => 'A test product',
            'product_type' => 'one_off',
            'price' => 29.99,
            'billing_frequency' => null,
            'stock_quantity' => 10,
            'image_path' => null,
            'is_archived' => false,
        ];

        $attributes = array_merge($defaults, $overrides);

        $product = new Product();
        $product->forceFill($attributes);

        return $product;
    }

    /**
     * Create a Product model instance with specified attributes.
     */
    private function makeProduct(array $attributes = []): Product
    {
        static $idCounter = 1000;

        $defaults = [
            'id' => $idCounter++,
            'name' => 'Test Product ' . $idCounter,
            'description' => 'A test product',
            'product_type' => 'one_off',
            'price' => 29.99,
            'billing_frequency' => null,
            'stock_quantity' => 10,
            'image_path' => null,
            'is_archived' => false,
        ];

        $product = new Product();
        $product->forceFill(array_merge($defaults, $attributes));

        return $product;
    }
}
