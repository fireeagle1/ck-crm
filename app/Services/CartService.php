<?php

namespace App\Services;

use App\Models\Product;
use InvalidArgumentException;

class CartService
{
    private const SESSION_KEY = 'shop_cart';

    /**
     * Get all items currently in the cart.
     *
     * @return array<int, array{product_id: int, name: string, price: float, product_type: string, billing_frequency: ?string}>
     */
    public function getItems(): array
    {
        return session()->get(self::SESSION_KEY, []);
    }

    /**
     * Add a product to the cart.
     *
     * @throws InvalidArgumentException if the product is not available for purchase.
     */
    public function addItem(Product $product): void
    {
        if (!$product->isAvailable()) {
            throw new InvalidArgumentException(
                "Product \"{$product->name}\" is not available for purchase."
            );
        }

        $items = $this->getItems();

        $items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'product_type' => $product->product_type,
            'billing_frequency' => $product->billing_frequency,
        ];

        session()->put(self::SESSION_KEY, $items);
    }

    /**
     * Remove an item from the cart by its index.
     */
    public function removeItem(int $index): void
    {
        $items = $this->getItems();

        if (!array_key_exists($index, $items)) {
            return;
        }

        unset($items[$index]);

        // Re-index the array to maintain sequential keys
        session()->put(self::SESSION_KEY, array_values($items));
    }

    /**
     * Get the total price of all items in the cart.
     */
    public function getTotal(): float
    {
        return array_sum(array_column($this->getItems(), 'price'));
    }

    /**
     * Get only one-off purchase items from the cart.
     *
     * @return array<int, array{product_id: int, name: string, price: float, product_type: string, billing_frequency: ?string}>
     */
    public function getOneOffItems(): array
    {
        return array_values(
            array_filter($this->getItems(), fn (array $item) => $item['product_type'] === 'one_off')
        );
    }

    /**
     * Get only recurring items (equipment_rental or hosting) from the cart.
     *
     * @return array<int, array{product_id: int, name: string, price: float, product_type: string, billing_frequency: ?string}>
     */
    public function getRecurringItems(): array
    {
        return array_values(
            array_filter(
                $this->getItems(),
                fn (array $item) => in_array($item['product_type'], ['equipment_rental', 'hosting'], true)
            )
        );
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Check if the cart is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->getItems());
    }
}
