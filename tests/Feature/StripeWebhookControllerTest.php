<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Event;
use Stripe\StripeObject;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockStripeEvent(string $type, array $objectData): void
    {
        $eventObject = StripeObject::constructFrom($objectData);

        $eventData = StripeObject::constructFrom(['object' => $eventObject]);

        $event = Event::constructFrom([
            'id' => 'evt_test_' . uniqid(),
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

        // Stock should be decremented by the fulfilment service
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 9,
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
            'status' => 'payment_failed',
        ]);
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
            'status' => 'cancelled',
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
}
