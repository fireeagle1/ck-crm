<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppPackage;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin JSON API for customer apps.
 *
 * Follows the {@see \App\Http\Controllers\Api\Admin\ServiceController} shape
 * ({ data, meta } with a `min($perPage, 100)` cap). This is the ADMIN surface,
 * so full technical detail (repo URL, hosting, codebase, packages, findings) is
 * intended here. Unlike ServiceController::show(), this controller builds an
 * EXPLICIT associative array rather than $model->toArray() — explicit field
 * lists are the house style going forward and avoid the leak pattern noted in
 * the MCP design.
 */
class CustomerAppController extends Controller
{
    /**
     * Paginated list of customer apps with the linked service summary.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = CustomerApp::query()
            ->with(['service', 'service.customer'])
            ->withCount(['packages', 'findings', 'openFindings']);

        // Filter by linked service.
        if ($serviceId = $request->input('service_id')) {
            $query->where('service_id', $serviceId);
        }

        $apps = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $apps->map(fn (CustomerApp $app) => [
                'id' => $app->id,
                'service_id' => $app->service_id,
                'repo_url' => $app->repo_url,
                'hosting_location' => $app->hosting_location,
                'service_name' => $app->service?->service_short,
                'service_status' => $app->service?->status,
                'customer_name' => $app->service?->customer?->company_name,
                'packages_count' => $app->packages_count,
                'findings_count' => $app->findings_count,
                'open_findings_count' => $app->open_findings_count,
                'created_at' => $app->created_at?->toISOString(),
                'updated_at' => $app->updated_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $apps->currentPage(),
                'last_page' => $apps->lastPage(),
                'per_page' => $apps->perPage(),
                'total' => $apps->total(),
            ],
        ]);
    }

    /**
     * Create a customer app against an existing service.
     *
     * Rejects when the service already has a linked app (Req 1.4); the DB
     * unique index on customer_apps.service_id is the backstop.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|integer|exists:services,service_id',
            'repo_url' => 'nullable|url|max:255',
            'hosting_location' => 'nullable|string|max:255',
            'codebase_info' => 'nullable|string',
        ]);

        $service = Service::findOrFail($validated['service_id']);

        if ($service->customerApp()->exists()) {
            return response()->json([
                'message' => 'This service already has a linked app.',
            ], 422);
        }

        $app = CustomerApp::create($validated);

        return response()->json([
            'data' => $this->present($app->fresh(['service', 'service.customer', 'packages', 'findings'])),
        ], 201);
    }

    /**
     * Show a single customer app with its full technical detail.
     *
     * Builds an EXPLICIT associative array of fields — never $model->toArray().
     * (Req 4.1, 4.4)
     */
    public function show(CustomerApp $customerApp): JsonResponse
    {
        $customerApp->load(['service', 'service.customer', 'packages', 'findings']);

        return response()->json([
            'data' => $this->present($customerApp),
        ]);
    }

    /**
     * Update the editable technical fields only (Req 4.2).
     */
    public function update(Request $request, CustomerApp $customerApp): JsonResponse
    {
        $validated = $request->validate([
            'repo_url' => 'nullable|url|max:255',
            'hosting_location' => 'nullable|string|max:255',
            'codebase_info' => 'nullable|string',
        ]);

        $customerApp->update($validated);

        return response()->json([
            'data' => $this->present($customerApp->fresh(['service', 'service.customer', 'packages', 'findings'])),
        ]);
    }

    /**
     * Delete a customer app (packages and findings cascade at the DB level).
     */
    public function destroy(CustomerApp $customerApp): JsonResponse
    {
        $customerApp->delete();

        return response()->json(null, 204);
    }

    /**
     * Build the explicit admin representation of a customer app.
     *
     * Every field is named explicitly: the app's own technical columns, a
     * summary of the linked Service (name/status/billing), and the packages
     * and findings arrays assembled field by field. No $model->toArray().
     *
     * @return array<string, mixed>
     */
    private function present(CustomerApp $app): array
    {
        $service = $app->service;

        return [
            'id' => $app->id,
            'service_id' => $app->service_id,
            'repo_url' => $app->repo_url,
            'hosting_location' => $app->hosting_location,
            'codebase_info' => $app->codebase_info,
            'created_at' => $app->created_at?->toISOString(),
            'updated_at' => $app->updated_at?->toISOString(),
            'service' => $service ? [
                'service_id' => $service->service_id,
                'name' => $service->service_short,
                'status' => $service->status,
                'billing' => $this->billing($service),
                'customer_name' => $service->customer?->company_name,
            ] : null,
            'packages' => $app->packages->map(fn (AppPackage $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'version' => $package->version,
                'synced_at' => $package->synced_at?->toISOString(),
            ])->all(),
            'findings' => $app->findings->map(fn (SecurityFinding $finding) => [
                'id' => $finding->id,
                'severity' => $finding->severity,
                'description' => $finding->description,
                'status' => $finding->status,
                'synced_at' => $finding->synced_at?->toISOString(),
            ])->all(),
        ];
    }

    /**
     * Derive the billing descriptor from the linked Service record (Req 4.3).
     *
     * Free when there is no Stripe subscription and no monthly charge;
     * otherwise the formatted charge with its payment frequency.
     *
     * @return array<string, mixed>
     */
    private function billing(Service $service): array
    {
        $hasCharge = $service->stripe_subscription_id !== null
            || ($service->service_monthly_charge !== null && (float) $service->service_monthly_charge > 0.0);

        if (! $hasCharge) {
            return [
                'is_free' => true,
                'label' => 'Free',
                'monthly_charge' => null,
                'payment_frequency' => null,
                'stripe_subscription_id' => null,
            ];
        }

        return [
            'is_free' => false,
            'label' => $service->service_monthly_charge !== null
                ? number_format((float) $service->service_monthly_charge, 2)
                : null,
            'monthly_charge' => $service->service_monthly_charge,
            'payment_frequency' => $service->service_payment_frequency,
            'stripe_subscription_id' => $service->stripe_subscription_id,
        ];
    }
}
