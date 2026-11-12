<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Exceptions\InvalidDomainException;
use App\Exceptions\WhmProvisioningException;
use App\Models\Service;
use App\Support\CpanelUsername;
use App\Support\DomainNormaliser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Testable core of hosting provisioning: claim, input resolution, and (task 6.13)
 * account creation / linking.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 9.5, 9.10
 */
class HostingProvisioner
{
    public const ERROR_MAX_LENGTH = 1000;

    public function __construct(
        protected WhmService $whm,
        protected NotificationService $notifications,
    ) {}

    /**
     * Provision a service that has been queued.
     *
     * @return bool true when this call completed provisioning; false when it
     *              did not claim the row or an input precondition failed
     */
    public function provision(int $serviceId, ?string $username = null): bool
    {
        if (! $this->claim($serviceId)) {
            return false;
        }

        $service = Service::query()->find($serviceId);
        if ($service === null) {
            return false;
        }

        try {
            $inputs = $this->resolveInputs($service);
        } catch (WhmProvisioningException $e) {
            // Precondition failure: recorded on the service, no WHM call was made.
            $this->markFailed($serviceId, $e->getMessage());

            return false;
        } catch (Throwable $e) {
            $this->markFailed($serviceId, $e->getMessage());

            throw $e;
        }

        try {
            $this->createOrLink($service, $inputs, $username);
        } catch (Throwable $e) {
            $this->markFailed($serviceId, $e->getMessage());

            throw $e;
        }

        return true;
    }

    /**
     * Atomically move queued -> running. Returns false when another worker holds
     * the row, it is already finished (succeeded), or it is not queued.
     */
    public function claim(int $serviceId): bool
    {
        $affected = DB::table('services')
            ->where('service_id', $serviceId)
            ->where('provisioning_status', 'queued')
            ->update([
                'provisioning_status' => 'running',
                'provisioning_updated_at' => now(),
            ]);

        return $affected === 1;
    }

    /**
     * Resolve package, customer email and canonical domain. Makes no WHM call.
     *
     * @return array{package: string, email: string, domain: string}
     *
     * @throws WhmProvisioningException when package, email or domain is missing
     */
    public function resolveInputs(Service $service): array
    {
        $package = $this->resolvePackage($service);
        if ($package === null) {
            throw new WhmProvisioningException(
                "No WHM package for service {$service->service_id}: set whm_package on the product or the service."
            );
        }

        $email = $this->resolveEmail($service);
        if ($email === null) {
            throw new WhmProvisioningException(
                "No customer email for service {$service->service_id}: the customer has no user with an email address."
            );
        }

        try {
            $domain = DomainNormaliser::normalise((string) $service->domain_name);
        } catch (InvalidDomainException $e) {
            throw new WhmProvisioningException(
                "Service {$service->service_id} has no valid domain: ".$e->getMessage(), 0, $e
            );
        }

        return ['package' => $package, 'email' => $email, 'domain' => $domain];
    }

    /**
     * Record a failure: status failed, error truncated. Touches nothing else.
     * Does not overwrite a succeeded service.
     */
    public function markFailed(int $serviceId, string $error): void
    {
        DB::table('services')
            ->where('service_id', $serviceId)
            ->where(function ($q) {
                $q->whereNull('provisioning_status')->orWhere('provisioning_status', '!=', 'succeeded');
            })
            ->update([
                'provisioning_status' => 'failed',
                'provisioning_error' => mb_substr($error, 0, self::ERROR_MAX_LENGTH),
                'provisioning_updated_at' => now(),
            ]);
    }

    public const MAX_USERNAME_CANDIDATES = 99;

