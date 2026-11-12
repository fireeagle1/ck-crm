<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Single place for stock changes tied to order items.
 *
 * Both methods MUST be called inside a caller-owned DB transaction so the
 * product row lock is held until the surrounding work commits.
 */
class StockService
{
    /**
     * Lock the product, check stock, decrement and record the reservation on the item.
     *
     * Unlimited stock (stock_quantity null) reserves 0.
     *
     * @throws \InvalidArgumentException If stock is insufficient.
     * @throws LogicException If no transaction is active.
     */
    public function reserve(Product $product, int $qty, OrderItem $item): void
    {
        $this->assertInTransaction();

        $locked = Product::lockForUpdate()->find($product->getKey());

        if ($locked === null || $locked->stock_quantity === null) {
            $item->forceFill(['stock_reserved_quantity' => 0])->save();

            return;
        }

        if ($locked->stock_quantity < $qty) {
            throw new \InvalidArgumentException(
                "Insufficient stock for '{$locked->name}'. Only {$locked->stock_quantity} units available."
            );
        }

        $locked->decrement('stock_quantity', $qty);
        $product->stock_quantity = $locked->stock_quantity;
        $item->forceFill(['stock_reserved_quantity' => $qty])->save();
    }

    /**
     * Restock every item of the order that has a reserved quantity, once.
     *
     * @return int Number of items restocked.
     *
     * @throws LogicException If no transaction is active.
     */
    public function restoreForOrder(Order $order): int
    {
        $this->assertInTransaction();

        $restored = 0;

        foreach ($order->items()->where('stock_reserved_quantity', '>', 0)->get() as $item) {
            $product = $item->product_id !== null
                ? Product::lockForUpdate()->find($item->product_id)
                : null;

            // Re-read the item under the product lock so concurrent restores cannot double count.
            $reserved = (int) OrderItem::whereKey($item->getKey())->value('stock_reserved_quantity');

            if ($reserved > 0 && $product !== null && $product->stock_quantity !== null) {
                $product->increment('stock_quantity', $reserved);
                $restored++;
            }

            if ($reserved > 0) {
                OrderItem::whereKey($item->getKey())->update(['stock_reserved_quantity' => 0]);
            }
        }

        return $restored;
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('StockService must be called inside a database transaction.');
        }
    }
}
