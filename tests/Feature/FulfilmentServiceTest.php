<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Services\FulfilmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for FulfilmentService.
 *
 * **Validates: Requirements 5.4, 6.1, 6.2, 7.2, 7.4**
 *
 * Property 13: Stock Decrements by Exactly One Per Stockable Item Ordered
 * For any Order created containing a stockable Product (one_off or equipment_rental),
 * the Product's stock_quantity SHALL decrease by exactly one per OrderItem referencing
 * that Product.
 *
 * Property 14: Hosting Service Auto-Provisioned With Correct Fields
 * For any Hosting Product subscription successfully created, the resulting Service record
 * SHALL have service_type equal to the Product name, status "active", stripe_subscription_id
 * matching the Stripe response, start_date equal to the current date, service_monthly_charge
 * equal to the Product price, and service_payment_frequency equal to the selected billing frequency.
 *
 * Property 15: Equipment Rental Creates Pending Service
 * For any Equipment Rental Product subscription successfully created, the resulting Service
 * record SHALL have status "pending" and the stripe_subscription_id from the Stripe response.
 * After admin fulfilment, the Service status SHALL change to "active" and the Order
 * fulfilment_status SHALL change to "completed".
 */
class FulfilmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private FulfilmentService $service;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FulfilmentService();

        $this->customer = Customer::factory()->create();
    }

    // ─── Helper Methods ──────────────────────────────────────────────────

    private function createOrderWithItem(array $itemAttributes = [], array $orderAttributes = []): OrderItem
    {
        $order = Order::create(array_merge([
            'company_id' => $this->customer->company_id,
            'payment_status' => 'paid',
            'fulfilment_status' => 'pending',
            'total_amount' => $itemAttributes['price'] ?? 50.00,
        ], $orderAttributes));

        return OrderItem::create(array_merge([
            'order_id' => $order->id,
            'product_name' => 'Test Product',
            'product_type' => 'one_off',
            'price' => 50.00,
        ], $itemAttributes));
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 13: Stock Decrements by Exactly One Per Stockable Item Ordered
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 5.4**
     *
     * Property 13: One-off purchase decrements stock by exactly one.
     */
    public function test_property_one_off_purchase_decrements_stock_by_exactly_one(): void
    {
        $product = Product::factory()->oneOff()->create([
            'stock_quantity' => 10,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'one_off',
            'price' => $product->price,
        ]);

        $this->service->handleOneOffPurchase($item);

        $product->refresh();
        $this->assertEquals(9, $product->stock_quantity);
    }

    /**
     * **Validates: Requirements 5.4**
     *
     * Property 13: Equipment rental purchase decrements stock by exactly one.
     */
    public function test_property_equipment_rental_purchase_decrements_stock_by_exactly_one(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'stock_quantity' => 10,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_equip_test',
        ]);

        $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $product->refresh();
        $this->assertEquals(9, $product->stock_quantity);
    }

    /**
     * **Validates: Requirements 5.4**
     *
     * Property 13 (property-based): For random initial stock quantities,
     * stock always decrements by exactly 1 after a one-off purchase.
     */
    public function test_property_stock_decrements_by_exactly_one_for_various_quantities(): void
    {
        $stockQuantities = [1, 2, 5, 10, 25, 50, 99, 100];

        foreach ($stockQuantities as $initialStock) {
            $product = Product::factory()->oneOff()->create([
                'stock_quantity' => $initialStock,
            ]);

            $item = $this->createOrderWithItem([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_type' => 'one_off',
                'price' => $product->price,
            ]);

            $this->service->handleOneOffPurchase($item);

            $product->refresh();
            $this->assertEquals(
                $initialStock - 1,
                $product->stock_quantity,
                "Expected stock to decrement from {$initialStock} to " . ($initialStock - 1)
            );
        }
    }

    /**
     * **Validates: Requirements 5.4**
     *
     * Property 13: Product with null stock (unlimited) does NOT get decremented.
     */
    public function test_property_null_stock_product_is_not_decremented(): void
    {
        $product = Product::factory()->hosting()->create([
            'stock_quantity' => null,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_hosting_null_stock',
        ]);

        // Directly call decrementStock to verify null stock is not decremented
        $this->service->decrementStock($product);

        $product->refresh();
        $this->assertNull($product->stock_quantity);
    }

    /**
     * **Validates: Requirements 5.4**
     *
     * Property 13: Product at stock_quantity=0 throws RuntimeException.
     */
    public function test_property_zero_stock_throws_runtime_exception(): void
    {
        $product = Product::factory()->oneOff()->create([
            'stock_quantity' => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Product \"{$product->name}\" is out of stock.");

        $this->service->decrementStock($product);
    }

    /**
     * **Validates: Requirements 5.4**
     *
     * Property 13 (property-based): For various stockable product types,
     * stock always decrements by exactly 1.
     */
    public function test_property_stock_decrements_for_all_stockable_product_types(): void
    {
        $scenarios = [
            ['factory' => 'oneOff', 'stock' => 15, 'type' => 'one_off'],
            ['factory' => 'equipmentRental', 'stock' => 8, 'type' => 'equipment_rental'],
            ['factory' => 'oneOff', 'stock' => 1, 'type' => 'one_off'],
            ['factory' => 'equipmentRental', 'stock' => 50, 'type' => 'equipment_rental'],
        ];

        foreach ($scenarios as $scenario) {
            $product = Product::factory()->{$scenario['factory']}()->create([
                'stock_quantity' => $scenario['stock'],
            ]);

            $stockBefore = $product->stock_quantity;

            $this->service->decrementStock($product);

            $product->refresh();
            $this->assertEquals(
                $stockBefore - 1,
                $product->stock_quantity,
                "Stock decrement failed for {$scenario['type']} product with initial stock {$scenario['stock']}"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 14: Hosting Service Auto-Provisioned With Correct Fields
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 6.1, 6.2**
     *
     * Property 14: Hosting purchase creates a Service with status 'Active'.
     */
    public function test_property_hosting_purchase_creates_active_service(): void
    {
        $product = Product::factory()->hosting()->create([
            'price' => 29.99,
            'billing_frequency' => 'monthly',
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_hosting_active_test',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertEquals('Active', $service->status);
    }

    /**
     * **Validates: Requirements 6.1, 6.2**
     *
     * Property 14: Hosting service has service_short equal to product_name from order item.
     */
    public function test_property_hosting_service_has_correct_service_short(): void
    {
        $product = Product::factory()->hosting()->create([
            'name' => 'Premium Web Hosting',
            'billing_frequency' => 'monthly',
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => 'Premium Web Hosting',
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => 'monthly',
            'stripe_subscription_id' => 'sub_hosting_name_test',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertEquals('Premium Web Hosting', $service->service_short);
    }

    /**
     * **Validates: Requirements 6.1, 6.2**
     *
     * Property 14: Hosting service has start_date set to today.
     */
    public function test_property_hosting_service_has_start_date_today(): void
    {
        $product = Product::factory()->hosting()->create();

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_hosting_date_test',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertTrue($service->start_date->isToday());
    }

    /**
     * **Validates: Requirements 6.1, 6.2**
     *
     * Property 14: Hosting service has correct monthly charge and payment frequency.
     */
    public function test_property_hosting_service_has_correct_charge_and_frequency(): void
    {
        $product = Product::factory()->hosting()->create([
            'price' => 49.99,
            'billing_frequency' => 'quarterly',
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => 49.99,
            'billing_frequency' => 'quarterly',
            'stripe_subscription_id' => 'sub_hosting_charge_test',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertEquals('49.99', $service->service_monthly_charge);
        $this->assertEquals('quarterly', $service->service_payment_frequency);
    }

    /**
     * **Validates: Requirements 6.1, 6.2**
     *
     * Property 14: Hosting service has correct stripe_subscription_id.
     */
    public function test_property_hosting_service_has_correct_stripe_subscription_id(): void
    {
        $product = Product::factory()->hosting()->create();

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_hosting_stripe_id_789',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertEquals('sub_hosting_stripe_id_789', $service->stripe_subscription_id);
    }

    /**
     * **Validates: Requirements 6.1, 6.2**
     *
     * Property 14: Hosting purchase sets order fulfilment_status to 'completed'.
     */
    public function test_property_hosting_purchase_sets_order_fulfilment_completed(): void
    {
        $product = Product::factory()->hosting()->create();

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_hosting_order_status',
        ]);

        $this->service->handleHostingPurchase($item, $this->customer);

        $item->order->refresh();
        $this->assertEquals('completed', $item->order->fulfilment_status);
    }

    /**
     * **Validates: Requirements 6.1, 6.2**
     *
     * Property 14: Hosting purchase links service_id on the OrderItem.
     */
    public function test_property_hosting_purchase_links_service_id_on_order_item(): void
    {
        $product = Product::factory()->hosting()->create();

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_hosting_link_test',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $item->refresh();
        $this->assertEquals($service->service_id, $item->service_id);
    }

    /**
     * **Validates: Requirements 6.1, 6.2**
     *
     * Property 14 (property-based): For various hosting product configurations,
     * all service fields are correctly provisioned.
     */
    public function test_property_hosting_auto_provision_correct_fields_for_various_configs(): void
    {
        $configs = [
            ['name' => 'Basic Hosting', 'price' => 9.99, 'billing_frequency' => 'monthly', 'sub_id' => 'sub_basic_123'],
            ['name' => 'Pro Hosting', 'price' => 29.99, 'billing_frequency' => 'quarterly', 'sub_id' => 'sub_pro_456'],
            ['name' => 'Enterprise Hosting', 'price' => 199.99, 'billing_frequency' => 'annually', 'sub_id' => 'sub_ent_789'],
        ];

        foreach ($configs as $config) {
            $product = Product::factory()->hosting()->create([
                'name' => $config['name'],
                'price' => $config['price'],
                'billing_frequency' => $config['billing_frequency'],
            ]);

            $item = $this->createOrderWithItem([
                'product_id' => $product->id,
                'product_name' => $config['name'],
                'product_type' => 'hosting',
                'price' => $config['price'],
                'billing_frequency' => $config['billing_frequency'],
                'stripe_subscription_id' => $config['sub_id'],
            ]);

            $service = $this->service->handleHostingPurchase($item, $this->customer);

            // Verify all fields per Property 14
            $this->assertEquals('Active', $service->status, "Service status should be Active for {$config['name']}");
            $this->assertEquals($config['name'], $service->service_short, "service_short mismatch for {$config['name']}");
            $this->assertTrue($service->start_date->isToday(), "start_date should be today for {$config['name']}");
            $this->assertEquals(number_format($config['price'], 2, '.', ''), $service->service_monthly_charge, "service_monthly_charge mismatch for {$config['name']}");
            $this->assertEquals($config['billing_frequency'], $service->service_payment_frequency, "service_payment_frequency mismatch for {$config['name']}");
            $this->assertEquals($config['sub_id'], $service->stripe_subscription_id, "stripe_subscription_id mismatch for {$config['name']}");

            // Verify order is completed
            $item->order->refresh();
            $this->assertEquals('completed', $item->order->fulfilment_status, "Order should be completed for {$config['name']}");

            // Verify item is linked
            $item->refresh();
            $this->assertEquals($service->service_id, $item->service_id, "OrderItem should be linked for {$config['name']}");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 15: Equipment Rental Creates Pending Service
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 7.2**
     *
     * Property 15: Equipment rental creates a Service with status 'pending'.
     */
    public function test_property_equipment_rental_creates_pending_service(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'stock_quantity' => 5,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_equip_pending_test',
        ]);

        $service = $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $this->assertEquals('pending', $service->status);
    }

    /**
     * **Validates: Requirements 7.2**
     *
     * Property 15: Equipment rental service has same field mapping as hosting
     * (service_short, start_date, charge, frequency, stripe_subscription_id).
     */
    public function test_property_equipment_rental_service_has_correct_fields(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'name' => 'Router Rental',
            'price' => 15.00,
            'billing_frequency' => 'monthly',
            'stock_quantity' => 10,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => 'Router Rental',
            'product_type' => 'equipment_rental',
            'price' => 15.00,
            'billing_frequency' => 'monthly',
            'stripe_subscription_id' => 'sub_router_rental_001',
        ]);

        $service = $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $this->assertEquals('Router Rental', $service->service_short);
        $this->assertTrue($service->start_date->isToday());
        $this->assertEquals('15.00', $service->service_monthly_charge);
        $this->assertEquals('monthly', $service->service_payment_frequency);
        $this->assertEquals('sub_router_rental_001', $service->stripe_subscription_id);
    }

    /**
     * **Validates: Requirements 7.2**
     *
     * Property 15: Equipment rental sets order fulfilment_status to 'awaiting_fulfilment'.
     */
    public function test_property_equipment_rental_sets_order_awaiting_fulfilment(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'stock_quantity' => 5,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_equip_awaiting_test',
        ]);

        $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $item->order->refresh();
        $this->assertEquals('awaiting_fulfilment', $item->order->fulfilment_status);
    }

    /**
     * **Validates: Requirements 7.2, 5.4**
     *
     * Property 15: Equipment rental decrements stock.
     */
    public function test_property_equipment_rental_decrements_stock(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'stock_quantity' => 7,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_equip_stock_test',
        ]);

        $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $product->refresh();
        $this->assertEquals(6, $product->stock_quantity);
    }

    /**
     * **Validates: Requirements 7.4**
     *
     * Property 15: After admin fulfilment, service status changes to 'Active'
     * and order fulfilment_status changes to 'completed'.
     */
    public function test_property_fulfil_order_activates_pending_service_and_completes_order(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'stock_quantity' => 5,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_equip_fulfil_test',
        ]);

        // First, create the equipment rental (pending service)
        $service = $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $this->assertEquals('pending', $service->status);
        $this->assertEquals('awaiting_fulfilment', $item->order->fresh()->fulfilment_status);

        // Now admin fulfils the order
        $this->service->fulfilOrder($item->order);

        // Verify service is now Active
        $service->refresh();
        $this->assertEquals('Active', $service->status);

        // Verify order is completed with fulfilled_at timestamp
        $item->order->refresh();
        $this->assertEquals('completed', $item->order->fulfilment_status);
        $this->assertNotNull($item->order->fulfilled_at);
    }

    /**
     * **Validates: Requirements 7.4**
     *
     * Property 15 (property-based): For various equipment rental configurations,
     * the full lifecycle (create pending -> fulfil -> active) works correctly.
     */
    public function test_property_equipment_rental_full_lifecycle_various_configs(): void
    {
        $configs = [
            ['name' => 'Router Rental', 'price' => 15.00, 'billing_frequency' => 'monthly', 'stock' => 10, 'sub_id' => 'sub_router_lc'],
            ['name' => 'Switch Rental', 'price' => 25.00, 'billing_frequency' => 'quarterly', 'stock' => 3, 'sub_id' => 'sub_switch_lc'],
            ['name' => 'Server Rental', 'price' => 99.99, 'billing_frequency' => 'annually', 'stock' => 1, 'sub_id' => 'sub_server_lc'],
        ];

        foreach ($configs as $config) {
            $product = Product::factory()->equipmentRental()->create([
                'name' => $config['name'],
                'price' => $config['price'],
                'billing_frequency' => $config['billing_frequency'],
                'stock_quantity' => $config['stock'],
            ]);

            $item = $this->createOrderWithItem([
                'product_id' => $product->id,
                'product_name' => $config['name'],
                'product_type' => 'equipment_rental',
                'price' => $config['price'],
                'billing_frequency' => $config['billing_frequency'],
                'stripe_subscription_id' => $config['sub_id'],
            ]);

            // Step 1: Create equipment rental purchase
            $service = $this->service->handleEquipmentRentalPurchase($item, $this->customer);

            // Verify initial state: pending service, awaiting_fulfilment order, stock decremented
            $this->assertEquals('pending', $service->status, "Service should be pending for {$config['name']}");
            $this->assertEquals($config['name'], $service->service_short, "service_short mismatch for {$config['name']}");
            $this->assertEquals($config['sub_id'], $service->stripe_subscription_id, "stripe_subscription_id mismatch for {$config['name']}");

            $item->order->refresh();
            $this->assertEquals('awaiting_fulfilment', $item->order->fulfilment_status, "Order should be awaiting_fulfilment for {$config['name']}");

            $product->refresh();
            $this->assertEquals($config['stock'] - 1, $product->stock_quantity, "Stock should decrement for {$config['name']}");

            // Step 2: Admin fulfils the order
            $this->service->fulfilOrder($item->order);

            // Verify final state: active service, completed order
            $service->refresh();
            $this->assertEquals('Active', $service->status, "Service should be Active after fulfilment for {$config['name']}");

            $item->order->refresh();
            $this->assertEquals('completed', $item->order->fulfilment_status, "Order should be completed after fulfilment for {$config['name']}");
            $this->assertNotNull($item->order->fulfilled_at, "fulfilled_at should be set for {$config['name']}");
        }
    }

    /**
     * **Validates: Requirements 7.4**
     *
     * Property 15: fulfilOrder sets fulfilled_at timestamp.
     */
    public function test_property_fulfil_order_sets_fulfilled_at_timestamp(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'stock_quantity' => 5,
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_equip_timestamp_test',
        ]);

        $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        // Verify fulfilled_at is null before fulfilment
        $this->assertNull($item->order->fresh()->fulfilled_at);

        // Fulfil the order
        $this->service->fulfilOrder($item->order);

        // Verify fulfilled_at is set
        $item->order->refresh();
        $this->assertNotNull($item->order->fulfilled_at);
        $this->assertTrue($item->order->fulfilled_at->isToday());
    }
}
