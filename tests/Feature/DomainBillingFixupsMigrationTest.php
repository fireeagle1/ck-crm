<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServiceDataCleanupChange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One-off production fix-ups: domain owns its subscription, Giant's Seat repair, Greenmount note.
 */
class DomainBillingFixupsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_30_100017_fold_domain_billing_fixups.php');
    }

    private function domain(int $id, string $name, int $companyId, ?string $sub = null, ?string $notes = null): void
    {
        DB::table('domains')->insert([
            'id' => $id, 'domain_name' => $name, 'company_id' => $companyId,
            'stripe_subscription_id' => $sub, 'domain_admin_notes' => $notes,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function service(int $id, string $short, int $companyId, ?string $domainName, ?int $domainId, ?string $sub, string $type = 'Other'): void
    {
        DB::table('services')->insert([
            'service_id' => $id, 'company_id' => $companyId, 'service_short' => $short,
            'service_type' => $type, 'status' => 'Active', 'domain_name' => $domainName,
            'domain_id' => $domainId, 'stripe_subscription_id' => $sub,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedProductionState(): void
    {
        foreach ([3, 23, 108] as $id) {
            Customer::factory()->create(['company_id' => $id]);
        }

        $this->domain(9, 'greenmount.org.uk', 3, null, 'Existing note');
        $this->domain(32, 'theproductcorner.co.uk', 108);
        $this->domain(34, 'pickleandpetal.co.uk', 108);
        $this->domain(35, 'giantsseat.org.uk', 23);
        $this->domain(36, 'giantseat.org.uk', 23, 'sub_1UZzJqKOtRMQ2ybupKDl6BAs');

        $this->service(139, 'Domain Name - Annual Registration Fee', 108, 'theproductcorner.co.uk', 32, 'sub_1UI6KQKOtRMQ2ybuDr9AM7Nq');
        $this->service(140, 'Domain Name - Annual Registration Fee - pickleandpetal.co.uk', 108, 'pickleandpetal.co.uk', 34, 'sub_1USH98KOtRMQ2ybuGpdH5kBi');
        $this->service(142, 'Charity Level Hosting', 23, 'giantseat.org.uk', 36, null, 'Web Hosting');
    }

    private function snapshot(): array
    {
        return [
            'domains' => DB::table('domains')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'services' => DB::table('services')->orderBy('service_id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    private function domainRow(int $id): object
    {
        return DB::table('domains')->where('id', $id)->first();
    }

    public function test_applies_all_fixups(): void
    {
        $this->seedProductionState();

        $this->migration()->up();

        $this->assertSame('sub_1UI6KQKOtRMQ2ybuDr9AM7Nq', $this->domainRow(32)->stripe_subscription_id);
        $this->assertSame('sub_1USH98KOtRMQ2ybuGpdH5kBi', $this->domainRow(34)->stripe_subscription_id);
        $this->assertFalse(DB::table('services')->whereIn('service_id', [139, 140])->exists());

        $hosting = DB::table('services')->where('service_id', 142)->first();
        $this->assertSame('sub_1UZzJqKOtRMQ2ybupKDl6BAs', $hosting->stripe_subscription_id);
        $this->assertSame(35, (int) $hosting->domain_id);
        $this->assertSame('giantsseat.org.uk', $hosting->domain_name);
        $this->assertSame('sub_1UZzIGKOtRMQ2ybuTljfxRoL', $this->domainRow(35)->stripe_subscription_id);
        $this->assertNull($this->domainRow(36)->stripe_subscription_id);
        $this->assertNotNull($this->domainRow(36)->deleted_at);

        $this->assertStringContainsString('Existing note', $this->domainRow(9)->domain_admin_notes);
        $this->assertStringContainsString('Not managed by CK Enterprises', $this->domainRow(9)->domain_admin_notes);
    }

    public function test_down_restores_original_state_and_up_is_repeatable(): void
    {
        $this->seedProductionState();
        $before = $this->snapshot();

        $this->migration()->up();
        $this->migration()->down();

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, ServiceDataCleanupChange::where('migration', 'domain-billing-fixups')->count());

        $this->migration()->up();
        $this->assertSame('sub_1UZzIGKOtRMQ2ybuTljfxRoL', $this->domainRow(35)->stripe_subscription_id);
    }

    public function test_second_run_changes_nothing(): void
    {
        $this->seedProductionState();
        $this->migration()->up();
        $after = $this->snapshot();
        $logs = ServiceDataCleanupChange::count();

        $this->migration()->up();

        $this->assertEquals($after, $this->snapshot());
        $this->assertSame($logs, ServiceDataCleanupChange::count());
    }

    public function test_unexpected_state_skips_only_that_fix(): void
    {
        $this->seedProductionState();
        DB::table('domains')->where('id', 32)->update(['stripe_subscription_id' => 'sub_other']);
        DB::table('services')->where('service_id', 142)->update(['stripe_subscription_id' => 'sub_already']);

        $this->migration()->up();

        // Product Corner fold skipped, service kept, domain untouched.
        $this->assertTrue(DB::table('services')->where('service_id', 139)->exists());
        $this->assertSame('sub_other', $this->domainRow(32)->stripe_subscription_id);
        // Giant's Seat skipped entirely.
        $this->assertNull($this->domainRow(36)->deleted_at);
        $this->assertNull($this->domainRow(35)->stripe_subscription_id);
        // Others still applied.
        $this->assertSame('sub_1USH98KOtRMQ2ybuGpdH5kBi', $this->domainRow(34)->stripe_subscription_id);
        $this->assertFalse(DB::table('services')->where('service_id', 140)->exists());
    }

    public function test_empty_database_is_untouched(): void
    {
        $before = $this->snapshot();

        $this->migration()->up();

        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(0, ServiceDataCleanupChange::count());
    }
}
