<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Service;
use App\Services\ProvisioningDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin hosting provisioning queue.
 *
 * The controller never talks to WHM. It only hands the service to
 * ProvisioningDispatcher; ProvisionHostingJob / HostingProvisioner do the work
 * (resolving the product's whm_package and sending the customer notification
 * after commit).
 *
 * Requirements: 9.11, 9.12, 9.13, 22.2
 */
class HostingProvisionController extends Controller
{
    public function __construct(
        private ProvisioningDispatcher $dispatcher
    ) {
    }

    /** Valid values for the status filter; 'none' means provisioning_status IS NULL. */
    public const STATUS_FILTERS = ['queued', 'running', 'succeeded', 'failed', 'none'];

    /**
     * Display every Web Hosting service in the provisioning queue, optionally
     * filtered by provisioning status (Requirements 19.1, 19.2, 19.3).
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = is_string($status) && in_array($status, self::STATUS_FILTERS, true) ? $status : null;

        $query = Service::with(['customer', 'domain' => fn ($q) => $q->withTrashed()])
            ->where('service_type', 'Web Hosting');

        if ($status === 'none') {
            $query->whereNull('provisioning_status');
        } elseif ($status !== null) {
            $query->where('provisioning_status', $status);
        }

        $services = $query->orderBy('created_at', 'desc')
            ->orderBy('service_id', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Product comes from the order item that created the service (shop-bought only).
        $itemIds = $services->getCollection()->pluck('order_item_id')->filter()->unique()->all();
        $orderItems = $itemIds === []
            ? collect()
            : OrderItem::with('product')->whereIn('id', $itemIds)->get()->keyBy('id');

        return view('admin.hosting.provision.index', [
            'services' => $services,
            'orderItems' => $orderItems,
            'status' => $status,
        ]);
    }

    /**
     * Queue provisioning for a service that has not been provisioned yet.
     */
    public function provision(Service $service): RedirectResponse
    {
        $queued = $this->dispatcher->dispatch($service, null, ProvisioningDispatcher::CONTEXT_ADMIN);

        return $this->respond($service, $queued, 'queued for provisioning', 'This service is already queued, running or provisioned.');
    }

    /**
     * Retry provisioning; only accepted for services in the 'failed' state.
     */
    public function retry(Service $service): RedirectResponse
    {
        $queued = $this->dispatcher->retry($service, null, ProvisioningDispatcher::CONTEXT_ADMIN);

        return $this->respond($service, $queued, 'queued for a provisioning retry', 'Only services whose provisioning failed can be retried.');
    }

    private function respond(Service $service, bool $queued, string $successText, string $failureText): RedirectResponse
    {
        $redirect = redirect()->route('admin.hosting.provision.index');

        if (! $queued) {
            return $redirect->with('error', $failureText);
        }

        return $redirect->with('success', "Hosting for {$service->domain_name} {$successText}.");
    }
}
