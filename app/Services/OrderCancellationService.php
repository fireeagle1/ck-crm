<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Service;
use App\Support\StripeCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single primitive for ending an order that will not be fulfilled.
 *
 * - failOrder(): checkout failed after the order was committed (payment failed, fulfilment cancelled).
 * - expire():    abandoned unpaid order (payment expired, fulfilment cancelled).
 * - cancel():    admin cancel (payment status untouched, fulfilment cancelled).
 *
 * Each restores stock once (via StockService), cancels bookings, releases assets and
 * deletes never-provisioned Pending hosting services created by the order. Stripe objects
 * are cancelled/expired best-effort after the outermost commit; failures are logged and
 * appended to orders.admin_notes. All methods are idempotent. Each returns true when it
 * changed the order and false when the order was already in (or past) the target state.
 */
class OrderCancellationService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly AssetAssignmentService $assetAssignmentService,
        private readonly StripeService $stripeService,
    ) {
    }

    public function failOrder(Order $order, ?StripeCreated $created = null): bool
    {
        return $this->end(
            $order,
            created: $created ?? new StripeCreated(),
            note: 'Checkout failed: order cancelled automatically.',
            eligible: fn (Order $o) => $o->fulfilment_status !== 'cancelled'
                && !in_array($o->payment_status, ['paid', 'paid_offline'], true),
            changes: ['payment_status' => 'failed', 'fulfilment_status' => 'cancelled'],
            stripe: true,
        );
    }

    public function expire(Order $order): bool
    {
        return $this->end(
            $order,
            created: new StripeCreated(),
            note: 'Auto-cancelled: payment not received within threshold.',
            eligible: fn (Order $o) => $o->payment_status === 'pending' && $o->fulfilment_status !== 'cancelled',
            changes: ['payment_status' => 'expired', 'fulfilment_status' => 'cancelled'],
            stripe: true,
        );
    }

    /** Admin cancel. Does not touch payment_status and never cancels Stripe objects (no refunds here). */
    public function cancel(Order $order): bool
    {
        return $this->end(
            $order,
            created: new StripeCreated(),
            note: null,
            eligible: fn (Order $o) => $o->fulfilment_status !== 'cancelled',
            changes: ['fulfilment_status' => 'cancelled'],
            stripe: false,
        );
    }

    /**
     * @param callable(Order): bool $eligible
     * @param array<string, string> $changes
     */
    private function end(
        Order $order,
        StripeCreated $created,
        ?string $note,
        callable $eligible,
        array $changes,
        bool $stripe,
    ): bool {
        $stripeObjects = null;

        $changed = DB::transaction(function () use ($order, $note, $eligible, $changes, $stripe, $created, &$stripeObjects) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if ($locked === null || !$eligible($locked)) {
                return false;
            }

            $items = $locked->items()->get();
            $itemIds = $items->pluck('id')->all();

            if ($stripe) {
                $stripeObjects = $this->collectStripeObjects($locked, $items->all(), $created);
            }

            $this->stockService->restoreForOrder($locked);
            $this->cancelBookings($itemIds, $items->pluck('booking_id')->filter()->all());
            $this->cleanupServices($itemIds);

            if ($note !== null) {
                $changes['admin_notes'] = $this->appendNote($locked->admin_notes, $note);
            }

            $locked->update($changes);

            return true;
        });

        if ($changed) {
            $order->refresh();

            if ($stripeObjects !== null && !$stripeObjects->isEmpty()) {
                $orderId = $order->getKey();
                DB::afterCommit(fn () => $this->compensateStripe($orderId, $stripeObjects));
            }
        }

        return $changed;
    }

    /** Merge caller-tracked objects with those persisted on the order. */
    private function collectStripeObjects(Order $order, array $items, StripeCreated $created): StripeCreated
    {
        $persisted = new StripeCreated(
            $order->stripe_checkout_session_id ?: null,
            array_values(array_filter(array_map(fn ($i) => $i->stripe_subscription_id ?: null, $items))),
        );

        return $persisted->merge($created);
    }

    /**
     * @param list<int> $itemIds
     * @param list<int> $bookingIds
     */
    private function cancelBookings(array $itemIds, array $bookingIds): void
    {
        if ($itemIds === [] && $bookingIds === []) {
            return;
        }

        $bookings = Booking::query()
            ->where(function ($q) use ($itemIds, $bookingIds) {
                $q->whereIn('order_item_id', $itemIds)->orWhereIn('id', $bookingIds);
            })
            ->whereIn('status', ['confirmed', 'active'])
            ->lockForUpdate()
            ->get();

        foreach ($bookings as $booking) {
            // Release reserved assets back to the pool, then free the booking's availability.
            $this->assetAssignmentService->releaseAssets($booking);
            $booking->update(['status' => 'cancelled']);
        }
    }

    /**
     * Delete never-provisioned Pending hosting services (they hold a unique domain);
     * mark any other Pending service created by the order as Cancelled.
     *
     * @param list<int> $itemIds
     */
    private function cleanupServices(array $itemIds): void
    {
        if ($itemIds === []) {
            return;
        }

        $services = Service::whereIn('order_item_id', $itemIds)
            ->where('status', ServiceStatus::Pending->value)
            ->lockForUpdate()
            ->get();

        foreach ($services as $service) {
            $neverProvisioned = $service->provisioning_status === null
                && ($service->cpanel_username === null || $service->cpanel_username === '');

            if ($neverProvisioned) {
                DB::table('order_items')->where('service_id', $service->service_id)->update(['service_id' => null]);
                $service->delete();
            } else {
                $service->update(['status' => ServiceStatus::Cancelled->value]);
            }
        }
    }

    private function compensateStripe(int $orderId, StripeCreated $objects): void
    {
        $failures = [];

        if ($objects->sessionId !== null) {
            try {
                $this->stripeService->expireCheckoutSession($objects->sessionId);
            } catch (\Throwable $e) {
                $failures[] = "could not expire Stripe checkout session {$objects->sessionId}";
                $this->logFailure($orderId, 'session', $objects->sessionId, $e);
            }
        }

        foreach ($objects->subscriptionIds as $subscriptionId) {
            try {
                $this->stripeService->cancelSubscription($subscriptionId);
            } catch (\Throwable $e) {
                $failures[] = "could not cancel Stripe subscription {$subscriptionId}";
                $this->logFailure($orderId, 'subscription', $subscriptionId, $e);
            }
        }

        if ($failures === []) {
            return;
        }

        try {
            DB::transaction(function () use ($orderId, $failures) {
                $order = Order::whereKey($orderId)->lockForUpdate()->first();
                if ($order === null) {
                    return;
                }

                $text = 'Manual Stripe cleanup needed: ' . implode('; ', $failures) . '.';
                $order->update(['admin_notes' => $this->appendNote($order->admin_notes, $text)]);
            });
        } catch (\Throwable $e) {
            Log::error('OrderCancellation: could not record compensation failure on order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function logFailure(int $orderId, string $kind, string $id, \Throwable $e): void
    {
        Log::error('OrderCancellation: Stripe compensation failed', [
            'order_id' => $orderId,
            'kind' => $kind,
            'stripe_id' => $id,
            'error' => $e->getMessage(),
        ]);
    }

    private function appendNote(?string $existing, string $note): string
    {
        return trim(($existing ? $existing . "\n\n" : '') . '[' . now()->format('Y-m-d H:i') . '] ' . $note);
    }
}
