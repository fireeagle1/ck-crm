<?php

namespace Tests\Feature;

use App\Exceptions\DomainAlreadyInUseException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\HostingServiceCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostingServiceCreatorTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    private function item(string $domain): OrderItem
    {
        $order = Order::create([
            'company_id' => $this->customer->company_id,
            'payment_status' => 'paid',
            'fulfilment_status' => 'pending',
            'total_amount' => 20.00,
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hosting Plan',
            'product_type' => 'hosting',
            'price' => 20.00,
            'billing_frequency' => 'monthly',
            'stripe_subscription_id' => 'sub_123',
            'domain_name' => $domain,
        ]);
    }

    public function test_repeated_calls_return_one_service(): void
    {
        $creator = app(HostingServiceCreator::class);
        $item = $this->item('example.com');

        $first = $creator->forOrderItem($item, $this->customer);
        $second = $creator->forOrderItem($item->fresh(), $this->customer);

        $this->assertSame($first->service_id, $second->service_id);
        $this->assertSame(1, Service::count());
        $this->assertSame('Web Hosting', $first->service_type);
        $this->assertSame('Pending', $first->status);
        $this->assertSame($first->service_id, $item->fresh()->service_id);
        $this->assertSame($first->domain_id, $item->fresh()->domain_id);
        $this->assertNotNull($first->domain_id);
        $this->assertSame('sub_123', $first->stripe_subscription_id);
        $this->assertSame('monthly', $first->service_payment_frequency);
    }

    public function test_canonical_domain_is_stored(): void
    {
        $service = app(HostingServiceCreator::class)
            ->forOrderItem($this->item('  WWW.Example.COM. '), $this->customer);

        $this->assertSame('example.com', $service->domain_name);
        $this->assertSame('example.com', $service->fresh()->domain_name);
    }

    public function test_domain_held_by_another_service_throws_naming_it(): void
    {
        $creator = app(HostingServiceCreator::class);
        $held = $creator->forOrderItem($this->item('example.com'), $this->customer);
        $held->update(['status' => 'Cancelled']);

        try {
            $creator->forOrderItem($this->item('WWW.example.com'), $this->customer);
            $this->fail('Expected DomainAlreadyInUseException');
        } catch (DomainAlreadyInUseException $e) {
            $this->assertSame($held->service_id, $e->existingServiceId);
            $this->assertStringContainsString((string) $held->service_id, $e->getMessage());
        }

        $this->assertSame(1, Service::count());
    }
}
