<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ServiceController as WebController;
use App\Http\Controllers\Api\Admin\ServiceController as ApiController;
use App\Http\Requests\ServiceRequest;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Api\Admin\ServiceController store/update call the controller methods directly
 * and must reach the same verdict as the web controller (shared ServiceRequest).
 *
 * Validates: Requirements 1.4, 1.5, 11.6
 */
class ApiServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Build a ServiceRequest and run validation; returns null or the failing field names. */
    private function makeRequest(array $data, ?Service $service = null, string $method = 'POST'): array
    {
        $request = ServiceRequest::create('/services', $method, $data);
        $request->setContainer(app())->setRedirector(app('redirect'));

        if ($service) {
            $route = new Route($method, '/services/{service}', []);
            $route->bind($request);
            $route->setParameter('service', $service);
            $request->setRouteResolver(fn () => $route);
        }

        try {
            $request->validateResolved();
        } catch (ValidationException $e) {
            return [$request, array_keys($e->errors())];
        }

        return [$request, []];
    }

    private function payload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $customer->company_id,
            'service_short' => 'Hosting',
            'service_type' => 'Web Hosting',
            'status' => 'Active',
            'domain_name' => 'example.com',
        ], $overrides);
    }

    public function test_store_rejects_invalid_type_and_status_like_web(): void
    {
        $customer = Customer::factory()->create();

        [, $failed] = $this->makeRequest($this->payload($customer, ['service_type' => 'Bogus', 'status' => 'Nope']));

        $this->assertEqualsCanonicalizing(['service_type', 'status'], $failed);
    }

    public function test_store_persists_canonical_domain_and_returns_201(): void
    {
        $customer = Customer::factory()->create();
        [$request, $failed] = $this->makeRequest($this->payload($customer, [
            'domain_name' => ' WWW.Example.COM. ',
            'provision_whm' => true,
        ]));
        $this->assertSame([], $failed);

        $response = app(ApiController::class)->store($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('example.com', Service::firstOrFail()->domain_name);
    }

    public function test_store_rejects_domain_variants_as_duplicates_like_web(): void
    {
        $customer = Customer::factory()->create();
        Service::factory()->create([
            'company_id' => $customer->company_id,
            'service_type' => 'Web Hosting',
            'domain_name' => 'example.com',
        ]);

        foreach (['EXAMPLE.com', 'www.example.com', 'Example.com.'] as $variant) {
            [, $failed] = $this->makeRequest($this->payload($customer, ['domain_name' => $variant]));
            $this->assertSame(['domain_name'], $failed);
        }
    }

    public function test_update_rejects_invalid_values_and_allows_own_domain(): void
    {
        $service = Service::factory()->create(['service_type' => 'Web Hosting', 'domain_name' => 'example.com']);

        [, $failed] = $this->makeRequest(['service_type' => 'Bogus', 'status' => 'Nope'], $service, 'PUT');
        $this->assertEqualsCanonicalizing(['service_type', 'status'], $failed);

        [$request, $failed] = $this->makeRequest(
            ['service_short' => 'Renamed', 'domain_name' => 'www.example.com'],
            $service,
            'PUT'
        );
        $this->assertSame([], $failed);

        $response = app(ApiController::class)->update($request, $service);

        $this->assertSame(200, $response->getStatusCode());
        $service->refresh();
        $this->assertSame('Renamed', $service->service_short);
        $this->assertSame('example.com', $service->domain_name);
    }

    public function test_api_and_web_controllers_share_the_request_type(): void
    {
        foreach ([ApiController::class, WebController::class] as $class) {
            foreach (['store', 'update'] as $method) {
                $param = (new \ReflectionMethod($class, $method))->getParameters()[0];
                $this->assertSame(ServiceRequest::class, $param->getType()->getName());
            }
        }
    }
}
