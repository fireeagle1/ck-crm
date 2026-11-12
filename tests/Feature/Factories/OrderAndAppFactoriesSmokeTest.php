<?php

namespace Tests\Feature\Factories;

use App\Models\CustomerApp;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SecurityFinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test for OrderFactory, OrderItemFactory, CustomerAppFactory and SecurityFindingFactory.
 */
class OrderAndAppFactoriesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_states_create(): void
    {
        $this->assertSame('pending', Order::factory()->create()->payment_status);
        $this->assertSame('paid', Order::factory()->paid()->create()->payment_status);
        $this->assertSame('failed', Order::factory()->failed()->create()->payment_status);
    }

    public function test_hosting_and_one_off_items_create(): void
    {
        $order = Order::factory()->create();

        $hosting = OrderItem::factory()->for($order)->hosting('example.com')->create();
        $oneOff = OrderItem::factory()->for($order)->oneOff(3)->create();

        $this->assertSame('hosting', $hosting->product_type);
        $this->assertSame('example.com', $hosting->domain_name);
        $this->assertSame('one_off', $oneOff->product_type);
        $this->assertSame(3, $oneOff->quantity);
        $this->assertCount(2, $order->items);
    }

    public function test_app_with_findings_in_each_status(): void
    {
        $app = CustomerApp::factory()->withFindings()->create();

        $this->assertNotNull($app->service);
        $this->assertEqualsCanonicalizing(
            SecurityFinding::STATUSES,
            $app->findings()->pluck('status')->all()
        );
        $this->assertSame(1, $app->openFindings()->count());
    }

    public function test_finding_states(): void
    {
        $this->assertSame('resolved', SecurityFinding::factory()->resolved()->create()->status);
        $this->assertSame('dismissed', SecurityFinding::factory()->dismissed()->create()->status);
        $this->assertSame('open', SecurityFinding::factory()->open()->create()->status);
    }
}
