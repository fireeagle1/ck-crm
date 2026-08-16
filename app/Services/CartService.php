<?php

namespace App\Services;

use App\Models\Product;
use Carbon\Carbon;
use InvalidArgumentException;

class CartService
{
    private const SESSION_KEY = 'shop_cart';

    /**
     * Get all items currently in the cart.
     *
     * @return array<int, array>
     */
    public function getItems(): array
    {
        return session()->get(self::SESSION_KEY, []);
    }

    /**
     * Add a product to the cart with optional rental/hosting data.
     *
     * For one-off products, if the same product already exists in the cart,
     * the quantity is incremented rather than adding a new line.
     *
     * @param Product $product The product to add.
     * @param array $options Optional data: rental_start_date, rental_end_date, quantity, domain_name.
     *
     * @throws InvalidArgumentException if the product is not available or validation fails.
     */
    public function addItem(Product $product, array $options = []): void
    {
        if (!$product->isAvailable()) {
            throw new InvalidArgumentException(
                "Product \"{$product->name}\" is not available for purchase."
            );
        }

        // Validate options based on product type
        $this->validateOptions($product, $options);

        $quantity = max(1, (int) ($options['quantity'] ?? 1));

        // Validate quantity against stock for one-off products
        if ($product->isOneOff()) {
            $this->validateOneOffQuantity($product, $quantity);
        }

        $items = $this->getItems();

        // For one-off products, check for duplicate and increment quantity instead of adding a new line
        if ($product->isOneOff()) {
            $existingIndex = $this->findDuplicateOneOffIndex($items, $product);
            if ($existingIndex !== null) {
                $newQuantity = $items[$existingIndex]['quantity'] + $quantity;
                $this->validateOneOffQuantity($product, $newQuantity);
                $items[$existingIndex]['quantity'] = $newQuantity;
                $items[$existingIndex]['total_price'] = (float) $product->price * $newQuantity;
                session()->put(self::SESSION_KEY, $items);
                return;
            }
        }

        $item = [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'product_type' => $product->product_type,
            'billing_frequency' => $product->billing_frequency,
            'quantity' => $quantity,
            'rental_start_date' => $options['rental_start_date'] ?? null,
            'rental_end_date' => $options['rental_end_date'] ?? null,
            'domain_name' => $options['domain_name'] ?? null,
            'delivery_charge' => $product->hasDeliveryCharge() ? (float) $product->delivery_charge : 0,
        ];

        // Calculate total_price for rental items
        if ($product->isEquipmentRental() && $item['rental_start_date'] && $item['rental_end_date']) {
            $item['total_price'] = $this->calculateRentalTotal(
                (float) $product->price,
                $item['rental_start_date'],
                $item['rental_end_date'],
                $item['quantity']
            );
        } else {
            $item['total_price'] = (float) $product->price * $item['quantity'];
        }

        $items[] = $item;

        session()->put(self::SESSION_KEY, $items);
    }

    /**
     * Validate options based on product type.
     *
     * @throws InvalidArgumentException if validation fails.
     */
    private function validateOptions(Product $product, array $options): void
    {
        if ($product->isEquipmentRental()) {
            $this->validateRentalOptions($product, $options);
        }

        if ($product->isHosting()) {
            $this->validateHostingOptions($options);
        }
    }

    /**
     * Validate rental-specific options.
     *
     * @throws InvalidArgumentException if rental dates are missing or invalid.
     */
    private function validateRentalOptions(Product $product, array $options): void
    {
        if (empty($options['rental_start_date']) || empty($options['rental_end_date'])) {
            throw new InvalidArgumentException(
                'Rental start date and end date are required for equipment rental products.'
            );
        }

        $startDate = Carbon::parse($options['rental_start_date']);
        $endDate = Carbon::parse($options['rental_end_date']);

        if ($endDate->lte($startDate)) {
            throw new InvalidArgumentException(
                'Rental end date must be after the start date.'
            );
        }

        // Check minimum rental period
        $days = $startDate->diffInDays($endDate);
        $minDays = $product->min_rental_days;

        if ($minDays && $days < $minDays) {
            throw new InvalidArgumentException(
                "Minimum rental period is {$minDays} days. Selected period is {$days} days."
            );
        }

        // Validate quantity against available stock
        $quantity = (int) ($options['quantity'] ?? 1);
        if ($quantity < 1) {
            throw new InvalidArgumentException(
                'Quantity must be at least 1.'
            );
        }

        $maxAvailable = $product->track_individual_assets
            ? $product->assets()->whereIn('asset_status', ['Available', 'Reserved', 'Rented Out'])->count()
            : $product->stock_quantity;

        if ($maxAvailable !== null && $quantity > $maxAvailable) {
            throw new InvalidArgumentException(
                "Only {$maxAvailable} units available for \"{$product->name}\". You requested {$quantity}."
            );
        }
    }

