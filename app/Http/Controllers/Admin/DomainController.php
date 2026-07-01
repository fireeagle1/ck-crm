<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->get('filter', 'all');

        $query = Domain::with('customer');

        if ($filter === 'expiring') {
            $query->whereDate('expiry_date', '<=', now()->addDays(30))
                  ->whereDate('expiry_date', '>=', now());
        } elseif ($filter === 'expired') {
            $query->whereDate('expiry_date', '<', now());
        }

        $domains = $query->orderBy('expiry_date')->paginate(20);

        $totalDomains = Domain::count();
        $expiringCount = Domain::whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())->count();
        $expiredCount = Domain::whereDate('expiry_date', '<', now())->count();

        return view('admin.domains.index', compact('domains', 'filter', 'totalDomains', 'expiringCount', 'expiredCount'));
    }

    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();
        return view('admin.domains.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'domain_name' => 'required|string|max:255|unique:domains,domain_name',
            'company_id' => 'nullable|exists:customers,company_id',
            'registrar' => 'nullable|string|max:255',
            'registration_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
            'domain_admin_notes' => 'nullable|string',
        ]);

        Domain::create($validated);

        return redirect()->route('admin.domains.index')
            ->with('success', 'Domain added.');
    }

    public function edit(Domain $domain): View
    {
        $customers = Customer::orderBy('company_name')->get();
        return view('admin.domains.edit', compact('domain', 'customers'));
    }

    public function update(Request $request, Domain $domain)
    {
        $validated = $request->validate([
            'domain_name' => 'required|string|max:255|unique:domains,domain_name,' . $domain->id,
            'company_id' => 'nullable|exists:customers,company_id',
            'registrar' => 'nullable|string|max:255',
            'registration_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
            'domain_admin_notes' => 'nullable|string',
        ]);

        $domain->update($validated);

        return back()->with('success', 'Domain updated.');
    }

    public function destroy(Domain $domain)
    {
        $name = $domain->domain_name;
        $domain->delete();

        return redirect()->route('admin.domains.index')
            ->with('success', "Domain '{$name}' deleted.");
    }
}
