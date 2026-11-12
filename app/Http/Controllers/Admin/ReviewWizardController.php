<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use App\Services\DomainLinker;
use App\Support\DomainNormaliser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewWizardController extends Controller
{
    public function index(Request $request): View
    {
        // Get customers with potential issues (multiple services or unmapped)
        $customers = Customer::withCount('services')
            ->having('services_count', '>', 0)
            ->orderBy('company_name')
            ->get();

        // Current customer being reviewed
        $currentId = $request->get('customer');
        $current = null;
        $services = collect();
        $domains = collect();

        if ($currentId) {
            $current = Customer::find($currentId);
            if ($current) {
                $services = Service::where('company_id', $current->company_id)
                    ->orderByDesc('status')
                    ->orderBy('service_short')
                    ->get();
                $domains = Domain::where('company_id', $current->company_id)->get();
            }
        }

        return view('admin.cleanup.review-wizard', compact('customers', 'current', 'services', 'domains'));
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'service_ids' => 'required|array',
            'service_ids.*' => 'exists:services,service_id',
        ]);

        $count = Service::whereIn('service_id', $validated['service_ids'])->delete();

        return back()->with('success', "{$count} service(s) deleted.");
    }

    public function moveToDomain(Request $request, DomainLinker $linker)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,service_id',
            'customer' => 'required|exists:customers,company_id',
        ]);

        $service = Service::find($validated['service_id']);

        if (!$service->domain_name) {
            return back()->with('error', 'This service has no domain name to move.');
        }

        $canonical = DomainNormaliser::tryNormalise($service->domain_name);

        if ($canonical === null) {
            return back()->with('error', 'This service has an invalid domain name and cannot be moved.');
        }

        // The Service is never deleted: it is linked to the Domain instead.
        DB::transaction(function () use ($service, $canonical, $linker) {
            $domain = $linker->findOrCreate($canonical, $service->company_id);

            $domainUpdates = [];
            $serviceUpdates = ['domain_id' => $domain->id];

            // Move billing data only where the Domain has none; existing values win.
            if ($service->stripe_subscription_id) {
                if (!$domain->stripe_subscription_id) {
                    $domainUpdates['stripe_subscription_id'] = $service->stripe_subscription_id;
                    $serviceUpdates['stripe_subscription_id'] = null;
                } elseif ($domain->stripe_subscription_id === $service->stripe_subscription_id) {
                    // Already held by the Domain: drop the duplicate copy.
                    $serviceUpdates['stripe_subscription_id'] = null;
                }
            }
            if ($domain->cost === null && $service->service_monthly_charge !== null) {
                $domainUpdates['cost'] = $service->service_monthly_charge;
            }

            if ($domainUpdates) {
                $domain->update($domainUpdates);
            }
            $service->update($serviceUpdates);
        });

        return redirect()->route('admin.cleanup.review', ['customer' => $validated['customer']])
            ->with('success', "Linked '{$canonical}' to its domain record. Subscription and cost moved where the domain had none. Service kept.");
    }
}
