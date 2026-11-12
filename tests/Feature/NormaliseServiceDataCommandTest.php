<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ScheduledTaskLog;
use App\Models\Service;
use App\Models\ServiceDataCleanupChange;
use App\Models\User;
use App\Services\DataCleanup\ServiceDomainBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for the services:normalise-data command and its UI whitelist.
 *
 * _Requirements: 1.9, 1.10, 1.11, 2.3, 2.4_
 */
class NormaliseServiceDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedDirtyData(): void
    {
        Domain::factory()->create(['domain_name' => 'Example.com']);
        Service::factory()->create(['domain_name' => ' EXAMPLE.com ', 'cpanel_username' => ' UserA ', 'service_type' => 'hosting']);
        Service::factory()->create(['domain_name' => '', 'cpanel_username' => 'usera']);
        Service::factory()->create(['service_type' => 'Mystery Type']);
    }

    public function test_default_run_is_read_only_and_reports(): void
    {
        $this->seedDirtyData();
        $before = [DB::table('services')->get()->toArray(), DB::table('domains')->get()->toArray()];

        $this->artisan('services:normalise-data')
            ->expectsOutputToContain('Unmatched labels: 1')
            ->expectsOutputToContain('Empty-string conversions (to NULL): ')
            ->expectsOutputToContain('Duplicates after normalisation: 1')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertEquals($before, [DB::table('services')->get()->toArray(), DB::table('domains')->get()->toArray()]);
        $this->assertSame(0, ServiceDataCleanupChange::count());

        $log = ScheduledTaskLog::where('task_name', 'services:normalise-data')->firstOrFail();
        $this->assertSame('completed', $log->status);
        $this->assertSame('dry-run', $log->meta['mode']);
    }

    public function test_apply_aborts_without_writing_when_duplicates_would_result(): void
    {
        $this->seedDirtyData();
        $before = DB::table('services')->get()->toArray();

        $this->artisan('services:normalise-data', ['--apply' => true])->assertFailed();

        $this->assertEquals($before, DB::table('services')->get()->toArray());
        $this->assertSame(0, ServiceDataCleanupChange::count());
    }

    public function test_apply_matches_backfill_apply(): void
    {
        Domain::factory()->create(['domain_name' => 'Example.com']);
        Service::factory()->create(['domain_name' => ' EXAMPLE.com ', 'cpanel_username' => ' UserA ', 'service_type' => 'hosting']);
        Service::factory()->create(['domain_name' => '', 'cpanel_username' => 'userb']);
        Service::factory()->create(['service_type' => 'Mystery Type']);
        $backfill = new ServiceDomainBackfill;
        $expectedChanges = count($backfill->plan()->changes);

        $this->artisan('services:normalise-data', ['--apply' => true])->assertSuccessful();

        // One log row per planned change, plus one for the unmatched label finding.
        $this->assertSame($expectedChanges + 1, ServiceDataCleanupChange::where('migration', ServiceDomainBackfill::MIGRATION)->count());
        $this->assertSame([], $backfill->plan()->changes);
        $this->assertSame('apply', ScheduledTaskLog::where('task_name', 'services:normalise-data')->value('meta')['mode']);
    }

    public function test_apply_aborts_on_collision(): void
    {
        Service::factory()->create(['domain_name' => 'Dup.com']);
        Service::factory()->create(['domain_name' => 'dup.com ']);

        $this->artisan('services:normalise-data', ['--apply' => true])->assertFailed();

        $this->assertSame(0, ServiceDataCleanupChange::whereColumn('old_value', '!=', 'new_value')->count());
        $this->assertSame('failed', ScheduledTaskLog::where('task_name', 'services:normalise-data')->value('status'));
    }

    public function test_ui_whitelist_allows_dry_run_only(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->seedDirtyData();
        $before = DB::table('services')->get()->toArray();

        $this->actingAs($admin)
            ->post(route('admin.settings.tasks.run'), ['command' => 'services:normalise-data'])
            ->assertSessionHas('success');
        $this->assertEquals($before, DB::table('services')->get()->toArray());
        $this->assertSame('dry-run', ScheduledTaskLog::where('task_name', 'services:normalise-data')->value('meta')['mode']);

        $this->actingAs($admin)
            ->post(route('admin.settings.tasks.run'), ['command' => 'services:normalise-data --apply'])
            ->assertSessionHasErrors('command');
        $this->assertEquals($before, DB::table('services')->get()->toArray());
    }
}
