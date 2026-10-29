<?php

namespace App\Mcp\Query;

use App\Mcp\Dto\ProjectDto;
use App\Mcp\Support\Pagination;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Read query service backing the Projects MCP read tools (`projects:read`).
 *
 * Projects have no existing admin API, so this is a new read surface. Every row
 * is mapped through {@see ProjectDto}, which exposes the computed
 * `progress_percentage` via the model accessor rather than any raw relation
 * data; models are never serialised with `->toArray()`.
 *
 * The optional `status` filter is constrained to {@see Project::STATUSES}: an
 * out-of-set value is rejected rather than silently returning no rows, so the
 * caller gets an explicit, actionable error. Page sizes are bounded through
 * {@see Pagination::effectivePerPage()} so list results never exceed the shared
 * cap the existing MCP read services use.
 *
 * @see \App\Mcp\Dto\ProjectDto
 * @see \App\Mcp\Support\Pagination::effectivePerPage()
 */
final class ProjectsQueryService
{
    /**
     * Look up a single Project by its primary key (`id`).
     *
     * Returns the DTO array for the matching project, or null when no project
     * with the given id exists.
     *
     * @return array<string, mixed>|null
     */
    public function getProject(int|string $id): ?array
    {
        $project = Project::query()->find($id);

        if ($project === null) {
            return null;
        }

        return ProjectDto::fromModel($project);
    }

    /**
     * List projects, optionally filtered by `company_id` and/or `status`, with
     * a capped page size.
     *
     * The returned array mirrors the shape used across the MCP read tools:
     * a `data` list of {@see ProjectDto} arrays and a `meta` block describing
     * the pagination state.
     *
     * @param  int|string|null  $companyId  Restrict to a single customer.
     * @param  string|null  $status         Restrict to a single project status; must be one of {@see Project::STATUSES}.
     * @param  int|null  $page              1-based page number (defaults to the first page).
     * @param  int|null  $perPage           Requested page size; capped via {@see Pagination}.
     * @return array{data: array<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     *
     * @throws InvalidArgumentException When $status is supplied but not a valid project status.
     */
    public function listProjects(
        int|string|null $companyId = null,
        ?string $status = null,
        ?int $page = null,
        ?int $perPage = null,
    ): array {
        if ($status !== null && ! in_array($status, Project::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid project status "%s". Expected one of: %s.',
                $status,
                implode(', ', Project::STATUSES),
            ));
        }

        $effectivePerPage = Pagination::effectivePerPage($perPage);

        $query = Project::query();

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($status !== null) {
            $query->where('status', $status);
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
     * Map a paginator of Project models into the MCP `data`/`meta` envelope.
     *
     * @param  LengthAwarePaginator<int, Project>  $paginator
     * @return array{data: array<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    private function present(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(static fn (Project $project): array => ProjectDto::fromModel($project))
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
