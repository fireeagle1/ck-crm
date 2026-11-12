<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'company_id' => Customer::factory(),
            'domain_name' => 'domain'.fake()->unique()->numberBetween(1, 9999999).'.example.com',
            'cost' => fake()->randomFloat(2, 5, 30),
            'registrar' => null,
            'registration_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'auto_renew' => false,
        ];
    }

    /**
     * A domain created by the eNom sync. Sets the eNom sync columns (migration M7).
     */
    public function enomSourced(?string $enomId = null): static
    {
        return $this->state(function () use ($enomId) {
            $enomId ??= (string) fake()->unique()->numberBetween(100000, 99999999);

            $state = [
                'registrar' => 'eNom',
                'enom_response' => json_encode(['DomainNameID' => $enomId]),
            ];
            $state['enom_domain_name_id'] = $enomId;
            $state['status'] = 'active';
            $state['last_synced_at'] = now();

            return $state;
        });
    }

    /** A domain not assigned to any customer. */
    public function unassigned(): static
    {
        return $this->state(fn () => ['company_id' => null]);
    }

    /** An eNom domain no longer returned by eNom. */
    public function missingFromEnom(): static
    {
        return $this->enomSourced()->state(fn () => ['status' => 'missing_from_enom']);
    }
}
