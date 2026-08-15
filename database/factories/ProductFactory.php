<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'product_type' => fake()->randomElement(['one_off', 'hosting', 'equipment_rental']),
            'price' => fake()->randomFloat(2, 5, 200),
            'billing_frequency' => null,
            'stock_quantity' => fake()->numberBetween(1, 100),
            'is_archived' => false,
        ];
    }

    public function oneOff(): static
    {
        return $this->state(fn () => [
            'product_type' => 'one_off',
            'billing_frequency' => null,
        ]);
    }

    public function hosting(): static
    {
        return $this->state(fn () => [
            'product_type' => 'hosting',
            'billing_frequency' => 'monthly',
            'stock_quantity' => null,
        ]);
    }

    public function equipmentRental(): static
    {
        return $this->state(fn () => [
            'product_type' => 'equipment_rental',
            'billing_frequency' => 'monthly',
        ]);
    }
}
