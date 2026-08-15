<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\NotificationService;

class ProductObserver
{
    /**
     * Handle the Product "updated" event.
     *
     * If stock_quantity was changed and is now at or below the low_stock_threshold,
     * and the low_stock_notified flag is still false, trigger a one-time low-stock
     * alert via NotificationService.
     *
     * Requirements: 20.1, 20.2, 20.3 (low-stock notification logic)
     * Requirements: 7.4, 7.5 (once-per-breach alert and flag reset cycle)
     */
    public function updated(Product $product): void
    {
        // Only act when stock_quantity actually changed
        if (!$product->wasChanged('stock_quantity')) {
            return;
        }

        // No threshold configured — nothing to check
        $threshold = $product->low_stock_threshold;
        if ($threshold === null) {
            return;
        }

        // Stock is at or below threshold and we haven't notified yet
        if ($product->stock_quantity <= $threshold && !$product->low_stock_notified) {
            app(NotificationService::class)->notifyAdminLowStock($product);
        }
    }
}