    /**
     * Link an existing WHM account for the domain, or create one, then record
     * the outcome. Any exception propagates (the caller marks the service
     * failed); nothing but the transactional success write touches WHM fields.
     *
     * Requirements: 8.6, 8.7, 8.8, 9.6, 9.7
     *
     * @param  array{package: string, email: string, domain: string}  $inputs
     */
    protected function createOrLink(Service $service, array $inputs, ?string $username): void
    {
        $existing = $this->whm->findAccountByDomain($inputs['domain']);

        if ($existing !== null) {
            $owner = $this->usernameOwner($existing['user'], $service->service_id);
            if ($owner !== null) {
                throw new WhmProvisioningException(
                    "WHM account '{$existing['user']}' for {$inputs['domain']} is already owned by service {$owner}."
                );
            }
            $chosen = $existing['user'];
        } else {
            $chosen = $this->pickUsername($inputs['domain'], $username);
            $created = $this->whm->createAccount($inputs['domain'], $inputs['package'], $inputs['email'], $chosen);
            $chosen = $created['username'] ?? $chosen;
        }

        $this->recordSuccess($service, $chosen);

        // Runs after the success transaction has committed. A send failure must
        // never undo or fail a succeeded provisioning (Req 9.8, 9.9).
        try {
            $this->notifications->notifyCustomerHostingProvisioned($service->fresh() ?? $service);
        } catch (Throwable $e) {
            Log::error('HostingProvisioner: provisioned notification failed', [
                'service_id' => $service->service_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** service_id of another service holding this cPanel username, or null. */
    private function usernameOwner(string $username, int $exceptServiceId): ?int
    {
        $id = DB::table('services')
            ->whereRaw('LOWER(cpanel_username) = ?', [strtolower($username)])
            ->where('service_id', '!=', $exceptServiceId)
            ->value('service_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @throws WhmProvisioningException when no free username is found
     */
    private function pickUsername(string $domain, ?string $hint): string
    {
        $taken = [];
        foreach (DB::table('services')->whereNotNull('cpanel_username')->pluck('cpanel_username') as $u) {
            $taken[strtolower((string) $u)] = true;
        }
        foreach ($this->whm->listAccounts() as $account) {
            $taken[strtolower((string) $account['user'])] = true;
        }

        if ($hint !== null) {
            $hint = strtolower(trim($hint));
            if (CpanelUsername::isValid($hint) && ! isset($taken[$hint])) {
                return $hint;
            }
        }

        $tried = 0;
        foreach (CpanelUsername::candidates($domain) as $candidate) {
            if ($tried++ >= self::MAX_USERNAME_CANDIDATES) {
                break;
            }
            if (! isset($taken[$candidate])) {
                return $candidate;
            }
        }

        throw new WhmProvisioningException("No free cPanel username available for {$domain}.");
    }

    /** Persist success and complete the order when every item is hosting and succeeded. */
    private function recordSuccess(Service $service, string $username): void
    {
        DB::transaction(function () use ($service, $username) {
            DB::table('services')
                ->where('service_id', $service->service_id)
                ->update([
                    'cpanel_username' => $username,
                    'provisioning_status' => 'succeeded',
                    'provisioning_error' => null,
                    'provisioning_updated_at' => now(),
                    'status' => ServiceStatus::Active->value,
                ]);

            $orderId = $service->order_item_id === null ? null : DB::table('order_items')
                ->where('id', $service->order_item_id)
                ->value('order_id');

            if ($orderId !== null && $this->allItemsProvisioned((int) $orderId)) {
                DB::table('orders')
                    ->where('id', $orderId)
                    ->where('fulfilment_status', '!=', 'completed')
                    ->update(['fulfilment_status' => 'completed', 'fulfilled_at' => now()]);
            }
        });
    }

    private function allItemsProvisioned(int $orderId): bool
    {
        $items = DB::table('order_items')->where('order_id', $orderId)->get(['id', 'product_type']);
        if ($items->isEmpty()) {
            return false;
        }

        foreach ($items as $item) {
            if ($item->product_type !== 'hosting') {
                return false;
            }
            $status = DB::table('services')->where('order_item_id', $item->id)->value('provisioning_status');
            if ($status !== 'succeeded') {
                return false;
            }
        }

        return true;
    }

    private function resolvePackage(Service $service): ?string
    {
        if ($service->order_item_id !== null) {
            $fromProduct = DB::table('order_items')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('order_items.id', $service->order_item_id)
                ->value('products.whm_package');

            if (is_string($fromProduct) && trim($fromProduct) !== '') {
                return trim($fromProduct);
            }
        }

        $own = $service->whm_package;

        return is_string($own) && trim($own) !== '' ? trim($own) : null;
    }

    private function resolveEmail(Service $service): ?string
    {
        $email = DB::table('users')
            ->where('company_id', $service->company_id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->value('email');

        return is_string($email) && trim($email) !== '' ? trim($email) : null;
    }
}
