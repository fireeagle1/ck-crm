<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProcessedWebhookEvent;
use App\Models\Service;
use App\Services\FulfilmentService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private FulfilmentService $fulfilmentService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Handle incoming Stripe webhook events.
     *
     * Verifies the Stripe signature (Req 9.4), checks idempotency via
     * ProcessedWebhookEvent (Req 9.1, 9.2), then processes the event
     * within a DB transaction (Req 10.1, 10.2, 10.3).
     *
     * Requirements: 9.1, 9.2, 9.4, 10.1, 10.2, 10.3
     */
    public function handle(Request $request): Response
    {
        // Verify Stripe signature before any processing (Req 9.4)
        $event = $this->stripeService->verifyWebhookSignature(
            $request->getContent(),
            $request->header('Stripe-Signature', '')
        );

        $eventId = $event->id;
        $eventType = $event->type;

        // Check if this event has already been processed (Req 9.2)
        if (ProcessedWebhookEvent::where('stripe_event_id', $eventId)->exists()) {
            return response('', 200);
        }

        // Insert the event_id row before processing (Req 9.1)
        $webhookRecord = ProcessedWebhookEvent::create([
            'stripe_event_id' => $eventId,
            'event_type' => $eventType,
            'processed_at' => now(),
        ]);

        try {
            // Wrap fulfilment processing in a DB transaction (Req 10.1)
            DB::transaction(function () use ($event) {
                match ($event->type) {
                    'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
                    'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
                    'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                    default => Log::info("Stripe webhook: Unhandled event type {$event->type}"),
                };
            });
        } catch (\Throwable $e) {
            // On transaction failure: delete event_id row to allow Stripe retry (Req 10.2, 10.3)
            $webhookRecord->delete();

            Log::error('Stripe webhook: Processing failed, event will be retried', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return response('', 200);
    }

    /**
     * Handle checkout.session.completed: update order payment status to "paid"
     * and trigger fulfilment for all item types (one-off, hosting, equipment rental).
     *
     * Requirements: 3.1, 6.4, 9.1, 10.1, 10.2, 10.3
     */
    private function handleCheckoutSessionCompleted(object $session): void
    {
        $order = Order::where('stripe_checkout_session_id', $session->id)->first();

        if (!$order) {
            Log::warning('Stripe webhook: Order not found for checkout session', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $order->update(['payment_status' => 'paid']);

        $customer = $order->customer;

        // Trigger fulfilment for each item based on product_type
        foreach ($order->items as $item) {
            match ($item->product_type) {
                'one_off' => $this->fulfilmentService->handleOneOffPurchase($item),
                'hosting' => $this->fulfilmentService->handleHostingPurchase($item, $customer),
                'equipment_rental' => $this->fulfilmentService->handleEquipmentRentalPurchase($item, $customer),
                default => Log::info('Stripe webhook: Unknown product_type for fulfilment', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'product_type' => $item->product_type,
                ]),
            };
        }
    }

    /**
     * Handle invoice.payment_failed: update the associated service status to "payment_failed"
     * and trigger payment failure notifications to admin and customer.
     *
     * Requirements: 8.1, 8.2
     */
    private function handleInvoicePaymentFailed(object $invoice): void
    {
        $subscriptionId = $invoice->subscription ?? null;
        $failureReason = $invoice->last_finalization_error->message ?? 'Payment failed';

        if (!$subscriptionId) {
            Log::info('Stripe webhook: invoice.payment_failed event without subscription ID');
            return;
        }

        $service = Service::where('stripe_subscription_id', $subscriptionId)->first();

        if (!$service) {
            Log::warning('Stripe webhook: Service not found for subscription', [
                'subscription_id' => $subscriptionId,
            ]);
            return;
        }

        $service->update(['status' => 'payment_failed']);

        // Find associated order to trigger payment failure notifications (Req 8.1, 8.2)
        $orderItem = OrderItem::where('stripe_subscription_id', $subscriptionId)->first();
        $order = $orderItem?->order;

        if ($order) {
            $this->notificationService->notifyPaymentFailure($order, $failureReason);
        } else {
            Log::warning('Stripe webhook: Order not found for payment failure notification', [
                'subscription_id' => $subscriptionId,
                'service_id' => $service->service_id,
            ]);
        }
    }

    /**
     * Handle customer.subscription.deleted: update the associated service status to "cancelled".
     *
     * Requirement: 9.3
     */
    private function handleSubscriptionDeleted(object $subscription): void
    {
        $service = Service::where('stripe_subscription_id', $subscription->id)->first();

        if (!$service) {
            Log::warning('Stripe webhook: Service not found for subscription', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        $service->update(['status' => 'cancelled']);
    }
}
