<?php

namespace Database\Factories;

use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityFinding>
 */
class SecurityFindingFactory extends Factory
{
    protected $model = SecurityFinding::class;

    public function definition(): array
    {
        return [
            'customer_app_id' => CustomerApp::factory(),
            'severity' => fake()->randomElement(['critical', 'high', 'medium', 'low', 'info']),
            'description' => fake()->sentence(),
            'status' => SecurityFinding::STATUS_OPEN,
            'synced_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => SecurityFinding::STATUS_OPEN]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => SecurityFinding::STATUS_RESOLVED]);
    }

    public function dismissed(): static
    {
        return $this->state(fn () => ['status' => SecurityFinding::STATUS_DISMISSED]);
    }
}
