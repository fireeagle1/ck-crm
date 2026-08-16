<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = new CartService();
    }

    // ──────────────────────────────────────────────────────────────────
    // addItem — basic (one_off products, no options)
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

        $this->cartService->addItem($product, ['domain_name' => 'example.com']);

        $this->assertCount(1, $this->cartService->getItems());
    }

    // ──────────────────────────────────────────────────────────────────
    // addItem — rental items
    // ──────────────────────────────────────────────────────────────────

    public function test_can_add_rental_product_with_dates_and_quantity(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'equipment_rental',
            'price' => 50.00,
        ]);

        $this->cartService->addItem($product, [
            'rental_start_date' => '2025-08-01',
            'rental_end_date' => '2025-08-04',
            'quantity' => 2,
        ]);

        $items = $this->cartService->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals('2025-08-01', $items[0]['rental_start_date']);
        $this->assertEquals('2025-08-04', $items[0]['rental_end_date']);
        $this->assertEquals(2, $items[0]['quantity']);
        // total_price = 50 × 3 days × 2 quantity = 300
        $this->assertEquals(300.00, $items[0]['total_price']);
    }

    public function test_rental_product_requires_start_date(): void
    {
        $product = $this->makeAvailableProduct(['product_type' => 'equipment_rental']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rental start date and end date are required');

        $this->cartService->addItem($product, [
            'rental_end_date' => '2025-08-04',
        ]);
    }

    public function test_rental_product_requires_end_date(): void
    {
        $product = $this->makeAvailableProduct(['product_type' => 'equipment_rental']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rental start date and end date are required');

        $this->cartService->addItem($product, [
            'rental_start_date' => '2025-08-01',
        ]);
    }

    public function test_rental_end_date_must_be_after_start_date(): void
    {
        $product = $this->makeAvailableProduct(['product_type' => 'equipment_rental']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rental end date must be after the start date');

        $this->cartService->addItem($product, [
            'rental_start_date' => '2025-08-05',
            'rental_end_date' => '2025-08-03',
        ]);
    }

    public function test_rental_enforces_minimum_rental_days(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'equipment_rental',
            'min_rental_days' => 5,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum rental period is 5 days');

        $this->cartService->addItem($product, [
            'rental_start_date' => '2025-08-01',
            'rental_end_date' => '2025-08-03', // 2 days < 5 minimum
        ]);
    }

    public function test_rental_allows_dates_meeting_minimum(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'equipment_rental',
            'min_rental_days' => 3,
            'price' => 10.00,
        ]);

        $this->cartService->addItem($product, [
            'rental_start_date' => '2025-08-01',
            'rental_end_date' => '2025-08-04', // 3 days = minimum
        ]);

        $this->assertCount(1, $this->cartService->getItems());
    }

    public function test_rental_quantity_must_be_at_least_1(): void
    {
        $product = $this->makeAvailableProduct(['product_type' => 'equipment_rental']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');

        $this->cartService->addItem($product, [
            'rental_start_date' => '2025-08-01',
            'rental_end_date' => '2025-08-04',
            'quantity' => 0,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // addItem — hosting items
    // ──────────────────────────────────────────────────────────────────

    public function test_hosting_product_requires_domain_name(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'hosting',
            'stock_quantity' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A domain name is required for hosting products');

        $this->cartService->addItem($product);
    }

    public function test_hosting_product_validates_domain_format(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'hosting',
            'stock_quantity' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please provide a valid domain name');

        $this->cartService->addItem($product, ['domain_name' => 'not a domain']);
    }

    public function test_hosting_product_stores_domain_name(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'hosting',
            'stock_quantity' => null,
            'billing_frequency' => 'monthly',
        ]);

        $this->cartService->addItem($product, ['domain_name' => 'mywebsite.co.uk']);

        $items = $this->cartService->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals('mywebsite.co.uk', $items[0]['domain_name']);
    }

    // ──────────────────────────────────────────────────────────────────
    // addItem — one_off items (no extra validation)
    // ──────────────────────────────────────────────────────────────────

    public function test_one_off_product_needs_no_extra_options(): void
    {
        $product = $this->makeAvailableProduct(['product_type' => 'one_off']);

        $this->cartService->addItem($product);

        $items = $this->cartService->getItems();
        $this->assertCount(1, $items);
        $this->assertNull($items[0]['rental_start_date']);
        $this->assertNull($items[0]['rental_end_date']);
        $this->assertNull($items[0]['domain_name']);
        $this->assertEquals(1, $items[0]['quantity']);
    }

    // ──────────────────────────────────────────────────────────────────
    // addItem — one_off items with quantity
    // ──────────────────────────────────────────────────────────────────

    public function test_one_off_product_accepts_quantity(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'one_off',
            'price' => 15.00,
            'stock_quantity' => 10,
        ]);

        $this->cartService->addItem($product, ['quantity' => 3]);

        $items = $this->cartService->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals(3, $items[0]['quantity']);
        $this->assertEquals(45.00, $items[0]['total_price']); // 15 × 3
    }

    public function test_one_off_product_quantity_cannot_exceed_stock(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'one_off',
            'stock_quantity' => 5,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only 5 units available');

        $this->cartService->addItem($product, ['quantity' => 6]);
    }

    public function test_one_off_product_quantity_must_be_at_least_1(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'one_off',
            'stock_quantity' => 10,
        ]);

        // quantity of 0 gets clamped to 1 via max(1, ...) so it should succeed with qty=1
        $this->cartService->addItem($product, ['quantity' => 0]);

        $items = $this->cartService->getItems();
        $this->assertEquals(1, $items[0]['quantity']);
    }

    public function test_one_off_product_unlimited_stock_allows_any_quantity(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'one_off',
            'stock_quantity' => null, // unlimited
            'price' => 10.00,
        ]);

        $this->cartService->addItem($product, ['quantity' => 99]);

        $items = $this->cartService->getItems();
        $this->assertEquals(99, $items[0]['quantity']);
        $this->assertEquals(990.00, $items[0]['total_price']);
    }

    // ──────────────────────────────────────────────────────────────────
    // addItem — duplicate one-off detection
    // ──────────────────────────────────────────────────────────────────

    public function test_adding_same_one_off_product_increments_quantity(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'one_off',
            'price' => 20.00,
            'stock_quantity' => 10,
        ]);

        $this->cartService->addItem($product, ['quantity' => 2]);
        $this->cartService->addItem($product, ['quantity' => 3]);

        $items = $this->cartService->getItems();
        $this->assertCount(1, $items); // Still one line
        $this->assertEquals(5, $items[0]['quantity']); // 2 + 3
        $this->assertEquals(100.00, $items[0]['total_price']); // 20 × 5
    }

    public function test_adding_same_one_off_product_rejects_if_combined_quantity_exceeds_stock(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'one_off',
            'price' => 10.00,
            'stock_quantity' => 5,
        ]);

        $this->cartService->addItem($product, ['quantity' => 3]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only 5 units available');

        $this->cartService->addItem($product, ['quantity' => 3]); // 3 + 3 = 6 > 5
    }

    public function test_different_one_off_products_are_separate_lines(): void
    {
        $product1 = $this->makeAvailableProduct([
            'product_type' => 'one_off',
            'name' => 'Product A',
            'price' => 10.00,
        ]);
        $product2 = $this->makeAvailableProduct([
            'product_type' => 'one_off',
            'name' => 'Product B',
            'price' => 20.00,
        ]);

        $this->cartService->addItem($product1);
        $this->cartService->addItem($product2);

        $items = $this->cartService->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals('Product A', $items[0]['name']);
        $this->assertEquals('Product B', $items[1]['name']);
    }

    // ──────────────────────────────────────────────────────────────────
    // updateItemQuantity
    // ──────────────────────────────────────────────────────────────────

    public function test_update_item_quantity_for_one_off_product(): void
    {
        $product = Product::factory()->create([
            'product_type' => 'one_off',
            'price' => 25.00,
            'stock_quantity' => 10,
            'is_archived' => false,
        ]);

        $this->cartService->addItem($product, ['quantity' => 1]);

        $this->cartService->updateItemQuantity(0, 4);

        $items = $this->cartService->getItems();
        $this->assertEquals(4, $items[0]['quantity']);
        $this->assertEquals(100.00, $items[0]['total_price']); // 25 × 4
    }

    public function test_update_item_quantity_rejects_exceeding_stock(): void
    {
        $product = Product::factory()->create([
            'product_type' => 'one_off',
            'price' => 10.00,
            'stock_quantity' => 3,
            'is_archived' => false,
        ]);

        $this->cartService->addItem($product, ['quantity' => 1]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only 3 units available');

        $this->cartService->updateItemQuantity(0, 5);
    }

    public function test_update_item_quantity_rejects_zero(): void
    {
        $product = Product::factory()->create([
            'product_type' => 'one_off',
            'price' => 10.00,
            'stock_quantity' => 10,
            'is_archived' => false,
        ]);

        $this->cartService->addItem($product, ['quantity' => 2]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');

        $this->cartService->updateItemQuantity(0, 0);
    }

    public function test_update_item_quantity_rejects_non_one_off_product(): void
    {
        $product = Product::factory()->create([
            'product_type' => 'equipment_rental',
            'price' => 50.00,
            'stock_quantity' => 10,
            'is_archived' => false,
            'min_rental_days' => null,
        ]);

        $this->cartService->addItem($product, [
            'rental_start_date' => '2025-08-01',
            'rental_end_date' => '2025-08-04',
            'quantity' => 1,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity can only be adjusted for one-off products');

        $this->cartService->updateItemQuantity(0, 3);
    }

    public function test_update_item_quantity_rejects_invalid_index(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart item not found');

        $this->cartService->updateItemQuantity(99, 1);
    }

    // ──────────────────────────────────────────────────────────────────
    // Domain validation
    // ──────────────────────────────────────────────────────────────────

    public function test_valid_domain_names(): void
    {
        $validDomains = [
            'example.com',
            'my-site.co.uk',
            'sub.domain.org',
            'test123.io',
            'a.bc',
        ];

        foreach ($validDomains as $domain) {
            $this->assertTrue(
                $this->cartService->isValidDomain($domain),
                "Expected '{$domain}' to be valid"
            );
        }
    }

    public function test_invalid_domain_names(): void
    {
        $invalidDomains = [
            'no spaces.com',
            'nodot',
            '',
            '.starts-with-dot.com',
            '-starts-with-dash.com',
        ];

        foreach ($invalidDomains as $domain) {
            $this->assertFalse(
                $this->cartService->isValidDomain($domain),
                "Expected '{$domain}' to be invalid"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Rental total calculation
    // ──────────────────────────────────────────────────────────────────

    public function test_calculate_rental_total(): void
    {
        // price=25, 4 days (Aug 1-5), quantity=3 → 25 × 4 × 3 = 300
        $total = $this->cartService->calculateRentalTotal(25.00, '2025-08-01', '2025-08-05', 3);
        $this->assertEquals(300.00, $total);
    }

    public function test_calculate_rental_total_single_day(): void
    {
        // price=100, 1 day (Aug 1-2), quantity=1 → 100 × 1 × 1 = 100
        $total = $this->cartService->calculateRentalTotal(100.00, '2025-08-01', '2025-08-02', 1);
        $this->assertEquals(100.00, $total);
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

    public function test_get_total_returns_sum_of_all_item_total_prices(): void
    {
        $this->cartService->addItem($this->makeAvailableProduct(['price' => 10.50]));
        $this->cartService->addItem($this->makeAvailableProduct(['price' => 20.75]));
        $this->cartService->addItem($this->makeAvailableProduct(['price' => 5.25]));

        $this->assertEquals(36.50, $this->cartService->getTotal());
    }

    public function test_get_total_includes_rental_total_price(): void
    {
        // Rental: price=10 × 3 days × 2 qty = 60
        $rentalProduct = $this->makeAvailableProduct([
            'product_type' => 'equipment_rental',
            'price' => 10.00,
        ]);
        $this->cartService->addItem($rentalProduct, [
            'rental_start_date' => '2025-08-01',
            'rental_end_date' => '2025-08-04',
            'quantity' => 2,
        ]);

        // One-off: price=20
        $this->cartService->addItem($this->makeAvailableProduct(['price' => 20.00]));

        $this->assertEquals(80.00, $this->cartService->getTotal());
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
    // hasOnlyHostingItems / hasPhysicalItems
    // ──────────────────────────────────────────────────────────────────

    public function test_has_only_hosting_items_returns_true_for_hosting_only_cart(): void
    {
        $product = $this->makeAvailableProduct([
            'product_type' => 'hosting',
            'stock_quantity' => null,
        ]);
        $this->cartService->addItem($product, ['domain_name' => 'test.com']);

        $this->assertTrue($this->cartService->hasOnlyHostingItems());
        $this->assertFalse($this->cartService->hasPhysicalItems());
    }

    public function test_has_only_hosting_items_returns_false_for_mixed_cart(): void
    {
        $hosting = $this->makeAvailableProduct([
            'product_type' => 'hosting',
            'stock_quantity' => null,
        ]);
        $oneOff = $this->makeAvailableProduct(['product_type' => 'one_off']);

        $this->cartService->addItem($hosting, ['domain_name' => 'test.com']);
        $this->cartService->addItem($oneOff);

        $this->assertFalse($this->cartService->hasOnlyHostingItems());
        $this->assertTrue($this->cartService->hasPhysicalItems());
    }

    public function test_has_only_hosting_items_returns_false_for_empty_cart(): void
    {
        $this->assertFalse($this->cartService->hasOnlyHostingItems());
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
            'stock_quantity' => null,
        ]), ['domain_name' => 'test.com']);
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'equipment_rental',
            'billing_frequency' => 'quarterly',
        ]), [
            'rental_start_date' => '2025-08-01',
            'rental_end_date' => '2025-08-05',
            'quantity' => 1,
        ]);
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
            'stock_quantity' => null,
        ]), ['domain_name' => 'test.com']);
        $this->cartService->addItem($this->makeAvailableProduct([
            'product_type' => 'equipment_rental',
            'billing_frequency' => 'annually',
        ]), [
            'rental_start_date' => '2025-08-01',
            'rental_end_date' => '2025-08-10',
            'quantity' => 1,
        ]);

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
     * total amount SHALL equal the arithmetic sum of all individual item total_prices.
     *
     * This test generates random combinations of products and verifies the
     * cart total always equals the sum of individual total prices.
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
            'min_rental_days' => null,
            'cooldown_days' => 0,
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
            'min_rental_days' => null,
            'cooldown_days' => 0,
        ];

        $product = new Product();
        $product->forceFill(array_merge($defaults, $attributes));

        return $product;
    }
}
