<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $query = Asset::with('customer');

        // Search by device name, serial number, or customer name
        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('device_name', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($cq) use ($q) {
                        $cq->where('company_name', 'like', "%{$q}%");
                    });
            });
        }

        $assets = $query->orderByDesc('device_id')->paginate(20);

        return view('admin.assets.index', compact('assets'));
    }

    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.assets.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,company_id',
            'device_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'asset_status' => 'in:Active,Decommissioned,In Repair',
            'device_type' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        Asset::create($validated);

        return redirect()->route('admin.assets.index')
            ->with('success', 'Asset created.');
    }

    public function show(Asset $asset): View
    {
        $asset->load(['customer', 'tickets']);

        return view('admin.assets.show', compact('asset'));
    }

    public function edit(Asset $asset): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.assets.edit', compact('asset', 'customers'));
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,company_id',
            'device_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'asset_status' => 'in:Active,Decommissioned,In Repair',
            'device_type' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $asset->update($validated);

        return back()->with('success', 'Asset updated.');
    }
}
