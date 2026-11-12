<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceDataCleanupChange;
use App\Services\DataCleanup\BackfillCollisionException;
use App\Services\DataCleanup\ServiceDomainBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for ServiceDomainBackfill::apply().
 *
 * _Requirements: 1.9, 1.10, 1.11, 2.3, 11.9, 12.1_
 */
class ServiceDomainBackfillApplyTest extends TestCase
{
    use RefreshDatabase;

    private function run_backfill(): void
    {
        $b = new ServiceDomainBackfill;
        $b->apply($b->plan());
    }

    public function test_apply_matches_plan_and_links_domains(): void
    {
        $domain = Domain::factory()->create(['domain_name' => 'WWW.Example.com.']);
        $service = Service::factory()->create([
            'domain_name' => ' Example.COM ',
            'cpanel_username' => ' UserA ',
            'service_type' => 'hosting',
            'status' => 'pending',
        ]);
        $item = OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'domain_name' => 'EXAMPLE.com']);

        $backfill = new ServiceDomainBackfill;
        $plan = $backfill->plan();
        $backfill->apply($plan);

        $this->assertSame('example.com', DB::table('domains')->where('id', $domain->id)->value('domain_name'));
        $row = DB::table('services')->where('service_id', $service->service_id)->first();
        $this->assertSame('example.com', $row->domain_name);
        $this->assertSame('usera', $row->cpanel_username);
        $this->assertSame('Web Hosting', $row->service_type);
        $this->assertSame('Pending', $row->status);
        $this->assertSame($domain->id, (int) $row->domain_id);
        $this->assertSame($domain->id, (int) DB::table('order_items')->where('id', $item->id)->value('domain_id'));
        $this->assertSame(count($plan->changes), ServiceDataCleanupChange::count());
    }

    public function test_second_run_changes_nothing(): void
    {
        Domain::factory()->create(['domain_name' => 'Again.com']);
        Service::factory()->create(['domain_name' => 'again.com', 'cpanel_username' => ' X ']);
        Service::factory()->create(['service_type' => 'Domain Registration']);

        $this->run_backfill();
        $logCount = ServiceDataCleanupChange::count();
        $snapshot = DB::table('services')->get()->toArray();

        $second = (new ServiceDomainBackfill)->plan();
        $this->assertSame([], $second->changes);
        $this->run_backfill();

        $this->assertSame($logCount, ServiceDataCleanupChange::count());
        $this->assertEquals($snapshot, DB::table('services')->get()->toArray());
    }

    public function test_unmatched_values_stay_unchanged_and_are_logged(): void
    {
        $legacy = Service::factory()->create(['service_type' => 'Domain Registration']);
        $invalid = Service::factory()->create(['domain_name' => 'not a domain']);
        $nomatch = Service::factory()->create(['domain_name' => 'Nomatch.com']);

        $this->run_backfill();

        $this->assertSame('Domain Registration', DB::table('services')->where('service_id', $legacy->service_id)->value('service_type'));
        $this->assertSame('not a domain', DB::table('services')->where('service_id', $invalid->service_id)->value('domain_name'));
        $this->assertNull(DB::table('services')->where('service_id', $nomatch->service_id)->value('domain_id'));

        $this->assertDatabaseHas('service_data_cleanup_changes', [
            'table_name' => 'services', 'row_id' => (string) $legacy->service_id,
            'column_name' => 'service_type', 'old_value' => 'Domain Registration', 'new_value' => 'Domain Registration',
        ]);
        $this->assertDatabaseHas('service_data_cleanup_changes', [
            'table_name' => 'services', 'row_id' => (string) $invalid->service_id,
            'column_name' => 'domain_name', 'old_value' => 'not a domain',
        ]);
    }

    public function test_collisions_abort_without_writing_and_are_logged(): void
    {
        $a = Domain::factory()->create(['domain_name' => 'dupe.com']);
        $b = Domain::factory()->create(['domain_name' => 'www.dupe.com']);
        $service = Service::factory()->create(['cpanel_username' => ' Z ']);

        try {
            $this->run_backfill();
            $this->fail('Expected collision exception');
        } catch (BackfillCollisionException $e) {
            $this->assertStringContainsString('dupe.com', $e->getMessage());
        }

        $this->assertSame(' Z ', DB::table('services')->where('service_id', $service->service_id)->value('cpanel_username'));
        $this->assertSame('www.dupe.com', DB::table('domains')->where('id', $b->id)->value('domain_name'));
        $this->assertDatabaseHas('service_data_cleanup_changes', [
            'table_name' => 'domains', 'row_id' => (string) $a->id, 'column_name' => 'domain_name',
        ]);
        $this->assertDatabaseHas('service_data_cleanup_changes', [
            'table_name' => 'domains', 'row_id' => (string) $b->id, 'old_value' => 'www.dupe.com',
        ]);
    }
}
