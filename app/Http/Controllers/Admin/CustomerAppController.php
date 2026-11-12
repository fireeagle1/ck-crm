<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerApp;
use App\Models\Domain;
use App\Models\Service;
use App\Services\CustomerAppService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAppController extends Controller
{
    /**
     * List every CustomerApp (the "Web App register") with its linked service,
     * customer, and child counts. This is the standalone register view, as
     * opposed to the per-service create flow.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $customerApps = CustomerApp::query()
            ->with(['service.customer', 'domain'])
            ->withCount(['packages', 'findings', 'openFindings'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('repo_url', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('install_url', 'like', "%{$search}%")
                        ->orWhere('hosting_location', 'like', "%{$search}%")
                        ->orWhereHas('service', function ($service) use ($search) {
                            $service->where('service_short', 'like', "%{$search}%")
                                ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', "%{$search}%"));
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.customer-apps.index', compact('customerApps', 'search'));
    }

    /**
     * Pick a service to attach a new app to. Only services without an existing
     * app are selectable, since the link is 1:1 (Req 1.4).
     */
    public function pick(): View
    {
        $services = Service::query()
            ->whereDoesntHave('customerApp')
            ->with('customer')
            ->orderByDesc('service_id')
            ->get();

        return view('admin.customer-apps.pick', compact('services'));
    }

    /**
     * Show the form for creating a CustomerApp against an existing Service.
     */
    public function create(Service $service): View
    {
        $service->load(['customer', 'domain']);

        // Prefill from the service's linked Domain (Req 16.6, 21.2).
        $domain = $service->domain;
        $domains = Domain::query()
            ->where('company_id', $service->company_id)
            ->orderBy('domain_name')
            ->get();
        if ($domain && ! $domains->contains('id', $domain->id)) {
            $domains->push($domain);
        }
        $defaultInstallUrl = $domain ? 'https://'.$domain->domain_name : null;

        return view('admin.customer-apps.create', compact('service', 'domain', 'domains', 'defaultInstallUrl'));
    }

    /**
     * Persist a new CustomerApp for the given Service.
     *
     * A duplicate create surfaces as a ValidationException on `service_id`
     * from CustomerAppService (Req 1.4, 16.3); the DB unique index is the
     * source of truth, so there is no check-then-insert pre-check here.
     */
    public function store(Request $request, Service $service, CustomerAppService $customerApps)
    {
        $validated = $request->validate([
            'domain_id' => 'nullable|integer|exists:domains,id',
            'display_name' => 'nullable|string|max:255',
            'install_url' => 'nullable|url|max:255',
            'repo_url' => 'nullable|url|max:255',
            'hosting_location' => 'nullable|string|max:255',
            'codebase_info' => 'nullable|string',
        ]);

        $domain = isset($validated['domain_id']) ? Domain::find($validated['domain_id']) : null;

        $customerApp = $customerApps->createForService($service, $validated, $domain);

        return redirect()->route('admin.customer-apps.show', $customerApp)
            ->with('success', 'App created.');
    }

    /**
     * Full technical view: repo, hosting, codebase, all packages, all findings,
     * plus the linked service name/status/billing (Req 4.1, 4.3).
     */
    public function show(CustomerApp $customerApp): View
    {
        $customerApp->load(['service.customer', 'domain', 'packages', 'findings']);

        return view('admin.customer-apps.show', compact('customerApp'));
    }

    /**
     * Show the form for editing the editable app fields.
     */
    public function edit(CustomerApp $customerApp): View
    {
        $customerApp->load('service.customer');

        return view('admin.customer-apps.edit', compact('customerApp'));
    }

    /**
     * Update only display_name, install_url, repo_url, hosting_location and
     * codebase_info (Req 4.2);
     * billing and service identity stay on the Service record.
     */
    public function update(Request $request, CustomerApp $customerApp)
    {
        $validated = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'install_url' => 'nullable|url|max:255',
            'repo_url' => 'nullable|url|max:255',
            'hosting_location' => 'nullable|string|max:255',
            'codebase_info' => 'nullable|string',
        ]);

        $customerApp->update([
            'display_name' => $validated['display_name'] ?? null,
            'install_url' => $validated['install_url'] ?? null,
            'repo_url' => $validated['repo_url'] ?? null,
            'hosting_location' => $validated['hosting_location'] ?? null,
            'codebase_info' => $validated['codebase_info'] ?? null,
        ]);

        return redirect()->route('admin.customer-apps.show', $customerApp)
            ->with('success', 'App updated.');
    }

    /**
     * Remove the CustomerApp (cascades to packages and findings via the DB).
     */
    public function destroy(CustomerApp $customerApp)
    {
        $customerApp->delete();

        return redirect()->route('admin.services.show', $customerApp->service_id)
            ->with('success', 'App deleted.');
    }
}
