<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FulfilmentService
{
    public function __construct(
        protected BookingService $bookingService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * Handle hosting purchase: create a pending Service record with domain_name and service_type 'hosting'.
     * The admin will later approve and provision via WHM.
     *
     * Requirements: 3.1, 10.1, 10.2, 10.3
     */
    public function handleHostingPurchase(OrderItem $item, Customer $customer): Service
    {
        try {
            return DB::transaction(function () use ($item, $customer) {
                $service = Service::create([
                    'company_id' => $customer->company_id,
                    'service_short' => $item->product_name,
                    'service_type' => 'hosting',
                    'status' => 'pending',
                    'domain_name' => $item->domain_name,
                    'start_date' => now(),
                    'service_monthly_charge' => $item->price,
                    'service_payment_frequency' => $item->billing_frequency,
                    'stripe_subscription_id' => $item->stripe_subscription_id,
                ]);

                $item->update(['service_id' => $service->service_id]);

                $item->order->update(['fulfilment_status' => 'awaiting_fulfilment']);

                return $service;
            });
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
     * Handle equipment rental purchase: create a Booking via BookingService and set status 'active'.
     *
     * Requirements: 6.4, 10.1, 10.2, 10.3
     */
    public function handleEquipmentRentalPurchase(OrderItem $item, Customer $customer): Booking
    {
        try {
            return DB::transaction(function () use ($item, $customer) {
                $product = $item->product;

                if (!$product) {
                    throw new RuntimeException(
                        "Product not found for order item \"{$item->product_name}\"."
                    );
                }

                $startDate = $item->rental_start_date;
                $endDate = $item->rental_end_date;
                $quantity = $item->quantity ?? 1;

                // Create booking via BookingService with pessimistic locking
                $booking = $this->bookingService->createBooking(
                    $item,
                    $product,
                    $startDate,
                    $endDate,
                    $quantity,
                    null, // signature data handled at checkout level
                    null  // agreement text handled at checkout level
                );

                // Set booking status to 'active' since payment is confirmed
                $booking->update(['status' => 'active']);

                $item->order->update(['fulfilment_status' => 'awaiting_fulfilment']);

                // Decrement stock for equipment rental items
                $this->decrementStock($product);

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
     * Handle one-off purchase: decrement stock. The order's fulfilment_status stays "pending".
     *
     * Requirements: 5.1, 5.4, 10.1
     */
    public function handleOneOffPurchase(OrderItem $item): void
    {
        DB::transaction(function () use ($item) {
            if ($item->product) {
                $this->decrementStock($item->product);
            }
        });
    }

    /**
     * Mark an order as fulfilled: set fulfilment_status to "completed", record timestamp,
     * and activate any associated pending services. Sends fulfilment notification to customer.
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

                // Activate any pending services associated with this order's items
                $order->items()->whereNotNull('service_id')->each(function (OrderItem $item) {
                    $service = $item->service;
                    if ($service && $service->status === 'pending') {
                        $service->update(['status' => 'Active']);
                    }
                });

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

    /**
     * Atomically decrement a product's stock quantity.
     * Uses a where guard to prevent going below zero.
     *
     * @throws RuntimeException if stock is already at zero.
     */
    public function decrementStock(Product $product): void
    {
        // stock_quantity of null means unlimited — no decrement needed
        if ($product->stock_quantity === null) {
            return;
        }

        $affected = Product::where('id', $product->id)
            ->where('stock_quantity', '>', 0)
            ->decrement('stock_quantity');

        if ($affected === 0) {
            Log::warning('FulfilmentService: Stock decrement failed — stock already at zero', [
                'product_id' => $product->id,
                'product_name' => $product->name,
            ]);

            throw new RuntimeException(
                "Product \"{$product->name}\" is out of stock."
            );
        }
    }
}
