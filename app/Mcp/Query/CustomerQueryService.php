<?php

namespace App\Mcp\Query;

use App\Mcp\Dto\CustomerDto;
use App\Mcp\Support\Pagination;
use App\Models\Customer;

/**
 * Read query service backing the Customers MCP read tools.
 *
 * Every row is mapped through {@see CustomerDto} so the output is restricted to
 * an explicit field allow-list; model attributes are never spread and the
 * users() relation is never traversed into the response, so no user security
 * field can leak (Req 4.4). List results are paginated with the shared
 * page-size cap (Req 4.3).
 *
 * @see \App\Http\Controllers\Api\Admin\CustomerController for the matching
 *      search / ordering conventions.
 */
final class CustomerQueryService
{
    /**
     * Look up a single Customer by its primary key (`company_id`).
     *
     * @return array<string, mixed>|null The DTO array, or null when no customer matches.
     */
    public function getCustomer(int|string $companyId): ?array
    {
        $customer = Customer::query()
            ->whereKey($companyId)
            ->first();

        if ($customer === null) {
            return null;
        }

        return CustomerDto::fromModel($customer);
    }

    /**
     * Search Customers by name, returning a paginated, DTO-mapped result.
     *
     * The optional $name term matches `company_name`, `customer_name`, and
     * `phone_number`, mirroring the existing admin API search. Results are
     * ordered by `company_id` descending and the page size is capped via
     * {@see Pagination::effectivePerPage()}.
     *
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function searchCustomers(?string $name = null, ?int $page = null, ?int $perPage = null): array
    {
        $perPage = Pagination::effectivePerPage($perPage);

        $query = Customer::query();

        if ($name !== null && $name !== '') {
            $query->where(function ($q) use ($name) {
                $q->where('company_name', 'like', "%{$name}%")
                    ->orWhere('customer_name', 'like', "%{$name}%")
                    ->orWhere('phone_number', 'like', "%{$name}%");
            });
        }

        $paginator = $query->orderByDesc('company_id')
            ->paginate($perPage, ['*'], 'page', $page !== null && $page > 0 ? $page : 1);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Customer $customer) => CustomerDto::fromModel($customer))
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
