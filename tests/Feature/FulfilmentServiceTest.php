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
use App\Services\HostingServiceCreator;
use App\Jobs\ProvisionHostingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for FulfilmentService.
 *
 * **Validates: Requirements 3.1, 5.4, 6.4, 10.1, 10.2, 10.3**
 *
 * Property 13: Fulfilment Leaves Stock Unchanged
 * Handling purchases and fulfilling orders SHALL NOT change any Product's stock_quantity.
 *
 * Property 14: Hosting Service Created With Pending Status and Domain Name
 * For any Hosting Product payment completed, the resulting Service record SHALL have
 * status "pending", domain_name from the OrderItem, and correct subscription fields.
 *
 * Property 15: Equipment Rental Creates Booking via BookingService
 * For any Equipment Rental Product payment completed, a Booking record SHALL be created
 * linked to the OrderItem, with correct dates and quantity.
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

        Bus::fake([ProvisionHostingJob::class]);

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
    // Property 13: Fulfilment Leaves Stock Unchanged
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 4.2, 4.3**
     *
     * One-off purchase handling does not change stock.
     */
    public function test_property_fulfilment_leaves_stock_unchanged_for_one_off(): void
    {
        foreach ([1, 10, 100, null] as $initialStock) {
            $product = Product::factory()->oneOff()->create(['stock_quantity' => $initialStock]);

            $item = $this->createOrderWithItem([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_type' => 'one_off',
                'price' => $product->price,
            ]);

            $this->service->handleOneOffPurchase($item);

            $this->assertSame($initialStock, $product->fresh()->stock_quantity);
        }
    }

    /**
     * **Validates: Requirements 4.2, 4.3**
     *
     * Equipment rental handling and fulfilOrder do not change stock.
     */
    public function test_property_fulfilment_leaves_stock_unchanged_for_rental(): void
    {
        $product = Product::factory()->equipmentRental()->create(['stock_quantity' => 10]);

        $item = $this->createOrderWithItem([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'equipment_rental',
            'price' => $product->price,
            'rental_start_date' => now()->addDay()->toDateString(),
            'rental_end_date' => now()->addDays(7)->toDateString(),
            'quantity' => 1,
        ]);

        $this->service->handleEquipmentRentalPurchase($item, $this->customer);
        $this->service->fulfilOrder($item->order);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_decrement_stock_no_longer_exists(): void
    {
        $this->assertFalse(method_exists(FulfilmentService::class, 'decrementStock'));
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

        $this->assertEquals('Pending', $service->status);
        $this->assertEquals('Web Hosting', $service->service_type);
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
            $this->assertEquals('Pending', $service->status, "Service status should be Pending for {$config['name']}");
            $this->assertEquals('Web Hosting', $service->service_type);
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

    public function test_hosting_purchase_is_idempotent_across_repeated_calls(): void
    {
        $item = $this->createOrderWithItem([
            'product_name' => 'Web Hosting',
            'product_type' => 'hosting',
            'billing_frequency' => 'monthly',
            'domain_name' => 'Repeat-Example.COM',
        ]);

        $first = $this->service->handleHostingPurchase($item, $this->customer);
        $second = $this->service->handleHostingPurchase($item->fresh(), $this->customer);

        $this->assertSame($first->service_id, $second->service_id);
        $this->assertEquals(1, Service::where('order_item_id', $item->id)->count());
        $this->assertEquals('repeat-example.com', $first->domain_name);
        $this->assertEquals('awaiting_fulfilment', $item->order->fresh()->fulfilment_status);
    }

    public function test_hosting_purchase_dispatches_exactly_one_provisioning_job_across_redeliveries(): void
    {
        $item = $this->createOrderWithItem(['product_type' => 'hosting', 'domain_name' => 'dispatch-once.com']);

        $first = $this->service->handleHostingPurchase($item, $this->customer);
        $this->service->handleHostingPurchase($item->fresh(), $this->customer);
        $this->service->handleHostingPurchase($item->fresh(), $this->customer);

        $this->assertSame('queued', $first->fresh()->provisioning_status);
        Bus::assertDispatchedTimes(ProvisionHostingJob::class, 1);
    }

    public function test_domain_conflict_hold_does_not_dispatch_provisioning(): void
    {
        $first = $this->createOrderWithItem(['product_type' => 'hosting', 'domain_name' => 'held.com']);
        $this->service->handleHostingPurchase($first, $this->customer);
        $second = $this->createOrderWithItem(['product_type' => 'hosting', 'domain_name' => 'held.com']);

        $this->assertNull($this->service->handleHostingPurchase($second, $this->customer));

        Bus::assertDispatchedTimes(ProvisionHostingJob::class, 1);
    }

    public function test_hosting_purchase_holds_order_when_domain_already_in_use(): void
    {
        $first = $this->createOrderWithItem(['product_type' => 'hosting', 'domain_name' => 'taken.com']);
        $holder = $this->service->handleHostingPurchase($first, $this->customer);

        $second = $this->createOrderWithItem(['product_type' => 'hosting', 'domain_name' => 'TAKEN.com']);

        $this->assertNull($this->service->handleHostingPurchase($second, $this->customer));
        // Redelivery must not duplicate the note.
        $this->assertNull($this->service->handleHostingPurchase($second->fresh(), $this->customer));

        $order = $second->order->fresh();
        $this->assertEquals('pending', $order->fulfilment_status);
        $this->assertEquals(1, substr_count($order->admin_notes, 'taken.com'));
        $this->assertStringContainsString("service #{$holder->service_id}", $order->admin_notes);
        $this->assertEquals(1, Service::where('domain_name', 'taken.com')->count());
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 15: Equipment Rental Creates Booking via BookingService
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 6.4, 10.1**
     *
     * Property 15: Equipment rental creates a Booking; a future start date leaves it 'confirmed'.
     */
    public function test_property_equipment_rental_creates_confirmed_booking_for_future_start(): void
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
        $this->assertEquals('confirmed', $booking->status);
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
     * **Validates: Requirements 10.1, 10.2, 10.3**
     *
     * Property 6: fulfilOrder wraps in transaction — completes the order.
     */
    public function test_property_fulfil_order_leaves_pending_service_and_completes_order(): void
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

        $this->assertEquals('Pending', $service->status);
        $this->assertEquals('awaiting_fulfilment', $item->order->fresh()->fulfilment_status);

        // Now admin fulfils the order (hosting service status is owned by provisioning, task 4.14)
        $this->service->fulfilOrder($item->order);

        // Verify order is completed with fulfilled_at timestamp
        $item->order->refresh();
        $this->assertEquals('completed', $item->order->fulfilment_status);
        $this->assertNotNull($item->order->fulfilled_at);
        $this->assertEquals('Pending', $service->fresh()->status);
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
     * Property 6: Transactional atomicity — when the injected creator fails, no records persist.
     */
    public function test_property_transactional_rollback_on_failure(): void
    {
        $creator = Mockery::mock(HostingServiceCreator::class);
        $creator->shouldReceive('forOrderItem')->once()->andThrow(new RuntimeException('creator failed'));
        $this->app->instance(HostingServiceCreator::class, $creator);

        $service = app(FulfilmentService::class);

        $item = $this->createOrderWithItem([
            'product_type' => 'hosting',
            'domain_name' => 'rollback.com',
        ]);

        $serviceCountBefore = Service::count();

        try {
            $service->handleHostingPurchase($item, $this->customer);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('creator failed', $e->getMessage());
        }

        $this->assertEquals($serviceCountBefore, Service::count());
        $this->assertEquals('pending', $item->order->fresh()->fulfilment_status);
        $this->assertNull($item->fresh()->service_id);
    }
}
