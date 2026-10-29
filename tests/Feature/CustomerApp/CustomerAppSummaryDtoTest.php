<?php

namespace Tests\Feature\CustomerApp;

use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\Service;
use App\Support\Dto\CustomerAppSummaryDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Focused example unit tests for CustomerAppSummaryDto::fromService() (task
 * 4.6). These pin the exact customer-facing key set and the two billing
 * branches:
 *   - a free service (no Stripe link, no positive charge) -> billing "Free"
 *   - a Stripe-charged service -> the formatted charge string
 * Every case asserts the output has exactly the four allow-listed keys in
 * the DTO's declared order.
 *
 * **Validates: Requirements 5.1**
 */
class CustomerAppSummaryDtoTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_KEYS = ['name', 'status', 'billing', 'security_summary'];

    public function test_free_service_billing_and_exact_key_set(): void
    {
        // Req 5.1/1.5: no Stripe subscription and no positive monthly charge
        // means the billing line is "Free".
        $service = $this->makeService([
            'service_short' => 'Managed App',
            'status' => 'active',
            'service_monthly_charge' => 0,
            'stripe_subscription_id' => null,
        ]);
        $this->linkApp($service);

        $summary = CustomerAppSummaryDto::fromService($service->fresh());

        $this->assertSame(self::EXPECTED_KEYS, array_keys($summary));
        $this->assertSame('Managed App', $summary['name']);
        $this->assertSame('active', $summary['status']);
        $this->assertSame('Free', $summary['billing']);
        $this->assertSame('Security checks passing', $summary['security_summary']);
    }

    public function test_stripe_charged_service_billing_and_exact_key_set(): void
    {
        // Req 5.1: a service with a positive monthly charge and a recorded
        // payment frequency formats as "£49.99/Monthly".
        $service = $this->makeService([
            'service_short' => 'Premium App',
            'status' => 'active',
            'service_monthly_charge' => 49.99,
            'service_payment_frequency' => 'Monthly',
            'stripe_subscription_id' => 'sub_ABC123',
        ]);
        $this->linkApp($service);

        $summary = CustomerAppSummaryDto::fromService($service->fresh());

        $this->assertSame(self::EXPECTED_KEYS, array_keys($summary));
        $this->assertSame('Premium App', $summary['name']);
        $this->assertSame('active', $summary['status']);
        $this->assertSame('£49.99/Monthly', $summary['billing']);
    }

    public function test_charge_without_frequency_formats_amount_only(): void
    {
        // Req 5.1: a positive charge with no recorded frequency formats as the
        // bare currency amount, with no trailing "/frequency" suffix.
        $service = $this->makeService([
            'service_short' => 'One-off App',
            'status' => 'active',
            'service_monthly_charge' => 49.99,
            'service_payment_frequency' => null,
            'stripe_subscription_id' => null,
        ]);
        $this->linkApp($service);

        $summary = CustomerAppSummaryDto::fromService($service->fresh());

        $this->assertSame(self::EXPECTED_KEYS, array_keys($summary));
        $this->assertSame('£49.99', $summary['billing']);
    }

    private function makeService(array $attributes): Service
    {
        $customer = Customer::factory()->create();

        return Service::create(array_merge([
            'company_id' => $customer->company_id,
        ], $attributes));
    }

    private function linkApp(Service $service): CustomerApp
    {
        return CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://example.com/repo.git',
            'hosting_location' => 'cpanel-01',
            'codebase_info' => 'Laravel 13 / PHP 8.3',
        ]);
    }
}
