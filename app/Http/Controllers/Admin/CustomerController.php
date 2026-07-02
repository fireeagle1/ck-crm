<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = Customer::withCount(['services', 'users', 'tickets']);

        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('company_name', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%");
            });
        }

        $customers = $query->orderByDesc('company_id')
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
        ]);

        $customer = Customer::create($validated);

        // Auto-create Stripe customer if Stripe is configured
        if (config('services.stripe.secret')) {
            try {
                $stripeCustomer = \Stripe\Customer::create([
                    'name' => $validated['company_name'],
                    'phone' => $validated['phone_number'] ?? null,
                    'metadata' => ['company_id' => $customer->company_id],
                ]);

                $customer->update(['stripe_customer_id' => $stripeCustomer->id]);
            } catch (\Exception) {
                // Don't fail — they can link Stripe later
            }
        }

        // Redirect to add service for this customer
        return redirect()->route('admin.services.create', ['company_id' => $customer->company_id])
            ->with('success', 'Customer created' . ($customer->stripe_customer_id ? ' and linked to Stripe.' : '.') . ' Now add their first service.');
    }

    public function show(Customer $customer): View
    {
        $customer->load(['services', 'invoices', 'tickets', 'users', 'assets', 'domains', 'articles', 'projects']);

        $totalIncome = $customer->invoices()
            ->where('invoice_status', 'Paid')
            ->sum('invoice_amount');

        $overdueInvoices = $customer->invoices()
            ->where('invoice_status', 'Unpaid')
            ->whereDate('due_date', '<', now())
            ->count();

        return view('admin.customers.show', compact('customer', 'totalIncome', 'overdueInvoices'));
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
