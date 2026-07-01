<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        $services = Service::where('company_id', $companyId)
            ->where('service_short', '!=', 'Technical Support Package')
            ->whereNotIn('status', ['Cancelled'])
            ->orderByDesc('service_id')
            ->paginate(10);

        // Check if they have support plan
        $hasSupportPlan = Service::where('company_id', $companyId)
            ->where('service_short', 'Technical Support Package')
            ->where('status', 'Active')
            ->exists();

        // Get customer's domains so we can show the tick
        $domains = Domain::where('company_id', $companyId)->get();

        return view('portal.services.index', compact('services', 'domains', 'hasSupportPlan'));
    }

    public function show(Request $request, Service $service): View
    {
        if ($service->company_id !== $request->user()->company_id) {
            abort(403);
        }

        // Check if domain is managed by us
        $managedDomain = $service->domain_name
            ? Domain::where('company_id', $request->user()->company_id)
                ->where('domain_name', strtolower($service->domain_name))
                ->first()
            : null;

        return view('portal.services.show', compact('service', 'managedDomain'));
    }
}
