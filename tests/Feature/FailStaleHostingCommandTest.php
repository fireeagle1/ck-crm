<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * _Requirements: 9.7_
 */
class FailStaleHostingCommandTest extends TestCase
{
    use RefreshDatabase;

    private function runningService(int $minutesAgo): Service
    {
        $service = Service::factory()->provisioning('running')->create();
        $service->forceFill(['provisioning_updated_at' => now()->subMinutes($minutesAgo)])->saveQuietly();

        return $service->fresh();
    }

    public function test_stale_running_services_are_failed_and_fresh_ones_untouched(): void
    {
        $stale = $this->runningService(16);
        $fresh = $this->runningService(5);
        $queued = Service::factory()->provisioning('queued')->create();
        $queued->forceFill(['provisioning_updated_at' => now()->subHour()])->saveQuietly();

        $this->artisan('hosting:fail-stale')->assertSuccessful();

        $stale->refresh();
        $this->assertSame('failed', $stale->provisioning_status);
        $this->assertStringContainsString('timed out', $stale->provisioning_error);

        $fresh->refresh();
        $this->assertSame('running', $fresh->provisioning_status);
        $this->assertNull($fresh->provisioning_error);

        $this->assertSame('queued', $queued->fresh()->provisioning_status);
    }

    public function test_schedule_entries_are_registered(): void
    {
        $events = collect(app(Schedule::class)->events());

        $stale = $events->first(fn ($e) => $e->description === 'hosting-fail-stale');
        $this->assertNotNull($stale);
        $this->assertSame('*/10 * * * *', $stale->expression);

        $queue = $events->first(fn ($e) => $e->description === 'queue-work');
        $this->assertNotNull($queue);
        $this->assertTrue($queue->withoutOverlapping);
        $this->assertSame(10, $queue->expiresAt);
    }

    public function test_queue_retry_after_reads_env_backed_config(): void
    {
        $this->assertIsInt(config('queue.connections.database.retry_after'));
    }
}
