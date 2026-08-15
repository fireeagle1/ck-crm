<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WhmProvisioningException;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\WhmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HostingProvisionController extends Controller
{
    public function __construct(
        private WhmService $whmService,
        private NotificationService $notificationService
    ) {
    }

    /**
     * Display pending hosting services in the provisioning queue.
     *
     * Requirements: 3.2
     */
    public function index(): View
    {
        $pendingServices = Service::with('customer')
            ->where('status', 'pending')
            ->where('service_type', 'Web Hosting')
            ->whereNotNull('domain_name')
            ->orderBy('created_at', 'asc')
            ->get();

        return view('admin.hosting.provision.index', compact('pendingServices'));
    }

    /**
     * Provision a pending hosting service via WHM API.
     *
     * On success: update Service status to 'Active', store cpanel_username,
     * update associated Order fulfilment_status to 'completed', trigger notification.
     * On failure: redirect back with error message.
     *
     * Requirements: 3.3, 3.4, 3.5, 3.6
     */
    public function provision(Service $service): RedirectResponse
    {
        // Ensure service is still pending
        if ($service->status !== 'pending') {
            return redirect()->route('admin.hosting.provision.index')
                ->with('error', 'This service has already been provisioned.');
        }

        $package = Setting::get('whm_default_package', 'default');
        $email = $service->customer->users()->first()?->email ?? '';

        try {
            $result = $this->whmService->createAccount(
                $service->domain_name,
                $package,
                $email
            );

            // Update service status and store cPanel username
            $service->update([
                'status' => 'Active',
                'cpanel_username' => $result['username'],
            ]);

            // Find the related Order through OrderItem and mark fulfilment as completed
            $orderItem = OrderItem::where('service_id', $service->service_id)->first();
            if ($orderItem && $orderItem->order) {
                $orderItem->order->update(['fulfilment_status' => 'completed']);
            }

            // Send provisioned notification to the customer (Req 3.6)
            $this->notificationService->notifyCustomerHostingProvisioned($service);

            return redirect()->route('admin.hosting.provision.index')
                ->with('success', "Hosting account provisioned successfully for {$service->domain_name} (username: {$result['username']}).");

        } catch (WhmProvisioningException $e) {
            return redirect()->route('admin.hosting.provision.index')
                ->with('error', "Provisioning failed for {$service->domain_name}: {$e->getMessage()}");
        }
    }
}
