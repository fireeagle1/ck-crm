<?php

namespace App\Mcp\Support;

/**
 * Shared pagination helper for MCP read tools.
 *
 * Centralises the page-size cap used across the read query services so that
 * every MCP list/read tool applies the same bound the existing admin
 * controllers use: `min($requested, 100)`, falling back to a positive default
 * when the requested value is absent, zero, or negative.
 *
 * @see \App\Http\Controllers\Api\Admin\CustomerController::index()
 */
final class Pagination
{
    /**
     * Default page size, matching the existing admin controllers.
     */
    public const DEFAULT_PER_PAGE = 15;

    /**
     * Hard upper bound on the page size, matching the existing admin controllers.
     */
    public const MAX_PER_PAGE = 100;

    /**
     * Compute the effective page size for a requested value.
     *
     * The result is always a positive integer no greater than
     * {@see self::MAX_PER_PAGE}. A requested value that is null, zero, or
     * negative falls back to the positive {@see $default}, which is itself
     * bounded to the valid range.
     *
     * @param  int|null  $requested  The caller-supplied page size (e.g. a `per_page` input).
     * @param  int  $default        The positive fallback used when $requested is not usable.
     */
    public static function effectivePerPage(?int $requested, int $default = self::DEFAULT_PER_PAGE): int
    {
        // Ensure the fallback itself is a sane, positive, capped value.
        $default = max(1, min($default, self::MAX_PER_PAGE));

        // A missing or non-positive request falls back to the default.
        if ($requested === null || $requested < 1) {
            return $default;
        }

        // Cap the requested value at the maximum allowed page size.
        return min($requested, self::MAX_PER_PAGE);
    }
}
