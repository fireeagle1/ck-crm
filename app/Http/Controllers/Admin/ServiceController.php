<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::with('customer')
            ->orderByDesc('service_id')
            ->paginate(20);

        return view('admin.services.index', compact('services'));
    }

    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.services.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:customers,company_id',
            'service_short' => 'required|string|max:255',
            'status' => 'in:Active,Suspended,Cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'service_monthly_charge' => 'nullable|numeric|min:0',
            'service_payment_frequency' => 'nullable|string',
        ]);

        Service::create($validated);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created.');
    }
}
