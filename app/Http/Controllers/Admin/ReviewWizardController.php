<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use Illuminate\Http\Request;
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

    public function moveToDomain(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,service_id',
            'customer' => 'required|exists:customers,company_id',
        ]);

        $service = Service::find($validated['service_id']);

        if (!$service->domain_name) {
            return back()->with('error', 'This service has no domain name to move.');
        }

        $existing = Domain::where('domain_name', strtolower($service->domain_name))->first();

        if ($existing) {
            // Update the existing domain with subscription ID if the service had one
            if ($service->stripe_subscription_id && !$existing->stripe_subscription_id) {
                $existing->update([
                    'stripe_subscription_id' => $service->stripe_subscription_id,
                    'cost' => $service->service_monthly_charge,
                ]);
            }
            $service->delete();
            return redirect()->route('admin.cleanup.review', ['customer' => $validated['customer']])
                ->with('success', "Domain '{$service->domain_name}' already exists. Subscription transferred. Service deleted.");
        }

        // Create domain record from the service
        Domain::create([
            'company_id' => $service->company_id,
            'domain_name' => strtolower($service->domain_name),
            'registrar' => 'Unknown',
            'stripe_subscription_id' => $service->stripe_subscription_id,
            'cost' => $service->service_monthly_charge,
        ]);

        $domainName = $service->domain_name;
        $service->delete();

        return redirect()->route('admin.cleanup.review', ['customer' => $validated['customer']])
            ->with('success', "Moved '{$domainName}' to domains table (with subscription) and removed the service.");
    }
}
