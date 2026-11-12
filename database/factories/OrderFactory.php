<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'company_id' => Customer::factory(),
            'payment_status' => 'pending',
            'fulfilment_status' => 'pending',
            'stripe_checkout_session_id' => null,
            'total_amount' => fake()->randomFloat(2, 5, 500),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => 'paid',
            'fulfilment_status' => 'awaiting_fulfilment',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['payment_status' => 'failed']);
    }

    // Note: 'expired' / 'cancelled' statuses are not offered as states because the
    // migration that widens the enums skips SQLite, so the test DB rejects them.
    // Tests that need them on MySQL can pass explicit attributes.

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn () => ['company_id' => $customer->company_id]);
    }
}
