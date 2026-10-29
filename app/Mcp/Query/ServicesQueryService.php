<?php

namespace App\Mcp\Query;

use App\Mcp\Dto\ServiceDto;
use App\Mcp\Support\Pagination;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read query service backing the Services MCP read tools (`services:read`).
 *
 * Every row is mapped through {@see ServiceDto}, which is the data-minimisation
 * enforcement point: `cpanel_username` and `stripe_subscription_id` are
 * structurally absent from all output. Models are never serialised with
 * `->toArray()`, so service credentials cannot leak even if the model changes.
 *
 * Page sizes are bounded through {@see Pagination::effectivePerPage()} so list
 * results never exceed the shared cap the admin controllers use.
 *
 * @see \App\Http\Controllers\Admin\ServiceController
 */
final class ServicesQueryService
{
    /**
     * Look up a single Service by its primary key (`service_id`).
     *
     * Returns the DTO array for the matching service, or null when no service
     * with the given id exists.
     */
    public function getService(int|string $serviceId): ?array
    {
        $service = Service::query()->find($serviceId);

        if ($service === null) {
            return null;
        }

        return ServiceDto::fromModel($service);
    }

    /**
     * List services, optionally filtered by `company_id` and/or `status`, with
     * a capped page size.
     *
     * The returned array mirrors the shape used across the MCP read tools:
     * a `data` list of {@see ServiceDto} arrays and a `meta` block describing
     * the pagination state.
     *
     * @param  int|string|null  $companyId  Restrict to a single customer.
     * @param  string|null  $status         Restrict to a single service status.
     * @param  int|null  $page              1-based page number (defaults to the first page).
     * @param  int|null  $perPage           Requested page size; capped via {@see Pagination}.
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function listServices(
        int|string|null $companyId = null,
        ?string $status = null,
        ?int $page = null,
        ?int $perPage = null,
    ): array {
        $effectivePerPage = Pagination::effectivePerPage($perPage);

        $query = Service::query();

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $paginator = $query
            ->orderByDesc('service_id')
            ->paginate(
                perPage: $effectivePerPage,
                page: $page !== null && $page > 0 ? $page : null,
            );

        return $this->present($paginator);
    }

    /**
     * Map a paginator of Service models into the MCP `data`/`meta` envelope.
     *
     * @param  LengthAwarePaginator<int, Service>  $paginator
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function present(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(static fn (Service $service): array => ServiceDto::fromModel($service))
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
