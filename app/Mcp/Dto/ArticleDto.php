<?php

namespace App\Mcp\Dto;

use App\Models\Article;

/**
 * Data-minimisation transformer for the Knowledgebase (Article) domain.
 *
 * Returns a fixed associative array built from an explicit field allow-list.
 * Model attributes are never spread and ->toArray() is never called, so any
 * field added to the knowledgebase table in future is structurally absent from
 * the MCP surface until it is deliberately added here. The customer() relation
 * is never traversed, so no customer field can leak through this surface.
 */
final class ArticleDto
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Article $article): array
    {
        return [
            'article_id' => $article->article_id,
            'company_id' => $article->company_id,
            'title' => $article->title,
            'content' => $article->content,
            'category' => $article->category,
            'is_public' => (bool) $article->is_public,
            'created_at' => optional($article->created_at)->toIso8601String(),
            'updated_at' => optional($article->updated_at)->toIso8601String(),
        ];
    }

    /**
     * A compact summary for list/search results: everything except the full
     * article body, which can be large. Callers fetch the body with get_article.
     *
     * @return array<string, mixed>
     */
    public static function summaryFromModel(Article $article): array
    {
        return [
            'article_id' => $article->article_id,
            'company_id' => $article->company_id,
            'title' => $article->title,
            'category' => $article->category,
            'is_public' => (bool) $article->is_public,
            'created_at' => optional($article->created_at)->toIso8601String(),
            'updated_at' => optional($article->updated_at)->toIso8601String(),
        ];
    }
}
