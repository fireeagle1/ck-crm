<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\FulfilmentService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private FulfilmentService $fulfilmentService
    ) {}

    /**
     * Handle incoming Stripe webhook events.
     *
     * Verifies the signature via StripeService, then dispatches
     * to the appropriate handler based on event type.
     *
     * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5
     */
    public function handle(Request $request): Response
    {
        $event = $this->stripeService->verifyWebhookSignature(
            $request->getContent(),
            $request->header('Stripe-Signature', '')
        );

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => Log::info("Stripe webhook: Unhandled event type {$event->type}"),
        };

        return response('', 200);
    }

    /**
     * Handle checkout.session.completed: update order payment status to "paid"
     * and trigger fulfilment for one-off items.
     *
     * Requirement: 9.1
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

        // Trigger fulfilment for one-off items
        foreach ($order->items as $item) {
            if ($item->product_type === 'one_off') {
                $this->fulfilmentService->handleOneOffPurchase($item);
            }
        }
    }

    /**
     * Handle invoice.payment_failed: update the associated service status to "payment_failed".
     *
     * Requirement: 9.2
     */
    private function handleInvoicePaymentFailed(object $invoice): void
    {
        $subscriptionId = $invoice->subscription ?? null;

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
