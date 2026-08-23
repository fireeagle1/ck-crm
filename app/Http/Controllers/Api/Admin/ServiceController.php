<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Paginated list of services with optional status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Service::with('customer');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by customer
        if ($customerId = $request->input('customer_id')) {
            $query->where('company_id', $customerId);
        }

        $services = $query->orderByDesc('service_id')->paginate($perPage);

        return response()->json([
            'data' => $services->map(fn (Service $s) => [
                'service_id' => $s->service_id,
                'service_short' => $s->service_short,
                'service_type' => $s->service_type,
                'domain_name' => $s->domain_name,
                'status' => $s->status,
                'service_monthly_charge' => $s->service_monthly_charge,
                'customer_name' => $s->customer?->company_name,
            ]),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]);
    }

    /**
     * Create a new service.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:customers,company_id',
            'service_short' => 'required|string|max:255',
            'service_type' => 'nullable|string|max:255',
            'domain_name' => 'nullable|string|max:255',
            'status' => 'required|string|max:50',
            'service_monthly_charge' => 'nullable|numeric|min:0',
            'service_payment_frequency' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'next_payment_date' => 'nullable|date',
            'stripe_subscription_id' => 'nullable|string|max:255',
        ]);

        $service = Service::create($validated);

        return response()->json(['data' => $service], 201);
    }

    /**
     * Show a single service with associated customer name.
     */
    public function show(Service $service): JsonResponse
    {
        $service->load('customer');

        return response()->json([
            'data' => array_merge($service->toArray(), [
                'customer_name' => $service->customer?->company_name,
            ]),
        ]);
    }

    /**
     * Update an existing service.
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'sometimes|required|integer|exists:customers,company_id',
            'service_short' => 'sometimes|required|string|max:255',
            'service_type' => 'nullable|string|max:255',
            'domain_name' => 'nullable|string|max:255',
            'status' => 'sometimes|required|string|max:50',
            'service_monthly_charge' => 'nullable|numeric|min:0',
            'service_payment_frequency' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'next_payment_date' => 'nullable|date',
            'stripe_subscription_id' => 'nullable|string|max:255',
        ]);

        $service->update($validated);

        return response()->json(['data' => $service]);
    }

    /**
     * Delete a service.
     */
    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(null, 204);
    }
}
