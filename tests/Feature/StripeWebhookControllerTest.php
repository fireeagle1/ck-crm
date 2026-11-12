<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProcessedWebhookEvent;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Mail\PaymentFailedAdmin;
use App\Services\FulfilmentService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Stripe\Event;
use Stripe\StripeObject;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripeEvent(string $type, array $objectData, ?string $eventId = null): void
    {
        $eventObject = StripeObject::constructFrom($objectData);

        $eventData = StripeObject::constructFrom(['object' => $eventObject]);

        $event = Event::constructFrom([
            'id' => $eventId ?? 'evt_test_' . uniqid(),
            'type' => $type,
            'data' => ['object' => $objectData],
        ]);
        // Override data->object with our constructed object for property access
        $event->data->object = $eventObject;

        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn($event);

        $this->app->instance(StripeService::class, $stripeService);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 9.5: Signature verification
    // ──────────────────────────────────────────────────────────────────

    public function test_webhook_returns_400_on_invalid_signature(): void
    {
        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andThrow(new \Symfony\Component\HttpKernel\Exception\HttpException(400, 'Invalid webhook signature.'));

        $this->app->instance(StripeService::class, $stripeService);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'invalid_sig',
        ]);

        $response->assertStatus(400);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 9.1: checkout.session.completed
    // ──────────────────────────────────────────────────────────────────

    public function test_checkout_session_completed_updates_order_to_paid(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'pending',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => 'cs_test_123',
            'total_amount' => 29.99,
        ]);

        $this->mockStripeEvent('checkout.session.completed', ['id' => 'cs_test_123']);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_checkout_session_completed_triggers_fulfilment_for_one_off_items(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->oneOff()->create(['stock_quantity' => 10]);

        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'pending',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => 'cs_test_456',
            'total_amount' => 29.99,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'one_off',
            'price' => 29.99,
        ]);

        $this->mockStripeEvent('checkout.session.completed', ['id' => 'cs_test_456']);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);

        // Fulfilment no longer decrements stock
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 9.4: Non-existent records
    // ──────────────────────────────────────────────────────────────────

    public function test_checkout_session_completed_returns_200_for_nonexistent_order(): void
    {
        $this->mockStripeEvent('checkout.session.completed', ['id' => 'cs_nonexistent_999']);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 9.2: invoice.payment_failed
    // ──────────────────────────────────────────────────────────────────

    public function test_invoice_payment_failed_updates_service_status(): void
    {
        $customer = Customer::factory()->create();
        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Hosting Plan',
            'status' => 'Active',
            'start_date' => now(),
            'service_monthly_charge' => 9.99,
            'service_payment_frequency' => 'monthly',
            'stripe_subscription_id' => 'sub_test_789',
        ]);

        $this->mockStripeEvent('invoice.payment_failed', [
            'id' => 'inv_test_001',
            'subscription' => 'sub_test_789',
        ]);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('services', [
            'service_id' => $service->service_id,
            'status' => 'Suspended',
        ]);
    }

    public function test_invoice_payment_failed_never_changes_cancelled_service(): void
    {
        $customer = Customer::factory()->create();
        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Hosting Plan',
            'status' => 'Cancelled',
            'start_date' => now(),
            'service_monthly_charge' => 9.99,
            'service_payment_frequency' => 'monthly',
            'stripe_subscription_id' => 'sub_test_cxl',
        ]);

        $this->mockStripeEvent('invoice.payment_failed', ['id' => 'inv_cxl', 'subscription' => 'sub_test_cxl']);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        $this->assertDatabaseHas('services', ['service_id' => $service->service_id, 'status' => 'Cancelled']);
    }

    public function test_checkout_session_completed_falls_back_to_client_reference_id(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'pending',
            'fulfilment_status' => 'pending',
            'total_amount' => 29.99,
        ]);

        $this->mockStripeEvent('checkout.session.completed', [
            'id' => 'cs_ref_1',
            'client_reference_id' => (string) $order->id,
        ]);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
    }

    public function test_checkout_session_with_metadata_for_missing_order_returns_500_and_rolls_back_marker(): void
    {
        $this->mockStripeEvent('checkout.session.completed', [
            'id' => 'cs_meta_1',
            'metadata' => ['order_id' => '999999'],
        ], 'evt_meta_001');

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(500);
        $this->assertDatabaseMissing('processed_webhook_events', ['stripe_event_id' => 'evt_meta_001']);
    }

    public function test_invoice_payment_failed_returns_200_for_nonexistent_service(): void
    {
        $this->mockStripeEvent('invoice.payment_failed', [
            'id' => 'inv_test_002',
            'subscription' => 'sub_nonexistent_999',
        ]);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirements 3.4, 6.2: invoice.paid
    // ──────────────────────────────────────────────────────────────────

    private function hostingOrderWithSubscription(string $subId, ?string $sessionId = null): Order
    {
        $customer = Customer::factory()->create();
        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'pending',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => $sessionId,
            'total_amount' => 9.99,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hosting',
            'product_type' => 'hosting',
            'price' => 9.99,
            'stripe_subscription_id' => $subId,
        ]);

        return $order;
    }

    private function fulfilmentExpecting(int $times): void
    {
        $fulfilment = Mockery::mock(FulfilmentService::class);
        $fulfilment->shouldReceive('handleHostingPurchase')->times($times)->andReturn(null);
        $this->app->instance(FulfilmentService::class, $fulfilment);
    }

    public function test_invoice_paid_subscription_create_marks_order_paid_and_provisions_hosting(): void
    {
        $order = $this->hostingOrderWithSubscription('sub_paid_001');
        $this->fulfilmentExpecting(1);

        $this->mockStripeEvent('invoice.paid', [
            'id' => 'in_paid_1',
            'billing_reason' => 'subscription_create',
            'subscription' => 'sub_paid_001',
        ]);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
    }

    public function test_invoice_paid_finds_order_via_subscription_metadata(): void
    {
        $order = $this->hostingOrderWithSubscription('sub_other');
        $this->fulfilmentExpecting(1);

        $this->mockStripeEvent('invoice.paid', [
            'id' => 'in_paid_2',
            'billing_reason' => 'subscription_create',
            'subscription' => 'sub_unknown',
            'subscription_details' => ['metadata' => ['order_id' => (string) $order->id]],
        ]);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
    }

    public function test_invoice_paid_with_other_billing_reason_is_ignored(): void
    {
        $order = $this->hostingOrderWithSubscription('sub_paid_003');
        $this->fulfilmentExpecting(0);

        $this->mockStripeEvent('invoice.paid', [
            'id' => 'in_paid_3',
            'billing_reason' => 'subscription_cycle',
            'subscription' => 'sub_paid_003',
        ]);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
    }

    public function test_invoice_paid_for_checkout_backed_order_is_ignored(): void
    {
        $order = $this->hostingOrderWithSubscription('sub_paid_004', 'cs_has_session');
        $this->fulfilmentExpecting(0);

        $this->mockStripeEvent('invoice.paid', [
            'id' => 'in_paid_4',
            'billing_reason' => 'subscription_create',
            'subscription' => 'sub_paid_004',
        ]);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
    }

    public function test_invoice_paid_with_no_matching_order_returns_200(): void
    {
        $this->fulfilmentExpecting(0);

        $this->mockStripeEvent('invoice.paid', [
            'id' => 'in_paid_5',
            'billing_reason' => 'subscription_create',
            'subscription' => 'sub_nobody',
        ]);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
    }

    public function test_invoice_paid_redelivery_has_no_further_effect(): void
    {
        $order = $this->hostingOrderWithSubscription('sub_paid_006');
        $this->fulfilmentExpecting(1);

        $event = Event::constructFrom([
            'id' => 'evt_paid_006',
            'type' => 'invoice.paid',
            'data' => ['object' => ['id' => 'in_paid_6']],
        ]);
        $event->data->object = StripeObject::constructFrom([
            'id' => 'in_paid_6',
            'billing_reason' => 'subscription_create',
            'subscription' => 'sub_paid_006',
        ]);
        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('verifyWebhookSignature')->twice()->andReturn($event);
        $this->app->instance(StripeService::class, $stripeService);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertSame(1, ProcessedWebhookEvent::where('stripe_event_id', 'evt_paid_006')->count());
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 9.3: customer.subscription.deleted
    // ──────────────────────────────────────────────────────────────────

    public function test_subscription_deleted_updates_service_status_to_cancelled(): void
    {
        $customer = Customer::factory()->create();
        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Equipment Rental',
            'status' => 'Active',
            'start_date' => now(),
            'service_monthly_charge' => 49.99,
            'service_payment_frequency' => 'monthly',
            'stripe_subscription_id' => 'sub_test_del_001',
        ]);

        $this->mockStripeEvent('customer.subscription.deleted', [
            'id' => 'sub_test_del_001',
        ]);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('services', [
            'service_id' => $service->service_id,
            'status' => 'Cancelled',
        ]);
    }

    public function test_subscription_deleted_returns_200_for_nonexistent_service(): void
    {
        $this->mockStripeEvent('customer.subscription.deleted', [
            'id' => 'sub_nonexistent_del_999',
        ]);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────
    // Unhandled event types
    // ──────────────────────────────────────────────────────────────────

    public function test_unhandled_event_type_returns_200(): void
    {
        $this->mockStripeEvent('payment_intent.created', ['id' => 'pi_test_001']);

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 9.1, 9.2: Webhook idempotency
    // ──────────────────────────────────────────────────────────────────

    public function test_webhook_stores_event_id_in_processed_events_table(): void
    {
        $this->mockStripeEvent('checkout.session.completed', ['id' => 'cs_nonexistent'], 'evt_idempotency_001');

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('processed_webhook_events', [
            'stripe_event_id' => 'evt_idempotency_001',
            'event_type' => 'checkout.session.completed',
        ]);
    }

    public function test_duplicate_event_returns_200_without_reprocessing(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->oneOff()->create(['stock_quantity' => 10]);

        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'pending',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => 'cs_dup_test',
            'total_amount' => 29.99,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => 'one_off',
            'price' => 29.99,
        ]);

        // Set up mock that can be called twice (for both webhook calls)
        $eventObject = StripeObject::constructFrom(['id' => 'cs_dup_test']);
        $event = Event::constructFrom([
            'id' => 'evt_dup_001',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_dup_test']],
        ]);
        $event->data->object = $eventObject;

        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('verifyWebhookSignature')
            ->twice()
            ->andReturn($event);

        $this->app->instance(StripeService::class, $stripeService);

        // First processing
        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);
        $response->assertStatus(200);

        // Stock unchanged by fulfilment
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);

        // Second processing with same event_id — should be skipped
        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);
        $response->assertStatus(200);

        // Still unchanged after the duplicate delivery
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_previously_processed_event_returns_200_immediately(): void
    {
        // Pre-insert a processed event record
        ProcessedWebhookEvent::create([
            'stripe_event_id' => 'evt_already_processed',
            'event_type' => 'checkout.session.completed',
            'processed_at' => now(),
        ]);

        $this->mockStripeEvent('checkout.session.completed', ['id' => 'cs_already'], 'evt_already_processed');

        $response = $this->postJson('/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid_sig',
        ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirements 6.1-6.6: transactional, exactly-once processing
    // ──────────────────────────────────────────────────────────────────

    public function test_stripe_event_id_column_is_unique(): void
    {
        ProcessedWebhookEvent::create([
            'stripe_event_id' => 'evt_unique_001',
            'event_type' => 'x',
            'processed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        ProcessedWebhookEvent::create([
            'stripe_event_id' => 'evt_unique_001',
            'event_type' => 'x',
            'processed_at' => now(),
        ]);
    }

    public function test_handler_failure_rolls_back_changes_and_marker_and_returns_500(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'pending',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => 'cs_fail_test',
            'total_amount' => 29.99,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Thing',
            'product_type' => 'one_off',
            'price' => 29.99,
        ]);

        $fulfilment = Mockery::mock(FulfilmentService::class);
        $fulfilment->shouldReceive('handleOneOffPurchase')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(FulfilmentService::class, $fulfilment);

        $this->mockStripeEvent('checkout.session.completed', ['id' => 'cs_fail_test'], 'evt_fail_001');

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])
            ->assertStatus(500);

        // Order update and marker both rolled back so Stripe can retry
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
        $this->assertDatabaseMissing('processed_webhook_events', ['stripe_event_id' => 'evt_fail_001']);
    }

    public function test_unique_violation_on_marker_returns_200_and_skips_processing(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'pending',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => 'cs_skip_test',
            'total_amount' => 29.99,
        ]);
        ProcessedWebhookEvent::create([
            'stripe_event_id' => 'evt_skip_001',
            'event_type' => 'checkout.session.completed',
            'processed_at' => now(),
        ]);

        $this->mockStripeEvent('checkout.session.completed', ['id' => 'cs_skip_test'], 'evt_skip_001');

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])
            ->assertStatus(200);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
        $this->assertSame(1, ProcessedWebhookEvent::where('stripe_event_id', 'evt_skip_001')->count());
    }

    public function test_payment_failed_email_is_sent_after_commit_once_for_duplicate_delivery(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $customer = Customer::factory()->create();
        Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Hosting Plan',
            'status' => 'Active',
            'start_date' => now(),
            'service_monthly_charge' => 9.99,
            'service_payment_frequency' => 'monthly',
            'stripe_subscription_id' => 'sub_mail_001',
        ]);
        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'paid',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => 'cs_mail_001',
            'total_amount' => 9.99,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hosting',
            'product_type' => 'hosting',
            'price' => 9.99,
            'stripe_subscription_id' => 'sub_mail_001',
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_mail_001',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['id' => 'in_1']],
        ]);
        $event->data->object = StripeObject::constructFrom(['id' => 'in_1', 'subscription' => 'sub_mail_001']);

        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('verifyWebhookSignature')->twice()->andReturn($event);
        $this->app->instance(StripeService::class, $stripeService);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        Mail::assertQueued(PaymentFailedAdmin::class, 1);

        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        Mail::assertQueued(PaymentFailedAdmin::class, 1);
    }

    public function test_after_commit_failure_is_isolated_and_keeps_committed_work(): void
    {
        Mail::fake();

        User::factory()->create(['is_admin' => true]);
        $customer = Customer::factory()->create();
        Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Hosting Plan',
            'status' => 'Active',
            'start_date' => now(),
            'service_monthly_charge' => 9.99,
            'service_payment_frequency' => 'monthly',
            'stripe_subscription_id' => 'sub_mail_002',
        ]);
        $order = Order::create([
            'company_id' => $customer->company_id,
            'payment_status' => 'paid',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => 'cs_mail_002',
            'total_amount' => 9.99,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hosting',
            'product_type' => 'hosting',
            'price' => 9.99,
            'stripe_subscription_id' => 'sub_mail_002',
        ]);

        $notification = Mockery::mock(NotificationService::class);
        $notification->shouldReceive('notifyPaymentFailure')->once()->andThrow(new \RuntimeException('smtp down'));
        $this->app->instance(NotificationService::class, $notification);

        $this->mockStripeEvent('invoice.payment_failed', ['id' => 'in_2', 'subscription' => 'sub_mail_002'], 'evt_mail_002');

        // After-commit failure is reported but does not undo the committed work
        $this->postJson('/stripe/webhook', [], ['HTTP_STRIPE_SIGNATURE' => 'valid_sig'])->assertStatus(200);
        $this->assertDatabaseHas('processed_webhook_events', ['stripe_event_id' => 'evt_mail_002']);
        Mail::assertNothingQueued();
    }
}
