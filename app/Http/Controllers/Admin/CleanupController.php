<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CleanupController extends Controller
{
    public function index(): View
    {
        // Services that look like they're just domain names (no cpanel, no charge, type is Domain Registration or blank)
        $domainServices = Service::with('customer')
            ->where(function ($q) {
                $q->where('service_type', 'Domain Registration')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('service_type')
                         ->whereNull('cpanel_username')
                         ->where(function ($q3) {
                             $q3->whereNull('service_monthly_charge')
                                ->orWhere('service_monthly_charge', '<=', 0);
                         });
                  });
            })
            ->orderBy('service_short')
            ->get();

        // Domains in the domains table that also exist as services
        $duplicateDomains = Domain::whereIn(
            'domain_name',
            Service::whereNotNull('domain_name')->pluck('domain_name')->map(fn($d) => strtolower($d))
        )->get();

        // Services with domain_name that matches a domain record
        $servicesWithMatchingDomain = Service::whereNotNull('domain_name')
            ->with('customer')
            ->get()
            ->filter(function ($s) {
                return Domain::where('domain_name', strtolower($s->domain_name))->exists();
            });

        // Cancelled services (candidates for deletion)
        $cancelledServices = Service::with('customer')
            ->where('status', 'Cancelled')
            ->orderByDesc('end_date')
            ->get();

        // Expired domains over 1 year old
        $oldExpiredDomains = Domain::with('customer')
            ->whereDate('expiry_date', '<', now()->subYear())
            ->orderBy('expiry_date')
            ->get();

        // Orphaned services (no customer)
        $orphanedServices = Service::whereNull('company_id')
            ->orWhereNotIn('company_id', \App\Models\Customer::pluck('company_id'))
            ->get();

        return view('admin.cleanup.index', compact(
            'domainServices',
            'duplicateDomains',
            'servicesWithMatchingDomain',
            'cancelledServices',
            'oldExpiredDomains',
            'orphanedServices',
        ));
    }

    public function deleteServices(Request $request)
    {
        $validated = $request->validate([
            'service_ids' => 'required|array',
            'service_ids.*' => 'exists:services,service_id',
        ]);

        $count = Service::whereIn('service_id', $validated['service_ids'])->delete();

        return back()->with('success', "{$count} service(s) deleted.");
    }

    public function deleteDomains(Request $request)
    {
        $validated = $request->validate([
            'domain_ids' => 'required|array',
            'domain_ids.*' => 'exists:domains,id',
        ]);

        $count = Domain::whereIn('id', $validated['domain_ids'])->delete();

        return back()->with('success', "{$count} domain(s) deleted.");
    }
}
