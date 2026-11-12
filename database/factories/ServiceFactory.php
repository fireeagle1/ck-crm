<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'company_id' => Customer::factory(),
            'service_short' => fake()->words(2, true),
            'service_type' => 'Technical Support',
            'status' => 'Active',
            'start_date' => now()->toDateString(),
            'service_monthly_charge' => fake()->randomFloat(2, 5, 100),
            'service_payment_frequency' => 'monthly',
        ];
    }

    public function webHosting(): static
    {
        return $this->state(fn () => [
            'service_type' => 'Web Hosting',
            'domain_name' => 'host'.fake()->unique()->numberBetween(1, 999999).'.example.com',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'Pending']);
    }

    public function withCpanel(?string $username = null): static
    {
        return $this->state(fn () => [
            'cpanel_username' => $username ?? 'c'.fake()->unique()->numerify('########'),
        ]);
    }

    /**
     * Set the provisioning status. Ignored until the provisioning_status
     * column exists (migration M1), so earlier tasks can still use the factory.
     */
    public function provisioning(?string $status): static
    {
        return $this->state(function () use ($status) {
            if (! Schema::hasColumn('services', 'provisioning_status')) {
                return [];
            }

            $state = ['provisioning_status' => $status];

            if (Schema::hasColumn('services', 'provisioning_updated_at')) {
                $state['provisioning_updated_at'] = now();
            }
            if ($status === 'failed' && Schema::hasColumn('services', 'provisioning_error')) {
                $state['provisioning_error'] = 'Factory failure';
            }

            return $state;
        });
    }
}
