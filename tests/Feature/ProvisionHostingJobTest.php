<?php

namespace Tests\Feature;

use App\Jobs\ProvisionHostingJob;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\HostingProvisioner;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeWhmService;
use Tests\TestCase;

/**
 * _Requirements: 9.3, 9.4, 9.10_
 */
class ProvisionHostingJobTest extends TestCase
{
    use RefreshDatabase;

    private FakeWhmService $whm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->whm = new FakeWhmService();
        $notifier = new class extends NotificationService
        {
            public function notifyCustomerHostingProvisioned(Service $service): void {}
        };
        $this->app->instance(HostingProvisioner::class, new HostingProvisioner($this->whm, $notifier));
    }

    private function makeService(string $status = 'queued'): Service
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['company_id' => $customer->company_id]);
        $service = Service::factory()->webHosting()->provisioning($status)->create([
            'company_id' => $customer->company_id,
            'domain_name' => 'job-test.com',
        ]);
        DB::table('services')->where('service_id', $service->service_id)->update(['whm_package' => 'pkg']);

        return $service->fresh();
    }

    public function test_job_configuration(): void
    {
        $job = new ProvisionHostingJob(7);

        $this->assertSame(1, $job->tries);
        $this->assertSame(90, $job->timeout);
        $this->assertCount(1, $job->middleware());
    }

    public function test_job_runs_the_provisioner(): void
    {
        $service = $this->makeService();

        Bus::dispatchSync(new ProvisionHostingJob($service->service_id));

        $fresh = $service->fresh();
        $this->assertSame('succeeded', $fresh->provisioning_status);
        $this->assertNotEmpty($fresh->cpanel_username);
    }

    public function test_held_lock_prevents_a_second_run(): void
    {
        $service = $this->makeService();
        $job = new ProvisionHostingJob($service->service_id);

        $lock = Cache::lock($job->middleware()[0]->getLockKey($job), 300);
        $this->assertTrue($lock->get());

        Bus::dispatchSync($job);

        $this->assertSame('queued', $service->fresh()->provisioning_status);
        $this->assertSame(0, $this->whm->totalCalls());

        $lock->release();
        Bus::dispatchSync(new ProvisionHostingJob($service->service_id));
        $this->assertSame('succeeded', $service->fresh()->provisioning_status);
    }

    public function test_failed_marks_service_failed(): void
    {
        $service = $this->makeService('running');

        (new ProvisionHostingJob($service->service_id))->failed(new \RuntimeException('worker crashed'));

        $fresh = $service->fresh();
        $this->assertSame('failed', $fresh->provisioning_status);
        $this->assertSame('worker crashed', $fresh->provisioning_error);
    }

    public function test_failed_does_not_overwrite_succeeded(): void
    {
        $service = $this->makeService('succeeded');

        (new ProvisionHostingJob($service->service_id))->failed(new \RuntimeException('late failure'));

        $this->assertSame('succeeded', $service->fresh()->provisioning_status);
    }
}
