<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    /**
     * Paginated list of assets with optional search and customer filter.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Asset::with('customer');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('company_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($status = $request->input('status')) {
            $query->where('asset_status', $status);
        }

        $assets = $query->orderByDesc('device_id')->paginate($perPage);

        return response()->json([
            'data' => $assets->map(fn (Asset $a) => [
                'device_id' => $a->device_id,
                'device_name' => $a->device_name,
                'device_type' => $a->device_type,
                'location' => $a->location,
                'asset_status' => $a->asset_status,
                'serial_number' => $a->serial_number,
                'customer_name' => $a->customer?->company_name,
                'customer_id' => $a->customer_id,
            ]),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    /**
     * Create a new asset.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,company_id',
            'device_name' => 'required|string|max:255',
            'device_type' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'asset_status' => 'required|string|in:Active,Decommissioned,In Repair',
            'serial_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $asset = Asset::create($validated);
        $asset->load('customer');

        return response()->json(['data' => [
            'device_id' => $asset->device_id,
            'device_name' => $asset->device_name,
            'device_type' => $asset->device_type,
            'location' => $asset->location,
            'asset_status' => $asset->asset_status,
            'serial_number' => $asset->serial_number,
            'notes' => $asset->notes,
            'customer_name' => $asset->customer?->company_name,
            'customer_id' => $asset->customer_id,
        ]], 201);
    }

    /**
     * Show a single asset with related tickets.
     */
    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['customer', 'tickets' => fn ($q) => $q->orderByDesc('created_at')->limit(10)]);

        return response()->json(['data' => [
            'device_id' => $asset->device_id,
            'device_name' => $asset->device_name,
            'device_type' => $asset->device_type,
            'location' => $asset->location,
            'asset_status' => $asset->asset_status,
            'serial_number' => $asset->serial_number,
            'notes' => $asset->notes,
            'customer_id' => $asset->customer_id,
            'customer_name' => $asset->customer?->company_name,
            'created_at' => $asset->created_at,
            'updated_at' => $asset->updated_at,
            'tickets' => $asset->tickets->map(fn ($t) => [
                'ticket_id' => $t->ticket_id,
                'subject' => $t->subject,
                'status' => $t->status,
                'priority' => $t->priority,
                'created_at' => $t->created_at,
            ]),
        ]]);
    }

    /**
     * Update an asset.
     */
    public function update(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'sometimes|required|integer|exists:customers,company_id',
            'device_name' => 'sometimes|required|string|max:255',
            'device_type' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'asset_status' => 'sometimes|required|string|in:Active,Decommissioned,In Repair',
            'serial_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $asset->update($validated);
        $asset->load('customer');

        return response()->json(['data' => [
            'device_id' => $asset->device_id,
            'device_name' => $asset->device_name,
            'device_type' => $asset->device_type,
            'location' => $asset->location,
            'asset_status' => $asset->asset_status,
            'serial_number' => $asset->serial_number,
            'notes' => $asset->notes,
            'customer_name' => $asset->customer?->company_name,
            'customer_id' => $asset->customer_id,
        ]]);
    }

    /**
     * Delete an asset.
     */
    public function destroy(Asset $asset): JsonResponse
    {
        $asset->delete();

        return response()->json(null, 204);
    }
}
