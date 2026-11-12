<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DomainRequest;
use App\Models\Customer;
use App\Services\DomainEditTracker;
use App\Models\Domain;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainController extends Controller
{
    public function index(Request $request): View
    {
        $filter = (string) $request->get('filter', 'all');
        if (! in_array($filter, ['all', 'expiring', 'expired', 'unassigned', 'missing'], true)) {
            $filter = 'all';
        }

        $selectedCustomer = null;
        if ($request->filled('customer')) {
            $selectedCustomer = Customer::find($request->input('customer'));
        }

        // Every count and the row list derive from the same model scopes over the same base set.
        $base = fn () => Domain::query()
            ->when($selectedCustomer, fn ($q) => $q->where('company_id', $selectedCustomer->company_id));

        $query = $base()->with('customer');
        match ($filter) {
            'expiring' => $query->expiring(),
            'expired' => $query->expired(),
            'unassigned' => $query->visible()->unassigned(),
            'missing' => $query->missingFromEnom(),
            default => $query->visible(),
        };

        $domains = $query->orderBy('expiry_date')->paginate(20)->withQueryString();

        $totalDomains = $base()->visible()->count();
        $expiringCount = $base()->expiring()->count();
        $expiredCount = $base()->expired()->count();
        $unassignedCount = $base()->visible()->unassigned()->count();
        $missingCount = $base()->missingFromEnom()->count();

        return view('admin.domains.index', compact(
            'domains', 'filter', 'totalDomains', 'expiringCount', 'expiredCount',
            'unassignedCount', 'missingCount', 'selectedCustomer'
        ));
    }

    /** JSON customer lookup for the autocomplete picker (max 20 matches). */
    public function customerSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $query = Customer::query()->orderBy('company_name')->limit(20);
        if ($term !== '') {
            $like = '%' . addcslashes($term, '\\%_') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('company_name', 'like', $like)
                    ->orWhere('customer_name', 'like', $like);
            });
        }

        return response()->json(
            $query->get(['company_id', 'company_name'])
                ->map(fn ($c) => ['id' => $c->company_id, 'name' => $c->company_name])
                ->values()
        );
    }

    public function create(): View
    {
        $selectedCustomer = old('company_id') ? Customer::find(old('company_id')) : null;
        return view('admin.domains.create', compact('selectedCustomer'));
    }

    public function store(DomainRequest $request)
    {
        Domain::create($request->validated());

        return redirect()->route('admin.domains.index')
            ->with('success', 'Domain added.');
    }

    public function edit(Domain $domain): View
    {
        $companyId = old('company_id', $domain->company_id);
        $selectedCustomer = $companyId ? Customer::find($companyId) : null;
        $domain->load('services');
        return view('admin.domains.edit', compact('domain', 'selectedCustomer'));
    }

    public function update(DomainRequest $request, Domain $domain, DomainEditTracker $tracker)
    {
        $validated = $request->validated();

        // Flag eNom-owned fields the admin changed so the sync leaves them alone.
        $tracker->record($domain, $validated);

        $domain->update($validated);

        return back()->with('success', 'Domain updated.');
    }

    /** Hand the eNom-owned fields back to the eNom sync (Req 22.2). */
    public function resetEdits(Domain $domain, DomainEditTracker $tracker)
    {
        $tracker->reset($domain);

        return back()->with('success', "Domain '{$domain->domain_name}' reset to eNom control.");
    }

    /** Inline customer assignment: saves company_id only, never touches eNom fields or edit flags. */
    public function assign(Request $request, Domain $domain)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:customers,company_id'],
        ]);

        $domain->company_id = $validated['company_id'];
        $domain->save();

        return back()->with('success', "Domain '{$domain->domain_name}' assigned.");
    }

    /** Link a Service to this Domain by setting services.domain_id (Req 20.6). */
    public function linkService(Request $request, Domain $domain)
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,service_id'],
        ]);

        $service = Service::findOrFail($validated['service_id']);

        // Relinking the same service to the same domain is a no-op success.
        if ((int) $service->domain_id === (int) $domain->id) {
            return back()->with('success', "Service {$service->service_short} is already linked to '{$domain->domain_name}'.");
        }

        $alreadyLinked = $domain->services()->where('service_id', '!=', $service->service_id)->first();
        if ($alreadyLinked) {
            throw ValidationException::withMessages([
                'service_id' => "Domain '{$domain->domain_name}' is already linked to service {$alreadyLinked->service_short}.",
            ]);
        }

        try {
            $service->domain_id = $domain->id;
            $service->save();
        } catch (QueryException $e) {
            // unique(services.domain_id) violation raced with another link.
            throw ValidationException::withMessages([
                'service_id' => "Domain '{$domain->domain_name}' is already linked to another service.",
            ]);
        }

        return back()->with('success', "Service {$service->service_short} linked to '{$domain->domain_name}'.");
    }

    /** Restore a soft-deleted domain. Looked up by raw id, since route binding excludes trashed rows (Req 22.2). */
    public function restore(int $id)
    {
        $domain = Domain::withTrashed()->findOrFail($id);
        $domain->restore();

        return redirect()->route('admin.domains.edit', $domain)
            ->with('success', "Domain '{$domain->domain_name}' restored.");
    }

    public function destroy(Domain $domain)
    {
        $name = $domain->domain_name;

        // A domain linked to a service is never deleted (Req 15.4, 15.5).
        if ($domain->services()->exists()) {
            $linked = $domain->services()->pluck('service_short')->implode(', ');

            return redirect()->route('admin.domains.index')
                ->with('error', "Domain '{$name}' not deleted, it is linked to service {$linked}.");
        }

        $domain->delete(); // soft delete (Req 15.3)

        return redirect()->route('admin.domains.index')
            ->with('success', "Domain '{$name}' deleted.");
    }
}
