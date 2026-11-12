<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Exceptions\DomainAlreadyInUseException;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Service;
use App\Support\DomainNormaliser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Single code path that turns a hosting order item into a Service, keyed on order_item_id.
 * whm_package is intentionally not written here.
 */
class HostingServiceCreator
{
    public function __construct(protected DomainLinker $domainLinker) {}

    /**
     * @throws DomainAlreadyInUseException when another service already holds the domain
     */
    public function forOrderItem(OrderItem $item, Customer $customer): Service
    {
        return DB::transaction(function () use ($item, $customer) {
            $existing = Service::where('order_item_id', $item->id)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $canonical = DomainNormaliser::normalise((string) $item->domain_name);
            $domain = $this->domainLinker->findOrCreate($canonical, $customer->company_id);

            try {
                // Nested transaction = savepoint, so a failed insert cannot poison the outer one.
                $service = DB::transaction(fn () => Service::create([
                    'company_id' => $customer->company_id,
                    'service_short' => $item->product_name,
                    'service_type' => ServiceType::WebHosting->value,
                    'status' => ServiceStatus::Pending->value,
                    'domain_name' => $canonical,
                    'domain_id' => $domain->id,
                    'order_item_id' => $item->id,
                    'start_date' => now(),
                    'service_monthly_charge' => $item->price,
                    'service_payment_frequency' => $item->billing_frequency,
                    'stripe_subscription_id' => $item->stripe_subscription_id,
                ]));
            } catch (UniqueConstraintViolationException $e) {
                $forItem = Service::where('order_item_id', $item->id)->first();
                if ($forItem !== null) {
                    return $forItem;
                }

                $holder = Service::where('domain_name', $canonical)->first();

                throw new DomainAlreadyInUseException($canonical, $holder?->service_id, $e);
            }

            $item->service_id = $service->service_id;
            $item->forceFill(['domain_id' => $domain->id])->save();

            return $service;
        });
    }
}
