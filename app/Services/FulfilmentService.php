<?php

namespace App\Services;

use App\Exceptions\DomainAlreadyInUseException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FulfilmentService
{
    public function __construct(
        protected BookingService $bookingService,
        protected NotificationService $notificationService,
        protected HostingServiceCreator $hostingServiceCreator,
        protected ProvisioningDispatcher $provisioningDispatcher,
    ) {}

    /**
     * Handle hosting purchase through HostingServiceCreator (idempotent per order item).
     * Returns null when the order is held because the domain is already in use.
     *
     * Requirements: 3.3, 3.4, 3.5
     */
    public function handleHostingPurchase(OrderItem $item, Customer $customer): ?Service
    {
        try {
            return DB::transaction(function () use ($item, $customer) {
                $service = $this->hostingServiceCreator->forOrderItem($item, $customer);

                $item->order->update(['fulfilment_status' => 'awaiting_fulfilment']);

                // Dispatch only once the commit lands. The dispatcher's atomic guard
                // makes repeat deliveries a no-op.
                DB::afterCommit(fn () => $this->provisioningDispatcher->dispatch(
                    $service,
                    null,
                    ProvisioningDispatcher::CONTEXT_WEBHOOK,
                ));

                return $service;
            });
        } catch (DomainAlreadyInUseException $e) {
            $this->holdOrderForDomainConflict($item->order, $e);

            return null;
        } catch (\Throwable $e) {
            Log::error('FulfilmentService: handleHostingPurchase transaction failed — full rollback', [
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'company_id' => $customer->company_id,
                'domain_name' => $item->domain_name,
                'product_name' => $item->product_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Leave the order unfulfilled, record why, and tell admins. Repeat deliveries add the note once.
     */
    protected function holdOrderForDomainConflict(Order $order, DomainAlreadyInUseException $e): void
    {
        $line = "Held: domain {$e->domain} already in use"
            . ($e->existingServiceId !== null ? " by service #{$e->existingServiceId}" : '') . '.';

        $order->refresh();
        $notes = (string) $order->admin_notes;

        if (str_contains($notes, $line)) {
            return;
        }

        $order->update(['admin_notes' => trim($notes . "\n" . $line)]);

        Log::warning('FulfilmentService: hosting order held, domain already in use', [
            'order_id' => $order->id,
            'domain' => $e->domain,
            'existing_service_id' => $e->existingServiceId,
        ]);

        $this->notificationService->notifyAdminHostingDomainConflict($order, $e->domain, $e->existingServiceId);
    }

    /**
     * Handle equipment rental purchase: create a Booking via BookingService and set status 'active'.
     *
     * Requirements: 6.4, 10.1, 10.2, 10.3
     */
    public function handleEquipmentRentalPurchase(OrderItem $item, Customer $customer): Booking
    {
        try {
            return DB::transaction(function () use ($item, $customer) {
                // The booking was already created during checkout (CheckoutService::handleRentalItems).
                // This method is called after payment confirmation via the Stripe webhook.
                // We just need to activate the existing booking and advance its fulfilment stage.
                $booking = $item->booking;

                if (!$booking) {
                    // Fallback: if no booking exists yet (edge case or legacy flow), create one
                    $product = $item->product;

                    if (!$product) {
                        throw new RuntimeException(
                            "Product not found for order item \"{$item->product_name}\"."
                        );
                    }

                    $booking = $this->bookingService->createBooking(
                        $item,
                        $product,
                        $item->rental_start_date,
                        $item->rental_end_date,
                        $item->quantity ?? 1,
                        null,
                        null
                    );
                }

                // Payment is now confirmed — activate the booking if start_date is today or past
                $newStatus = $booking->start_date->lte(today()) ? 'active' : 'confirmed';
                $booking->update(['status' => $newStatus]);

                // Advance fulfilment stage: if assets are assigned (packing stage) and now paid,
                // we can advance to 'ready'. If still at 'ordered', advance to 'packing'.
                $this->advanceFulfilmentAfterPayment($booking);

                $item->order->update(['fulfilment_status' => 'awaiting_fulfilment']);

                return $booking;
            });
        } catch (\Throwable $e) {
            Log::error('FulfilmentService: handleEquipmentRentalPurchase transaction failed — full rollback', [
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'company_id' => $customer->company_id,
                'product_name' => $item->product_name,
                'rental_start_date' => $item->rental_start_date,
                'rental_end_date' => $item->rental_end_date,
                'quantity' => $item->quantity,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * After payment is confirmed, advance the booking's fulfilment stage appropriately.
     *
     * - If at 'ordered' with no assets assigned: stays at 'ordered' (needs manual assignment)
     * - If at 'ordered' with assets assigned: advance to 'packing'
     * - If at 'packing' with sufficient assets: advance to 'ready'
     */
    protected function advanceFulfilmentAfterPayment(Booking $booking): void
    {
        $booking->loadCount('assignedAssets');

        if ($booking->fulfilment_stage === 'ordered' && $booking->assigned_assets_count > 0) {
            $booking->update(['fulfilment_stage' => 'packing']);
        }

        if ($booking->fulfilment_stage === 'packing' && $booking->assigned_assets_count >= $booking->quantity) {
            $booking->update(['fulfilment_stage' => 'ready']);
        }
    }

    /**
     * Handle one-off purchase: no stock handling (stock is not tracked here).
     * The order's fulfilment_status stays "pending".
     *
     * Requirements: 4.2, 4.3
     */
    public function handleOneOffPurchase(OrderItem $item): void
    {
        Log::info('FulfilmentService: one-off purchase recorded, no stock handling', [
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
        ]);
    }

    /**
     * Mark an order as fulfilled: set fulfilment_status to "completed", record timestamp,
     * and activate confirmed bookings. Hosting services are not touched. Sends fulfilment notification to customer.
     *
     * Wrapped in DB::transaction for atomicity. On failure, full rollback occurs
     * and details are logged with event context.
     *
     * Requirements: 5.1, 10.1, 10.2, 10.3
     */
    public function fulfilOrder(Order $order): void
    {
        try {
            DB::transaction(function () use ($order) {
                $order->update([
                    'fulfilment_status' => 'completed',
                    'fulfilled_at' => now(),
                ]);

                // Hosting service status is owned by provisioning (ServiceStatus::Pending -> Active);
                // fulfilment never touches it.

                // Activate any confirmed bookings associated with this order's items
                $order->items()->whereNotNull('booking_id')->each(function (OrderItem $item) {
                    $booking = $item->booking;
                    if ($booking && $booking->status === 'confirmed') {
                        $booking->update(['status' => 'active']);
                    }
                });
            });

            // Send fulfilment notification to customer (outside transaction)
            $this->notificationService->notifyCustomerOrderFulfilled($order);
        } catch (\Throwable $e) {
            Log::error('FulfilmentService: fulfilOrder transaction failed — full rollback', [
                'order_id' => $order->id,
                'company_id' => $order->company_id,
                'fulfilment_status' => $order->fulfilment_status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
