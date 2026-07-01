<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::where('company_id', $request->user()->company_id)
            ->where('service_short', '!=', 'Technical Support Package')
            ->orderByDesc('service_id')
            ->paginate(10);

        return view('portal.services.index', compact('services'));
    }

    public function show(Request $request, Service $service): View
    {
        // Ensure the service belongs to the user's company
        if ($service->company_id !== $request->user()->company_id) {
            abort(403);
        }

        return view('portal.services.show', compact('service'));
    }
}
