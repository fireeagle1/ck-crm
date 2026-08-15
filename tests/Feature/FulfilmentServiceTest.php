<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Services\BookingService;
use App\Services\FulfilmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for FulfilmentService.
 *
 * **Validates: Requirements 3.1, 5.4, 6.4, 10.1, 10.2, 10.3**
 *
 * Property 13: Stock Decrements by Exactly One Per Stockable Item Ordered
 * For any Order created containing a stockable Product (one_off or equipment_rental),
 * the Product's stock_quantity SHALL decrease by exactly one per OrderItem referencing
 * that Product.
 *
 * Property 14: Hosting Service Created With Pending Status and Domain Name
 * For any Hosting Product payment completed, the resulting Service record SHALL have
 * status "pending", domain_name from the OrderItem, and correct subscription fields.
 *
 * Property 15: Equipment Rental Creates Booking via BookingService
 * For any Equipment Rental Product payment completed, a Booking record SHALL be created
 * with status "active", linked to the OrderItem, with correct dates and quantity.
 *
 * Property 6: Transactional Atomicity
 * For any fulfilment operation, if any sub-step fails, then NO records from that
 * transaction SHALL persist in the database.
 */
class FulfilmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private FulfilmentService $service;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FulfilmentService::class);

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
            'rental_start_date' => now()->addDay()->toDateString(),
            'rental_end_date' => now()->addDays(7)->toDateString(),
            'quantity' => 1,
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
     * **Validates: Requirements 3.1, 10.1**
     *
     * Property 14: Hosting purchase creates a Service with status 'pending' and domain_name.
     */
    public function test_property_hosting_purchase_creates_pending_service_with_domain(): void
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
            'domain_name' => 'example.com',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertEquals('pending', $service->status);
        $this->assertEquals('example.com', $service->domain_name);
    }

    /**
     * **Validates: Requirements 3.1**
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
            'domain_name' => 'premium-hosting.com',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertEquals('Premium Web Hosting', $service->service_short);
    }

    /**
     * **Validates: Requirements 3.1**
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
            'domain_name' => 'datetest.com',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertTrue($service->start_date->isToday());
    }

    /**
     * **Validates: Requirements 3.1**
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
            'domain_name' => 'chargetest.com',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertEquals('49.99', $service->service_monthly_charge);
        $this->assertEquals('quarterly', $service->service_payment_frequency);
    }

    /**
     * **Validates: Requirements 3.1**
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
            'domain_name' => 'stripetest.com',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $this->assertEquals('sub_hosting_stripe_id_789', $service->stripe_subscription_id);
    }

    /**
     * **Validates: Requirements 3.1, 10.1**
     *
     * Property 14: Hosting purchase sets order fulfilment_status to 'awaiting_fulfilment'.
     */
    public function test_property_hosting_purchase_sets_order_awaiting_fulfilment(): void
    {
        $product = Product::factory()->hosting()->create();

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_hosting_order_status',
            'domain_name' => 'ordertest.com',
        ]);

        $this->service->handleHostingPurchase($item, $this->customer);

        $item->order->refresh();
        $this->assertEquals('awaiting_fulfilment', $item->order->fulfilment_status);
    }

    /**
     * **Validates: Requirements 3.1**
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
            'domain_name' => 'linktest.com',
        ]);

        $service = $this->service->handleHostingPurchase($item, $this->customer);

        $item->refresh();
        $this->assertEquals($service->service_id, $item->service_id);
    }

    /**
     * **Validates: Requirements 3.1, 10.1**
     *
     * Property 14 (property-based): For various hosting product configurations,
     * all service fields are correctly provisioned with pending status and domain.
     */
    public function test_property_hosting_auto_provision_correct_fields_for_various_configs(): void
    {
        $configs = [
            ['name' => 'Basic Hosting', 'price' => 9.99, 'billing_frequency' => 'monthly', 'sub_id' => 'sub_basic_123', 'domain' => 'basic.com'],
            ['name' => 'Pro Hosting', 'price' => 29.99, 'billing_frequency' => 'quarterly', 'sub_id' => 'sub_pro_456', 'domain' => 'pro-site.co.uk'],
            ['name' => 'Enterprise Hosting', 'price' => 199.99, 'billing_frequency' => 'annually', 'sub_id' => 'sub_ent_789', 'domain' => 'enterprise.org'],
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
                'domain_name' => $config['domain'],
            ]);

            $service = $this->service->handleHostingPurchase($item, $this->customer);

            // Verify all fields per Property 14 (updated for pending flow)
            $this->assertEquals('pending', $service->status, "Service status should be pending for {$config['name']}");
            $this->assertEquals($config['domain'], $service->domain_name, "domain_name mismatch for {$config['name']}");
            $this->assertEquals($config['name'], $service->service_short, "service_short mismatch for {$config['name']}");
            $this->assertTrue($service->start_date->isToday(), "start_date should be today for {$config['name']}");
            $this->assertEquals(number_format($config['price'], 2, '.', ''), $service->service_monthly_charge, "service_monthly_charge mismatch for {$config['name']}");
            $this->assertEquals($config['billing_frequency'], $service->service_payment_frequency, "service_payment_frequency mismatch for {$config['name']}");
            $this->assertEquals($config['sub_id'], $service->stripe_subscription_id, "stripe_subscription_id mismatch for {$config['name']}");

            // Verify order is awaiting_fulfilment (admin will approve via WHM later)
            $item->order->refresh();
            $this->assertEquals('awaiting_fulfilment', $item->order->fulfilment_status, "Order should be awaiting_fulfilment for {$config['name']}");

            // Verify item is linked
            $item->refresh();
            $this->assertEquals($service->service_id, $item->service_id, "OrderItem should be linked for {$config['name']}");
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 15: Equipment Rental Creates Booking via BookingService
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 6.4, 10.1**
     *
     * Property 15: Equipment rental creates a Booking with status 'active'.
     */
    public function test_property_equipment_rental_creates_active_booking(): void
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
            'rental_start_date' => now()->addDays(1)->toDateString(),
            'rental_end_date' => now()->addDays(5)->toDateString(),
            'quantity' => 1,
        ]);

        $booking = $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals('active', $booking->status);
    }

    /**
     * **Validates: Requirements 6.4**
     *
     * Property 15: Equipment rental booking has correct dates and quantity.
     */
    public function test_property_equipment_rental_booking_has_correct_fields(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'name' => 'Router Rental',
            'price' => 15.00,
            'stock_quantity' => 10,
        ]);

        $startDate = now()->addDays(2)->toDateString();
        $endDate = now()->addDays(7)->toDateString();

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => 'Router Rental',
            'product_type' => 'equipment_rental',
            'price' => 15.00,
            'rental_start_date' => $startDate,
            'rental_end_date' => $endDate,
            'quantity' => 2,
        ]);

        $booking = $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $this->assertEquals($startDate, $booking->start_date->toDateString());
        $this->assertEquals($endDate, $booking->end_date->toDateString());
        $this->assertEquals(2, $booking->quantity);
        $this->assertEquals($product->id, $booking->product_id);
    }

    /**
     * **Validates: Requirements 6.4, 10.1**
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
            'rental_start_date' => now()->addDays(1)->toDateString(),
            'rental_end_date' => now()->addDays(5)->toDateString(),
            'quantity' => 1,
        ]);

        $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $item->order->refresh();
        $this->assertEquals('awaiting_fulfilment', $item->order->fulfilment_status);
    }

    /**
     * **Validates: Requirements 6.4, 5.4**
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
            'rental_start_date' => now()->addDays(1)->toDateString(),
            'rental_end_date' => now()->addDays(5)->toDateString(),
            'quantity' => 1,
        ]);

        $this->service->handleEquipmentRentalPurchase($item, $this->customer);

        $product->refresh();
        $this->assertEquals(6, $product->stock_quantity);
    }

    /**
     * **Validates: Requirements 10.1, 10.2, 10.3**
     *
     * Property 6: fulfilOrder wraps in transaction — activates pending services and bookings.
     */
    public function test_property_fulfil_order_activates_pending_service_and_completes_order(): void
    {
        $product = Product::factory()->hosting()->create();

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'hosting',
            'price' => $product->price,
            'billing_frequency' => $product->billing_frequency,
            'stripe_subscription_id' => 'sub_hosting_fulfil_test',
            'domain_name' => 'fulfiltest.com',
        ]);

        // First, create the hosting purchase (pending service)
        $service = $this->service->handleHostingPurchase($item, $this->customer);

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
     * **Validates: Requirements 10.1, 10.2, 10.3**
     *
     * Property 6: fulfilOrder activates confirmed bookings within a transaction.
     */
    public function test_property_fulfil_order_activates_confirmed_bookings(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'stock_quantity' => 5,
        ]);

        $startDate = now()->addDays(1)->toDateString();
        $endDate = now()->addDays(5)->toDateString();

        $order = Order::create([
            'company_id' => $this->customer->company_id,
            'payment_status' => 'paid',
            'fulfilment_status' => 'pending',
            'total_amount' => 100.00,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => 20.00,
            'rental_start_date' => $startDate,
            'rental_end_date' => $endDate,
            'quantity' => 1,
        ]);

        // Create a confirmed booking manually
        $booking = Booking::create([
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'company_id' => $this->customer->company_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'quantity' => 1,
            'total_price' => 100.00,
            'status' => 'confirmed',
        ]);

        $item->update(['booking_id' => $booking->id]);

        // Fulfil the order
        $this->service->fulfilOrder($order);

        // Verify booking is now active
        $booking->refresh();
        $this->assertEquals('active', $booking->status);

        // Verify order is completed
        $order->refresh();
        $this->assertEquals('completed', $order->fulfilment_status);
        $this->assertNotNull($order->fulfilled_at);
    }

    /**
     * **Validates: Requirements 10.1, 10.2, 10.3**
     *
     * Property 6: Transactional atomicity — on failure, no records persist.
     */
    public function test_property_transactional_rollback_on_failure(): void
    {
        $product = Product::factory()->equipmentRental()->create([
            'stock_quantity' => 0, // Will cause RuntimeException on stock decrement
        ]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => $product->price,
            'rental_start_date' => now()->addDays(1)->toDateString(),
            'rental_end_date' => now()->addDays(5)->toDateString(),
            'quantity' => 1,
        ]);

        $bookingCountBefore = Booking::count();

        try {
            $this->service->handleEquipmentRentalPurchase($item, $this->customer);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            // Expected — stock is zero
        }

        // Verify no booking was created (transaction rolled back)
        $this->assertEquals($bookingCountBefore, Booking::count());

        // Verify order fulfilment_status unchanged
        $item->order->refresh();
        $this->assertEquals('pending', $item->order->fulfilment_status);
    }
}
