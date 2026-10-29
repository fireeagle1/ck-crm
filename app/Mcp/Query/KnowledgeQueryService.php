<?php

namespace App\Mcp\Query;

use App\Mcp\Dto\ArticleDto;
use App\Mcp\Support\Pagination;
use App\Models\Article;

/**
 * Read query service backing the Knowledgebase MCP read tools
 * (`knowledge:read`).
 *
 * Every row is mapped through {@see ArticleDto} so the output is restricted to
 * an explicit field allow-list; model attributes are never spread and the
 * customer() relation is never traversed into the response. List results are
 * paginated with the shared page-size cap and return compact summaries (no
 * article body) so a search response stays small; the full body is fetched via
 * getArticle().
 *
 * @see \App\Http\Controllers\Portal\KnowledgebaseController for the matching
 *      search / ordering conventions.
 */
final class KnowledgeQueryService
{
    /**
     * Fetch a single knowledgebase article by its primary key (`article_id`),
     * including the full body.
     *
     * @return array<string, mixed>|null The DTO array, or null when no article matches.
     */
    public function getArticle(int|string $articleId): ?array
    {
        $article = Article::query()
            ->whereKey($articleId)
            ->first();

        if ($article === null) {
            return null;
        }

        return ArticleDto::fromModel($article);
    }

    /**
     * Search knowledgebase articles, returning a paginated list of compact
     * summaries (no article body).
     *
     * The optional $search term matches `title` and `content`; the optional
     * $category and $companyId narrow the result set. Results are ordered by
     * most-recently-created first and the page size is capped via
     * {@see Pagination::effectivePerPage()}.
     *
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function searchArticles(
        ?string $search = null,
        ?string $category = null,
        ?int $companyId = null,
        ?int $page = null,
        ?int $perPage = null,
    ): array {
        $perPage = Pagination::effectivePerPage($perPage);

        $query = Article::query();

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('article_id')
            ->paginate($perPage, ['*'], 'page', $page !== null && $page > 0 ? $page : 1);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Article $article) => ArticleDto::summaryFromModel($article))
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
