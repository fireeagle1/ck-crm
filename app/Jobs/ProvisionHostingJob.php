<?php

namespace App\Jobs;

use App\Services\HostingProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Provisions a WHM hosting account for a service.
 *
 * Requirements: 9.3, 9.4, 9.10
 */
class ProvisionHostingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public int $serviceId,
        public ?string $requestedUsername = null,
    ) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("provision-service-{$this->serviceId}"))
                ->dontRelease()
                ->expireAfter(300),
        ];
    }

    public function handle(HostingProvisioner $provisioner): void
    {
        $provisioner->provision($this->serviceId, $this->requestedUsername);
    }

    /** Crash, timeout or exception: record failure unless already succeeded. */
    public function failed(?Throwable $exception): void
    {
        app(HostingProvisioner::class)->markFailed(
            $this->serviceId,
            $exception?->getMessage() ?: 'Provisioning job failed.'
        );
    }
}
