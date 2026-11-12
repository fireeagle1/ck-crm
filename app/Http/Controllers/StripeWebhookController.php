<?php

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProcessedWebhookEvent;
use App\Models\Service;
use App\Services\FulfilmentService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Database\UniqueConstraintViolationException;
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

        /** @var array<int, \Closure> $afterCommit */
        $afterCommit = [];

        try {
            // Marker insert and handlers share one transaction (Req 6.2).
            DB::transaction(function () use ($event, $eventId, $eventType, &$afterCommit) {
                ProcessedWebhookEvent::create([
                    'stripe_event_id' => $eventId,
                    'event_type' => $eventType,
                    'processed_at' => now(),
                ]);

                match ($event->type) {
                    'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
                    'invoice.paid' => $this->handleInvoicePaid($event->data->object),
                    'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object, $afterCommit),
                    'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                    default => Log::info("Stripe webhook: Unhandled event type {$event->type}"),
                };
            });
        } catch (UniqueConstraintViolationException) {
            // Already processed (or concurrently processing): skip (Req 6.3)
            return response('', 200);
        } catch (\Throwable $e) {
            // Transaction (including marker) rolled back; Stripe will retry (Req 6.4)
            report($e);

            return response('', 500);
        }

        // Side effects (emails etc.) run only after commit, each isolated (Req 6.5, 6.6)
        foreach ($afterCommit as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                report($e);
            }
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

        $clientReferenceId = $session->client_reference_id ?? null;
        if (!$order && $clientReferenceId) {
            $order = Order::find($clientReferenceId);
        }

        if (!$order) {
            $metadataOrderId = $session->metadata->order_id ?? null;

            if ($metadataOrderId) {
                // Metadata names an order we cannot see yet; throw so the
                // transaction rolls back and Stripe retries (Req 6.4).
                throw new \RuntimeException(
                    "Stripe webhook: order {$metadataOrderId} named in session metadata not found (session {$session->id})"
                );
            }

            Log::warning('Stripe webhook: checkout session does not belong to an order, ignoring', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $order->update(['payment_status' => 'paid']);

        $customer = $order->customer;
        $order->load('items.booking');

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
     * Handle invoice.paid: first invoice of a hosting subscription whose order
     * has no Checkout Session. Marks the order paid and provisions hosting items.
     * Other billing reasons, unknown orders, checkout-backed orders and orders
     * already paid are ignored.
     *
     * Requirements: 3.4, 6.2
     */
    private function handleInvoicePaid(object $invoice): void
    {
        if (($invoice->billing_reason ?? null) !== 'subscription_create') {
            return;
        }

        $details = $invoice->parent->subscription_details ?? $invoice->subscription_details ?? null;
        $subscriptionId = $invoice->subscription ?? ($details->subscription ?? null);
        $metadataOrderId = $details->metadata->order_id ?? null;

        $order = null;
        if ($subscriptionId) {
            $orderId = OrderItem::where('stripe_subscription_id', $subscriptionId)->value('order_id');
            $order = $orderId ? Order::find($orderId) : null;
        }
        if (!$order && $metadataOrderId) {
            $order = Order::find($metadataOrderId);
        }

        if (!$order) {
            Log::info('Stripe webhook: invoice.paid does not belong to an order, ignoring', [
                'invoice_id' => $invoice->id ?? null,
            ]);
            return;
        }

        // Checkout-backed orders are fulfilled by checkout.session.completed.
        if ($order->stripe_checkout_session_id || $order->payment_status === 'paid') {
            return;
        }

        $order->update(['payment_status' => 'paid']);

        $customer = $order->customer;
        $order->load('items.booking');

        foreach ($order->items as $item) {
            if ($item->product_type === 'hosting') {
                $this->fulfilmentService->handleHostingPurchase($item, $customer);
            }
        }
    }

    /**
     * Handle invoice.payment_failed: update the associated service status to "payment_failed"
     * and trigger payment failure notifications to admin and customer.
     *
     * Requirements: 8.1, 8.2, 6.5
     *
     * @param array<int, \Closure> $afterCommit
     */
    private function handleInvoicePaymentFailed(object $invoice, array &$afterCommit): void
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

        // Only Active or Pending services are suspended; Cancelled is never changed (Req 1.7)
        if (in_array($service->status, [ServiceStatus::Active->value, ServiceStatus::Pending->value], true)) {
            $service->update(['status' => ServiceStatus::Suspended->value]);
        }

        // Find associated order to trigger payment failure notifications (Req 8.1, 8.2)
        $orderItem = OrderItem::where('stripe_subscription_id', $subscriptionId)->first();
        $order = $orderItem?->order;

        if ($order) {
            $afterCommit[] = fn () => $this->notificationService->notifyPaymentFailure($order, $failureReason);
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

        $service->update(['status' => ServiceStatus::Cancelled->value]);
    }
}
