<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerTierController extends Controller
{
    public function index(): View
    {
        $tiers = CustomerTier::withCount('customers')->with('customers')->get();
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.shop.tiers.index', compact('tiers', 'customers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        CustomerTier::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        return redirect()->route('admin.shop.tiers.index')
            ->with('success', 'Tier created.');
    }

    public function update(Request $request, CustomerTier $tier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'customers' => 'nullable|array',
            'customers.*' => 'exists:customers,company_id',
        ]);

        $tier->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        $tier->customers()->sync($validated['customers'] ?? []);

        return redirect()->route('admin.shop.tiers.index')
            ->with('success', 'Tier updated.');
    }

    public function destroy(CustomerTier $tier): RedirectResponse
    {
        $tier->delete();

        return redirect()->route('admin.shop.tiers.index')
            ->with('success', 'Tier deleted.');
    }
}
