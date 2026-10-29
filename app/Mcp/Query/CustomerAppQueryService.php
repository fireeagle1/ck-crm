<?php

namespace App\Mcp\Query;

use App\Mcp\Dto\CustomerAppDto;
use App\Mcp\Support\Pagination;
use App\Models\CustomerApp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read query service backing the Web App (CustomerApp) MCP read tools
 * (`apps:read`).
 *
 * Every row is mapped through {@see CustomerAppDto}, the data-minimisation
 * enforcement point. Models are never serialised with `->toArray()`, so only
 * the whitelisted technical-metadata fields are ever returned.
 *
 * Page sizes are bounded through {@see Pagination::effectivePerPage()} so list
 * results never exceed the shared cap the admin controllers use.
 *
 * @see \App\Http\Controllers\Admin\CustomerAppController
 */
final class CustomerAppQueryService
{
    /**
     * Look up a single Web App by its primary key (`id`).
     *
     * Returns the DTO array for the matching app, or null when no app with the
     * given id exists.
     */
    public function getCustomerApp(int|string $id): ?array
    {
        $app = CustomerApp::query()->with('service')->find($id);

        if ($app === null) {
            return null;
        }

        return CustomerAppDto::fromModel($app);
    }

    /**
     * List Web Apps, optionally filtered by `service_id` and/or `company_id`
     * (the owning customer), with a capped page size.
     *
     * The returned array mirrors the shape used across the MCP read tools:
     * a `data` list of {@see CustomerAppDto} arrays and a `meta` block
     * describing the pagination state.
     *
     * @param  int|string|null  $serviceId  Restrict to the app on a single service.
     * @param  int|string|null  $companyId  Restrict to the apps of a single customer.
     * @param  int|null  $page              1-based page number (defaults to the first page).
     * @param  int|null  $perPage           Requested page size; capped via {@see Pagination}.
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function listCustomerApps(
        int|string|null $serviceId = null,
        int|string|null $companyId = null,
        ?int $page = null,
        ?int $perPage = null,
    ): array {
        $effectivePerPage = Pagination::effectivePerPage($perPage);

        $query = CustomerApp::query()->with('service');

        if ($serviceId !== null) {
            $query->where('service_id', $serviceId);
        }

        if ($companyId !== null) {
            $query->whereHas('service', fn ($service) => $service->where('company_id', $companyId));
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate(
                perPage: $effectivePerPage,
                page: $page !== null && $page > 0 ? $page : null,
            );

        return $this->present($paginator);
    }

    /**
     * Map a paginator of CustomerApp models into the MCP `data`/`meta` envelope.
     *
     * @param  LengthAwarePaginator<int, CustomerApp>  $paginator
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function present(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(static fn (CustomerApp $app): array => CustomerAppDto::fromModel($app))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
