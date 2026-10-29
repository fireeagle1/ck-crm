<?php

namespace App\Mcp\Query;

use App\Mcp\Dto\TicketDetailDto;
use App\Mcp\Dto\TicketSummaryDto;
use App\Mcp\Support\Pagination;
use App\Models\Ticket;

/**
 * Read query service backing the Tickets MCP read tools (`tickets:read`).
 *
 * `searchTickets()` returns compact summaries mapped through
 * {@see TicketSummaryDto}; `getTicket()` returns the full detail view mapped
 * through {@see TicketDetailDto}, which includes the description, the
 * customer/service/asset references, BOTH internal and non-internal replies,
 * and the activity history.
 *
 * Filter and search conventions mirror the admin API
 * ({@see \App\Http\Controllers\Api\Admin\TicketController::index()}): an
 * `open` status expands to the open set, `search` matches the subject, the
 * ticket id, and the customer name. Detail relations are eager-loaded so the
 * DTO never triggers N+1 queries, and page sizes are bounded through
 * {@see Pagination::effectivePerPage()}. Full models are never serialised.
 *
 * @see \App\Mcp\Dto\TicketSummaryDto
 * @see \App\Mcp\Dto\TicketDetailDto
 * @see \App\Mcp\Support\Pagination::effectivePerPage()
 */
final class TicketQueryService
{
    /**
     * Fetch a single ticket by its primary key (`ticket_id`) as a detail view.
     *
     * Eager-loads every relation the {@see TicketDetailDto} reads
     * (customer, service, asset, and the reply/activity threads with their
     * authoring user) to avoid N+1 queries. Replies and activities are ordered
     * oldest-first, matching the admin detail view.
     *
     * @return array<string, mixed>|null The DTO array, or null when no ticket matches.
     */
    public function getTicket(int $ticketId): ?array
    {
        $ticket = Ticket::query()
            ->with([
                'customer',
                'service',
                'asset',
                'replies' => fn ($q) => $q->with('user')->orderBy('created_at', 'asc'),
                'activities' => fn ($q) => $q->with('user')->orderBy('created_at', 'asc'),
            ])
            ->whereKey($ticketId)
            ->first();

        return $ticket === null ? null : TicketDetailDto::fromModel($ticket);
    }

    /**
     * Search tickets, returning a paginated list of summaries.
     *
     * Optional filters mirror the admin API:
     *  - `status`: `open` expands to {Open, Pending, In Progress}; any other
     *    value filters on that exact status.
     *  - `priority`: exact match.
     *  - `company_id`: restricts to a single customer.
     *  - `search`: matches the subject, the ticket id, or the customer name
     *    (company_name / customer_name).
     *
     * Results are ordered newest-first and the page size is capped via
     * {@see Pagination::effectivePerPage()}.
     *
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function searchTickets(
        ?string $status = null,
        ?string $priority = null,
        ?int $companyId = null,
        ?string $search = null,
        ?int $page = null,
        ?int $perPage = null,
    ): array {
        $effectivePerPage = Pagination::effectivePerPage($perPage);

        // The summary DTO reads the related customer name, so eager-load it to
        // avoid N+1 queries while building the list.
        $query = Ticket::query()->with('customer');

        if ($status !== null && $status !== '') {
            if ($status === 'open') {
                $query->whereIn('status', ['Open', 'Pending', 'In Progress']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($priority !== null && $priority !== '') {
            $query->where('priority', $priority);
        }

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('ticket_id', $search)
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('company_name', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('ticket_id')
            ->paginate(
                $effectivePerPage,
                ['*'],
                'page',
                $page !== null && $page > 0 ? $page : 1,
            );

        return [
            'data' => $paginator->getCollection()
                ->map(static fn (Ticket $ticket): array => TicketSummaryDto::fromModel($ticket))
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
