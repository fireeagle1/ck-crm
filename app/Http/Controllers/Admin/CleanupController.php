<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $duplicateDomains = Domain::with(['customer', 'services'])
            ->whereIn(
                DB::raw('LOWER(domain_name)'),
                Service::select(DB::raw('LOWER(domain_name)'))->whereNotNull('domain_name')
            )->get();

        // Services with domain_name that matches a domain record (one set-based query)
        $servicesWithMatchingDomain = Service::with(['customer', 'domain'])
            ->whereNotNull('domain_name')
            ->whereIn(
                DB::raw('LOWER(domain_name)'),
                Domain::select(DB::raw('LOWER(domain_name)'))
            )
            ->get();

        // Cancelled services (candidates for deletion)
        $cancelledServices = Service::with('customer')
            ->where('status', 'Cancelled')
            ->orderByDesc('end_date')
            ->get();

        // Expired domains over 1 year old
        $oldExpiredDomains = Domain::with(['customer', 'services'])
            ->whereDate('expiry_date', '<', now()->subYear())
            ->orderBy('expiry_date')
            ->get();

        // Orphaned services (no customer)
        $orphanedServices = Service::with('customer')
            ->where(function ($q) {
                $q->whereNull('company_id')
                    ->orWhereNotIn('company_id', Customer::select('company_id'));
            })
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

        // Domains linked to a service (services.domain_id) are never deleted.
        $linked = Domain::with('services:service_id,domain_id,service_short')
            ->whereIn('id', $validated['domain_ids'])
            ->whereHas('services')
            ->get();

        $linkedIds = $linked->pluck('id')->all();
        $deletableIds = array_values(array_diff($validated['domain_ids'], $linkedIds));

        // Soft delete (Domain uses SoftDeletes) in a single statement.
        $count = $deletableIds === [] ? 0 : Domain::whereIn('id', $deletableIds)->delete();

        $redirect = back();

        if ($linked->isNotEmpty()) {
            $refused = $linked->map(function (Domain $d) {
                return $d->domain_name.' (linked to service '.$d->services->pluck('service_short')->implode(', ').')';
            })->implode('; ');

            $redirect = $redirect->with('error', "Not deleted, linked to a service: {$refused}.");
        }

        return $redirect->with('success', "{$count} domain(s) deleted.");
    }
}
