<?php

namespace App\Support;

use App\Models\CustomerApp;

/**
 * Derives the customer-safe security summary string for an app from the
 * open-finding count alone.
 *
 * The count is taken solely from Security_Finding status values via the
 * CustomerApp::openFindings() relationship. Severity labels, descriptions,
 * and package records are never read in this path, so the resulting string
 * can contain no technical or vulnerability detail (Req 6.3, 5.2).
 */
final class SecuritySummaryService
{
    /**
     * Produce the high-level security summary for the given app.
     *
     * - No app, or no open findings -> "Security checks passing" (Req 6.1).
     * - One or more open findings    -> the count of issues being
     *   addressed, naming no finding (Req 6.2).
     */
    public static function for(?CustomerApp $app): string
    {
        $openCount = $app ? $app->openFindings()->count() : 0;

        if ($openCount === 0) {
            return 'Security checks passing';
        }

        return $openCount === 1
            ? '1 issue being addressed'
            : "{$openCount} issues being addressed";
    }
}
