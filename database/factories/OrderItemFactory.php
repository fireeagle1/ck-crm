<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'service_id' => null,
            'product_name' => fake()->words(3, true),
            'product_type' => 'one_off',
            'price' => fake()->randomFloat(2, 5, 200),
            'billing_frequency' => null,
            'stripe_subscription_id' => null,
            'domain_name' => null,
            'quantity' => 1,
        ];
    }

    public function oneOff(int $quantity = 1): static
    {
        return $this->state(fn () => [
            'product_type' => 'one_off',
            'billing_frequency' => null,
            'domain_name' => null,
            'quantity' => $quantity,
        ]);
    }

    public function hosting(?string $domain = null): static
    {
        return $this->state(fn () => [
            'product_type' => 'hosting',
            'billing_frequency' => 'monthly',
            'domain_name' => $domain ?? fake()->unique()->domainName(),
            'quantity' => 1,
        ]);
    }
}
