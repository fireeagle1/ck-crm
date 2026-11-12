<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Domain;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceDataCleanupChange;
use App\Services\DataCleanup\BackfillPlan;
use App\Services\DataCleanup\ServiceDomainBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for ServiceDomainBackfill::plan().
 *
 * _Requirements: 1.9, 1.10, 1.11, 2.3, 11.9, 12.1_
 */
class ServiceDomainBackfillPlanTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): BackfillPlan
    {
        return (new ServiceDomainBackfill)->plan();
    }

    /** @return array<string,mixed>|null */
    private function change(BackfillPlan $plan, string $table, int|string $id, string $column): ?array
    {
        foreach ($plan->changes as $c) {
            if ($c['table'] === $table && (string) $c['row_id'] === (string) $id && $c['column'] === $column) {
                return $c;
            }
        }

        return null;
    }

    public function test_trims_and_converts_empty_strings_to_null(): void
    {
        $a = Service::factory()->create(['cpanel_username' => '', 'domain_name' => '   ']);
        $b = Service::factory()->create(['cpanel_username' => ' abc ', 'domain_name' => null]);

        $plan = $this->plan();

        $this->assertSame('empty_to_null', $this->change($plan, 'services', $a->service_id, 'cpanel_username')['action']);
        $this->assertNull($this->change($plan, 'services', $a->service_id, 'cpanel_username')['new']);
        $this->assertNull($this->change($plan, 'services', $a->service_id, 'domain_name')['new']);
        $c = $this->change($plan, 'services', $b->service_id, 'cpanel_username');
        $this->assertSame('abc', $c['new']);
        $this->assertSame('trim', $c['action']);
        $this->assertNull($this->change($plan, 'services', $b->service_id, 'domain_name'));
    }

    public function test_lowercases_cpanel_username(): void
    {
        $s = Service::factory()->create(['cpanel_username' => 'MixedUser']);

        $c = $this->change($this->plan(), 'services', $s->service_id, 'cpanel_username');

        $this->assertSame('mixeduser', $c['new']);
        $this->assertSame('lowercase', $c['action']);
    }

    public function test_maps_labels_and_reports_unmatched(): void
    {
        $hosting = Service::factory()->create(['service_type' => 'hosting', 'status' => 'pending']);
        $case = Service::factory()->create(['service_type' => 'TECHNICAL SUPPORT', 'status' => 'active']);
        $legacy = Service::factory()->create(['service_type' => 'Domain Registration', 'status' => 'payment_failed']);
        $ok = Service::factory()->create(['service_type' => 'Other', 'status' => 'Active']);

        $plan = $this->plan();

        $this->assertSame('Web Hosting', $this->change($plan, 'services', $hosting->service_id, 'service_type')['new']);
        $this->assertSame('Pending', $this->change($plan, 'services', $hosting->service_id, 'status')['new']);
        $this->assertSame('Technical Support', $this->change($plan, 'services', $case->service_id, 'service_type')['new']);
        $this->assertSame('Active', $this->change($plan, 'services', $case->service_id, 'status')['new']);
        $this->assertSame('Suspended', $this->change($plan, 'services', $legacy->service_id, 'status')['new']);
        $this->assertNull($this->change($plan, 'services', $legacy->service_id, 'service_type'));
        $this->assertNull($this->change($plan, 'services', $ok->service_id, 'service_type'));
        $this->assertNull($this->change($plan, 'services', $ok->service_id, 'status'));

        $this->assertCount(1, $plan->unmatchedLabels);
        $this->assertSame($legacy->service_id, $plan->unmatchedLabels[0]['row_id']);
        $this->assertSame('Domain Registration', $plan->unmatchedLabels[0]['value']);
    }

    public function test_canonicalises_domain_names_in_all_three_tables(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'WWW.Example.COM.']);
        $service = Service::factory()->create(['domain_name' => ' www.Shop.Example.com ']);
        $order = Order::factory()->create();
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'domain_name' => 'Item.Example.com']);

        $plan = $this->plan();

        $this->assertSame('example.com', $this->change($plan, 'domains', $domain->id, 'domain_name')['new']);
        $s = $this->change($plan, 'services', $service->service_id, 'domain_name');
        $this->assertSame('shop.example.com', $s['new']);
        $this->assertSame('canonicalise', $s['action']);
        $this->assertSame('item.example.com', $this->change($plan, 'order_items', $item->id, 'domain_name')['new']);
    }

    public function test_reports_invalid_domains_and_leaves_them_unchanged(): void
    {
        $service = Service::factory()->create(['domain_name' => 'not a domain']);

        $plan = $this->plan();

        $this->assertNull($this->change($plan, 'services', $service->service_id, 'domain_name'));
        $this->assertCount(1, $plan->invalidDomains);
        $this->assertSame('services', $plan->invalidDomains[0]['table']);
        $this->assertSame('not a domain', $plan->invalidDomains[0]['value']);
    }

    public function test_reports_canonicalisation_collisions_and_skips_those_rows(): void
    {
        $a = Domain::factory()->create(['domain_name' => 'dupe.com']);
        $b = Domain::factory()->create(['domain_name' => 'www.dupe.com']);

        $plan = $this->plan();

        $this->assertCount(1, $plan->collisions);
        $this->assertSame('domains', $plan->collisions[0]['table']);
        $this->assertSame('dupe.com', $plan->collisions[0]['canonical']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $plan->collisions[0]['row_ids']);
        $this->assertNull($this->change($plan, 'domains', $b->id, 'domain_name'));
        $this->assertTrue($plan->hasCollisions());
    }

    public function test_links_services_and_order_items_to_matching_domain(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'linked.com']);
        $service = Service::factory()->create(['domain_name' => 'WWW.Linked.com']);
        $none = Service::factory()->create(['domain_name' => 'nomatch.com']);
        $item = OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'domain_name' => 'linked.com']);

        $plan = $this->plan();

        $link = $this->change($plan, 'services', $service->service_id, 'domain_id');
        $this->assertSame((string) $domain->id, $link['new']);
        $this->assertSame('link_domain', $link['action']);
        $this->assertNull($this->change($plan, 'services', $none->service_id, 'domain_id'));
        $this->assertSame((string) $domain->id, $this->change($plan, 'order_items', $item->id, 'domain_id')['new']);
    }

    public function test_already_linked_service_needs_no_link_change(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'done.com']);
        $service = Service::factory()->create(['domain_name' => 'done.com']);
        DB::table('services')->where('service_id', $service->service_id)->update(['domain_id' => $domain->id]);

        $this->assertNull($this->change($this->plan(), 'services', $service->service_id, 'domain_id'));
    }

    public function test_sets_provisioning_succeeded_for_hosting_with_cpanel_username(): void
    {
        $hosting = Service::factory()->webHosting()->withCpanel('Acct1')->create();
        $legacyType = Service::factory()->create(['service_type' => 'hosting', 'cpanel_username' => 'acct2']);
        $noCpanel = Service::factory()->webHosting()->create();
        $support = Service::factory()->withCpanel('acct3')->create();
        $already = Service::factory()->webHosting()->withCpanel('acct4')->provisioning('failed')->create();

        $plan = $this->plan();

        $this->assertSame('succeeded', $this->change($plan, 'services', $hosting->service_id, 'provisioning_status')['new']);
        $this->assertSame('succeeded', $this->change($plan, 'services', $legacyType->service_id, 'provisioning_status')['new']);
        $this->assertNull($this->change($plan, 'services', $noCpanel->service_id, 'provisioning_status'));
        $this->assertNull($this->change($plan, 'services', $support->service_id, 'provisioning_status'));
        $this->assertNull($this->change($plan, 'services', $already->service_id, 'provisioning_status'));
    }

    public function test_counts_group_changes_by_action(): void
    {
        Service::factory()->create(['service_type' => 'hosting', 'cpanel_username' => 'UP']);
        Service::factory()->create(['service_type' => 'hosting', 'cpanel_username' => 'AB']);

        $counts = $this->plan()->counts();

        $this->assertSame(2, $counts['map_label']);
        $this->assertSame(2, $counts['lowercase']);
        $this->assertSame(2, $counts['provisioning_succeeded']);
    }

    public function test_plan_writes_nothing(): void
    {
        Domain::factory()->create(['domain_name' => 'WWW.Write.com']);
        Service::factory()->create(['service_type' => 'hosting', 'status' => 'pending', 'domain_name' => 'Write.com', 'cpanel_username' => ' X ']);
        OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'domain_name' => 'WRITE.com']);
        Customer::query()->count();

        $snapshot = fn () => [
            DB::table('services')->get()->toArray(),
            DB::table('domains')->get()->toArray(),
            DB::table('order_items')->get()->toArray(),
        ];
        $before = $snapshot();
        $changesBefore = ServiceDataCleanupChange::count();

        $plan = $this->plan();

        $this->assertFalse($plan->isEmpty());
        $this->assertEquals($before, $snapshot());
        $this->assertSame($changesBefore, ServiceDataCleanupChange::count());
    }

    public function test_plan_is_empty_for_clean_data(): void
    {
        Domain::factory()->create(['domain_name' => 'clean.com']);
        Service::factory()->webHosting()->create(['domain_name' => 'clean2.com']);

        $plan = $this->plan();

        $this->assertSame([], $plan->unmatchedLabels);
        $this->assertSame([], $plan->invalidDomains);
        $this->assertSame([], $plan->collisions);
        $this->assertSame([], $plan->changesFor('domains'));
    }
}