    /**
     * Validate hosting-specific options.
     *
     * @throws InvalidArgumentException if domain name is missing or invalid.
     */
    private function validateHostingOptions(array $options): void
    {
        if (empty($options['domain_name'])) {
            throw new InvalidArgumentException(
                'A domain name is required for hosting products.'
            );
        }

        if (!$this->isValidDomain($options['domain_name'])) {
            throw new InvalidArgumentException(
                'Please provide a valid domain name (e.g. example.com).'
            );
        }
    }

    /**
     * Validate quantity for one-off products against stock.
     *
     * @throws InvalidArgumentException if quantity < 1 or exceeds stock.
     */
    private function validateOneOffQuantity(Product $product, int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($product->stock_quantity !== null && $quantity > $product->stock_quantity) {
            throw new InvalidArgumentException(
                "Only {$product->stock_quantity} units available for \"{$product->name}\"."
            );
        }
    }

    /**
     * Find the index of an existing one-off item in the cart for the same product.
     *
     * Returns the index if a duplicate one-off item is found, or null if not.
     */
    private function findDuplicateOneOffIndex(array $items, Product $product): ?int
    {
        foreach ($items as $index => $item) {
            if ($item['product_id'] === $product->id && $item['product_type'] === 'one_off') {
                return $index;
            }
        }

        return null;
    }

    /**
     * Validate a domain name format.
     */
    public function isValidDomain(string $domain): bool
    {
        // Must contain at least one dot, no spaces, only valid domain characters
        $pattern = '/^(?!-)[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/';

        return (bool) preg_match($pattern, $domain);
    }

    /**
     * Calculate rental total: price × days × quantity.
     */
    public function calculateRentalTotal(float $price, string $startDate, string $endDate, int $quantity): float
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = $start->diffInDays($end);

        return round($price * $days * $quantity, 2);
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
     * Update the quantity of a cart item (for one-off products).
     *
     * Validates the new quantity against the product's stock_quantity and
     * recalculates the line total.
     *
     * @param int $index The cart item index.
     * @param int $quantity The new quantity (must be >= 1).
     *
     * @throws InvalidArgumentException if the index is invalid, item is not one-off, or quantity exceeds stock.
     */
    public function updateItemQuantity(int $index, int $quantity): void
    {
        $items = $this->getItems();

        if (!array_key_exists($index, $items)) {
            throw new InvalidArgumentException('Cart item not found.');
        }

        $item = $items[$index];

        if ($item['product_type'] !== 'one_off') {
            throw new InvalidArgumentException('Quantity can only be adjusted for one-off products.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        // Validate against stock
        $product = Product::find($item['product_id']);
        if ($product && $product->stock_quantity !== null && $quantity > $product->stock_quantity) {
            throw new InvalidArgumentException(
                "Only {$product->stock_quantity} units available for \"{$item['name']}\"."
            );
        }

        // Update quantity and recalculate line total
        $items[$index]['quantity'] = $quantity;
        $items[$index]['total_price'] = (float) $items[$index]['price'] * $quantity;

        session()->put(self::SESSION_KEY, $items);
    }

    /**
     * Get the total price of all items in the cart.
     */
    public function getTotal(): float
    {
        $items = $this->getItems();
        $total = 0.0;

        foreach ($items as $item) {
            $total += $item['total_price'] ?? $item['price'];
        }

        return $total;
    }

    /**
     * Get the total delivery charge for all physical items in the cart.
     * Returns 0 if delivery method is 'collection'.
     */
    public function getDeliveryTotal(string $deliveryMethod = 'delivery'): float
    {
        if ($deliveryMethod === 'collection') {
            return 0.0;
        }

        $items = $this->getItems();
        $total = 0.0;

        foreach ($items as $item) {
            if (in_array($item['product_type'], ['one_off', 'equipment_rental'], true)) {
                $total += (float) ($item['delivery_charge'] ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * Get only one-off purchase items from the cart.
     *
     * @return array<int, array>
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
     * @return array<int, array>
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

    /**
     * Check if the cart contains only hosting products.
     */
    public function hasOnlyHostingItems(): bool
    {
        $items = $this->getItems();

        if (empty($items)) {
            return false;
        }

        return collect($items)->every(fn (array $item) => $item['product_type'] === 'hosting');
    }

    /**
     * Check if the cart contains any physical items (one_off or equipment_rental).
     */
    public function hasPhysicalItems(): bool
    {
        return collect($this->getItems())->contains(
            fn (array $item) => in_array($item['product_type'], ['one_off', 'equipment_rental'], true)
        );
    }
}
