<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerApp>
 */
class CustomerAppFactory extends Factory
{
    protected $model = CustomerApp::class;

    public function definition(): array
    {
        return [
            // Service is created inline so this factory does not depend on ServiceFactory.
            'service_id' => fn () => Service::create([
                'company_id' => Customer::factory()->create()->company_id,
                'service_short' => fake()->words(2, true),
                'status' => 'Active',
            ])->service_id,
            'display_name' => fake()->words(2, true),
            'install_url' => 'https://'.fake()->unique()->domainName(),
            'repo_url' => 'https://github.com/example/'.fake()->slug(2),
            'hosting_location' => 'cPanel',
            'codebase_info' => fake()->sentence(),
        ];
    }

    public function forService(Service $service): static
    {
        return $this->state(fn () => ['service_id' => $service->service_id]);
    }

    /**
     * Attach one finding per given status (defaults to every status).
     *
     * @param  list<string>|null  $statuses
     */
    public function withFindings(?array $statuses = null): static
    {
        $statuses ??= SecurityFinding::STATUSES;

        return $this->afterCreating(function (CustomerApp $app) use ($statuses) {
            foreach ($statuses as $status) {
                SecurityFinding::factory()->for($app, 'customerApp')->state(['status' => $status])->create();
            }
        });
    }
}
