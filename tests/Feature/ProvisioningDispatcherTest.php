<?php

namespace Tests\Feature;

use App\Jobs\ProvisionHostingJob;
use App\Models\Service;
use App\Services\ProvisioningDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * _Requirements: 9.2, 9.11, 9.12_
 */
class ProvisioningDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private const STATUSES = [null, 'queued', 'running', 'succeeded', 'failed'];

    private function makeService(?string $status): Service
    {
        $service = Service::factory()->create();
        DB::table('services')->where('service_id', $service->service_id)->update([
            'provisioning_status' => $status,
            'provisioning_error' => $status === 'failed' ? 'boom' : null,
            'provisioning_updated_at' => $status === null ? null : now()->subHour(),
        ]);

        return $service->fresh();
    }

    private function row(Service $service): object
    {
        return DB::table('services')->where('service_id', $service->service_id)->first();
    }

    public function test_dispatch_guard_for_every_status(): void
    {
        config(['queue.default' => 'database']);

        foreach (self::STATUSES as $status) {
            Queue::fake();
            $service = $this->makeService($status);
            $before = $this->row($service);

            $result = app(ProvisioningDispatcher::class)->dispatch($service, 'abc');

            if ($status === null || $status === 'failed') {
                $this->assertTrue($result, "status ".var_export($status, true));
                $after = $this->row($service);
                $this->assertSame('queued', $after->provisioning_status);
                $this->assertNull($after->provisioning_error);
                $this->assertNotNull($after->provisioning_updated_at);
                Queue::assertPushed(ProvisionHostingJob::class, fn ($j) => $j->serviceId === $service->service_id && $j->requestedUsername === 'abc');
            } else {
                $this->assertFalse($result, "status $status");
                $this->assertEquals($before, $this->row($service));
                Queue::assertNothingPushed();
            }
        }
    }

    public function test_retry_accepts_only_failed(): void
    {
        config(['queue.default' => 'database']);

        foreach (self::STATUSES as $status) {
            Queue::fake();
            $service = $this->makeService($status);
            $before = $this->row($service);

            $result = app(ProvisioningDispatcher::class)->retry($service);

            if ($status === 'failed') {
                $this->assertTrue($result);
                $this->assertSame('queued', $this->row($service)->provisioning_status);
                Queue::assertPushed(ProvisionHostingJob::class, 1);
            } else {
                $this->assertFalse($result, "status ".var_export($status, true));
                $this->assertEquals($before, $this->row($service));
                Queue::assertNothingPushed();
            }
        }
    }

    public function test_second_dispatch_is_rejected_and_pushes_one_job(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $service = $this->makeService(null);

        $this->assertTrue(app(ProvisioningDispatcher::class)->dispatch($service));
        $this->assertFalse(app(ProvisioningDispatcher::class)->dispatch($service));

        Queue::assertPushed(ProvisionHostingJob::class, 1);
    }

    public function test_database_queue_writes_to_jobs_table(): void
    {
        config(['queue.default' => 'database']);
        $service = $this->makeService(null);

        app(ProvisioningDispatcher::class)->dispatch($service);

        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_sync_admin_context_runs_inline(): void
    {
        config(['queue.default' => 'sync']);
        Bus::fake();
        $service = $this->makeService(null);

        $this->assertTrue(app(ProvisioningDispatcher::class)->dispatch($service, null, ProvisioningDispatcher::CONTEXT_ADMIN));

        Bus::assertDispatchedSync(ProvisionHostingJob::class);
    }

    public function test_sync_webhook_context_defers_until_after_response(): void
    {
        config(['queue.default' => 'sync']);
        Bus::fake();
        $service = $this->makeService(null);

        $this->assertTrue(app(ProvisioningDispatcher::class)->dispatch($service, null, ProvisioningDispatcher::CONTEXT_WEBHOOK));

        Bus::assertDispatchedAfterResponse(ProvisionHostingJob::class);
        Bus::assertNotDispatchedSync(ProvisionHostingJob::class);
    }
}
