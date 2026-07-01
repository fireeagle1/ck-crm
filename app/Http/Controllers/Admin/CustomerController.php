<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        $customers = Customer::withCount(['services', 'users', 'tickets'])
            ->orderByDesc('company_id')
            ->paginate(20);

        return view('admin.customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'stripe_customer_id' => 'nullable|string|max:255',
        ]);

        $customer = Customer::create($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        $customer->load(['services', 'invoices', 'tickets', 'users', 'assets', 'domains', 'articles']);

        $totalIncome = $customer->invoices()
            ->where('invoice_status', 'Paid')
            ->sum('invoice_amount');

        return view('admin.customers.show', compact('customer', 'totalIncome'));
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'stripe_customer_id' => 'nullable|string|max:255',
        ]);

        $customer->update($validated);

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        // Only allow deletion if the customer has no active services
        if ($customer->services()->where('status', 'Active')->exists()) {
            return back()->with('error', 'Cannot delete a customer with active services. Cancel or remove their services first.');
        }

        $name = $customer->company_name;
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', "Customer '{$name}' deleted.");
    }
}
