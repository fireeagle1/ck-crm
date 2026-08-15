<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FulfilmentService
{
    /**
     * Handle hosting purchase: create an active Service record and mark order fulfilled.
     *
     * Requirements: 6.1, 6.2, 6.3
     */
    public function handleHostingPurchase(OrderItem $item, Customer $customer): Service
    {
        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => $item->product_name,
            'status' => 'Active',
            'start_date' => now(),
            'service_monthly_charge' => $item->price,
            'service_payment_frequency' => $item->billing_frequency,
            'stripe_subscription_id' => $item->stripe_subscription_id,
        ]);

        $item->update(['service_id' => $service->service_id]);

        $item->order->update(['fulfilment_status' => 'completed']);

        return $service;
    }

    /**
     * Handle equipment rental purchase: create a pending Service and mark order awaiting fulfilment.
     *
     * Requirements: 7.1, 7.2, 7.5
     */
    public function handleEquipmentRentalPurchase(OrderItem $item, Customer $customer): Service
    {
        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => $item->product_name,
            'status' => 'pending',
            'start_date' => now(),
            'service_monthly_charge' => $item->price,
            'service_payment_frequency' => $item->billing_frequency,
            'stripe_subscription_id' => $item->stripe_subscription_id,
        ]);

        $item->update(['service_id' => $service->service_id]);

        $item->order->update(['fulfilment_status' => 'awaiting_fulfilment']);

        // Decrement stock for equipment rental items
        if ($item->product) {
            $this->decrementStock($item->product);
        }

        return $service;
    }

    /**
     * Handle one-off purchase: decrement stock. The order's fulfilment_status stays "pending".
     *
     * Requirements: 5.1, 5.4
     */
    public function handleOneOffPurchase(OrderItem $item): void
    {
        if ($item->product) {
            $this->decrementStock($item->product);
        }
    }

    /**
     * Mark an order as fulfilled: set fulfilment_status to "completed", record timestamp,
     * and activate any associated pending services.
     *
     * Requirements: 5.1
     */
    public function fulfilOrder(Order $order): void
    {
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
