<?php

namespace App\Services;

use App\Jobs\ProvisionHostingJob;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Moves a service into the provisioning queue with an atomic guard and
 * dispatches ProvisionHostingJob only when the guard row-update succeeded.
 *
 * Requirements: 9.2, 9.11, 9.12
 */
class ProvisioningDispatcher
{
    public const CONTEXT_WEBHOOK = 'webhook';

    public const CONTEXT_ADMIN = 'admin';

    /**
     * Queue provisioning from a NULL or 'failed' status.
     *
     * @param  string  $context  CONTEXT_WEBHOOK or CONTEXT_ADMIN; only matters when queue.default is 'sync'.
     * @return bool true when the service was queued and a job dispatched
     */
    public function dispatch(Service $service, ?string $username = null, string $context = self::CONTEXT_ADMIN): bool
    {
        return $this->claimAndDispatch($service, $username, $context, allowNull: true);
    }

    /**
     * Retry provisioning; accepted only from 'failed'.
     */
    public function retry(Service $service, ?string $username = null, string $context = self::CONTEXT_ADMIN): bool
    {
        return $this->claimAndDispatch($service, $username, $context, allowNull: false);
    }

    private function claimAndDispatch(Service $service, ?string $username, string $context, bool $allowNull): bool
    {
        $now = now();

        $affected = DB::table('services')
            ->where('service_id', $service->service_id)
            ->where(function ($q) use ($allowNull) {
                $q->where('provisioning_status', 'failed');
                if ($allowNull) {
                    $q->orWhereNull('provisioning_status');
                }
            })
            ->update([
                'provisioning_status' => 'queued',
                'provisioning_error' => null,
                'provisioning_updated_at' => $now,
            ]);

        if ($affected === 0) {
            return false;
        }

        $service->forceFill([
            'provisioning_status' => 'queued',
            'provisioning_error' => null,
            'provisioning_updated_at' => $now,
        ])->syncOriginal();

        $id = (int) $service->service_id;

        if (config('queue.default') === 'sync') {
            // Webhook callers answer Stripe first; admin callers run inline.
            if ($context === self::CONTEXT_WEBHOOK) {
                ProvisionHostingJob::dispatchAfterResponse($id, $username);
            } else {
                ProvisionHostingJob::dispatchSync($id, $username);
            }

            return true;
        }

        ProvisionHostingJob::dispatch($id, $username)->afterCommit();

        return true;
    }
}
