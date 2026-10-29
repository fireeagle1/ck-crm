<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerApp;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAppController extends Controller
{
    /**
     * Show the form for creating a CustomerApp against an existing Service.
     */
    public function create(Service $service): View
    {
        $service->load('customer');

        return view('admin.customer-apps.create', compact('service'));
    }

    /**
     * Persist a new CustomerApp for the given Service.
     *
     * Rejects the create when the service already has a linked app (Req 1.4);
     * the DB unique index on customer_apps.service_id is the backstop.
     */
    public function store(Request $request, Service $service)
    {
        if ($service->customerApp()->exists()) {
            return back()->withInput()
                ->with('error', 'This service already has a linked app.');
        }

        $validated = $request->validate([
            'repo_url' => 'nullable|url|max:255',
            'hosting_location' => 'nullable|string|max:255',
            'codebase_info' => 'nullable|string',
        ]);

        $customerApp = $service->customerApp()->create([
            'repo_url' => $validated['repo_url'] ?? null,
            'hosting_location' => $validated['hosting_location'] ?? null,
            'codebase_info' => $validated['codebase_info'] ?? null,
        ]);

        return redirect()->route('admin.customer-apps.show', $customerApp)
            ->with('success', 'App created.');
    }

    /**
     * Full technical view: repo, hosting, codebase, all packages, all findings,
     * plus the linked service name/status/billing (Req 4.1, 4.3).
     */
    public function show(CustomerApp $customerApp): View
    {
        $customerApp->load(['service.customer', 'packages', 'findings']);

        return view('admin.customer-apps.show', compact('customerApp'));
    }

    /**
     * Show the form for editing the three editable technical fields.
     */
    public function edit(CustomerApp $customerApp): View
    {
        $customerApp->load('service.customer');

        return view('admin.customer-apps.edit', compact('customerApp'));
    }

    /**
     * Update only repo_url, hosting_location, codebase_info (Req 4.2);
     * billing and service identity stay on the Service record.
     */
    public function update(Request $request, CustomerApp $customerApp)
    {
        $validated = $request->validate([
            'repo_url' => 'nullable|url|max:255',
            'hosting_location' => 'nullable|string|max:255',
            'codebase_info' => 'nullable|string',
        ]);

        $customerApp->update([
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
