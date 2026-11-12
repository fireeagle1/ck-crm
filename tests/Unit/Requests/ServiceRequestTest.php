<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ServiceRequest;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Validates: Requirements 1.3, 1.4, 1.5, 18.3
 */
class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data, ?Service $service = null): \Illuminate\Validation\Validator
    {
        $request = ServiceRequest::create('/services', 'POST', $data);
        $request->setRouteResolver(function () use ($request, $service) {
            $route = new Route('POST', '/services/{service}', []);
            $route->bind($request);
            if ($service) {
                $route->setParameter('service', $service);
            }

            return $route;
        });

        $m = new \ReflectionMethod($request, 'prepareForValidation');
        $m->setAccessible(true);
        $m->invoke($request);

        return Validator::make($request->all(), $request->rules());
    }

    private function base(array $over = []): array
    {
        return array_merge([
            'company_id' => Customer::factory()->create()->company_id,
            'service_short' => 'Test',
            'service_type' => 'Technical Support',
        ], $over);
    }

    public function test_valid_create_passes(): void
    {
        $this->assertTrue($this->validate($this->base())->passes());
    }

    public function test_service_type_enum(): void
    {
        $this->assertTrue($this->validate($this->base(['service_type' => 'Bogus']))->errors()->has('service_type'));
    }

    public function test_status_enum(): void
    {
        $this->assertTrue($this->validate($this->base(['status' => 'Nope']))->errors()->has('status'));
        $this->assertTrue($this->validate($this->base(['status' => 'Pending']))->passes());
    }

    public function test_domain_required_for_web_hosting(): void
    {
        $v = $this->validate($this->base(['service_type' => 'Web Hosting']));
        $this->assertTrue($v->errors()->has('domain_name'));
    }

    public function test_domain_not_required_for_other_types(): void
    {
        $this->assertFalse($this->validate($this->base())->errors()->has('domain_name'));
    }

    public function test_invalid_domain_rejected(): void
    {
        $this->assertTrue($this->validate($this->base(['domain_name' => 'not a domain']))->errors()->has('domain_name'));
    }

    public function test_domain_variant_is_normalised_then_unique_checked(): void
    {
        Service::factory()->create(['domain_name' => 'example.com']);
        $v = $this->validate($this->base(['domain_name' => 'WWW.Example.com.']));
        $this->assertTrue($v->errors()->has('domain_name'));
    }

    public function test_domain_unique_ignores_current_service_on_update(): void
    {
        $service = Service::factory()->create(['domain_name' => 'example.com']);
        $v = $this->validate(['domain_name' => 'www.example.com'], $service);
        $this->assertFalse($v->errors()->has('domain_name'));

        Service::factory()->create(['domain_name' => 'other.com']);
        $this->assertTrue($this->validate(['domain_name' => 'other.com'], $service)->errors()->has('domain_name'));
    }

    public function test_cpanel_username_format(): void
    {
        foreach (['Upper', '1abc', 'ab-c', 'ab_c', str_repeat('a', 33)] as $bad) {
            $this->assertTrue(
                $this->validate($this->base(['cpanel_username' => $bad]))->errors()->has('cpanel_username'),
                "Expected rejection of {$bad}"
            );
        }
        foreach (['abc', 'a1b2', str_repeat('a', 32)] as $good) {
            $this->assertFalse($this->validate($this->base(['cpanel_username' => $good]))->errors()->has('cpanel_username'));
        }
    }

    public function test_cpanel_username_unique_and_ignores_current(): void
    {
        $service = Service::factory()->create(['cpanel_username' => 'taken']);
        $this->assertTrue($this->validate($this->base(['cpanel_username' => 'taken']))->errors()->has('cpanel_username'));
        $this->assertFalse($this->validate(['cpanel_username' => 'taken'], $service)->errors()->has('cpanel_username'));
    }

    public function test_create_requires_core_fields_but_update_does_not(): void
    {
        $create = $this->validate([]);
        foreach (['company_id', 'service_short', 'service_type'] as $f) {
            $this->assertTrue($create->errors()->has($f), "create should require {$f}");
        }

        $service = Service::factory()->create();
        $this->assertTrue($this->validate(['status' => 'Suspended'], $service)->passes());
    }

    public function test_optional_web_and_api_fields(): void
    {
        $v = $this->validate($this->base([
            'provision_whm' => true,
            'whm_package' => 'pkg',
            'stripe_price_id' => 'price_1',
            'contact_email' => 'a@example.com',
            'stripe_subscription_id' => 'sub_1',
            'next_payment_date' => '2026-01-01',
        ]));
        $this->assertTrue($v->passes());

        $this->assertTrue($this->validate($this->base(['stripe_price_id' => 'price_1']))->errors()->has('contact_email'));
        $this->assertTrue($this->validate($this->base(['next_payment_date' => 'x']))->errors()->has('next_payment_date'));
    }
}
