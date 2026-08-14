<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Paginated list of customers with optional search.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Customer::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('company_id')->paginate($perPage);

        return response()->json([
            'data' => $customers->map(fn (Customer $c) => [
                'company_id' => $c->company_id,
                'company_name' => $c->company_name,
                'customer_name' => $c->customer_name,
                'phone_number' => $c->phone_number,
            ]),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Create a new customer.
     */
    public function store(Request $request): JsonResponse
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

        return response()->json(['data' => $customer], 201);
    }

    /**
     * Show a single customer with related counts.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->loadCount(['services', 'tickets', 'invoices', 'domains']);

        return response()->json(['data' => $customer]);
    }

    /**
     * Update an existing customer.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'sometimes|required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
        ]);

        $customer->update($validated);

        return response()->json(['data' => $customer]);
    }

    /**
     * Delete a customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(null, 204);
    }
}
