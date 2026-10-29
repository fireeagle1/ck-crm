<?php

namespace App\Mcp\Audit;

use App\Models\McpAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The single seam for writing MCP audit trail rows (task 11.1).
 *
 * Every MCP `tools/call` and every denied authorization attempt results in
 * EXACTLY ONE {@see McpAuditLog} row (Req 13.1, 13.4). The three public methods
 * map one-to-one onto the three terminal outcomes a call can have:
 *
 *  - {@see self::denied()}  — the scope gate ({@see \App\Http\Middleware\CheckMcpScope})
 *    rejected the call before any handler ran (missing scope / unregistered
 *    tool). No side effect occurred.
 *  - {@see self::success()} — a handler ran and returned a result.
 *  - {@see self::error()}   — a handler was reached but failed (not-wired or threw).
 *
 * Because denials happen in middleware BEFORE the controller, and success/error
 * are recorded in the controller AFTER dispatch, the three paths are mutually
 * exclusive for a single `tools/call`: there is never more than one row per call.
 *
 * Every row records only non-sensitive references (Req 13.3):
 *  - the Passport access token id — a reference, NEVER the token value;
 *  - the MCP identity (bot email / OAuth client name);
 *  - the tool name and the scope that was required/used;
 *  - the outcome and, for denials/errors, a short machine-readable reason;
 *  - an explicit invocation timestamp;
 *  - a `context` map restricted to non-sensitive metadata (e.g. `ticket_id`,
 *    `company_id` filter). The caller is responsible for passing only
 *    allow-listed keys; this logger never reads the request body.
 */
class McpAuditLogger
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_DENIED = 'denied';

    public const OUTCOME_ERROR = 'error';

    /**
     * Record a denied authorization attempt (Req 13.4).
     *
     * Called from the scope-checking middleware when a `tools/call` is rejected
     * before any handler runs. `$toolName` is null when the denial happens
     * before the tool could be resolved; `$scope` is null when no scope had
     * been determined yet.
     */
    public function denied(Request $request, ?string $toolName, ?string $scope, string $reason): void
    {
        $this->write($request, $toolName, $scope, self::OUTCOME_DENIED, $reason, null);
    }

    /**
     * Record a successful tool invocation (Req 13.1).
     *
     * `$context` must contain ONLY non-sensitive metadata (e.g. the target
     * `ticket_id` or a `company_id` filter) — never the request body,
     * credentials, or the token value (Req 13.3).
     *
     * @param  array<string, mixed>  $context
     */
    public function success(Request $request, string $toolName, ?string $scope, array $context = []): void
    {
        $this->write($request, $toolName, $scope, self::OUTCOME_SUCCESS, null, $context);
    }

    /**
     * Record a failed tool invocation (handler reached but failed) (Req 13.1).
     *
     * `$reason` is a short, non-sensitive description (e.g. `handler_not_wired`,
     * `handler_exception`) — never a stack trace or sensitive value.
     *
     * @param  array<string, mixed>  $context
     */
    public function error(Request $request, ?string $toolName, ?string $scope, string $reason, array $context = []): void
    {
        $this->write($request, $toolName, $scope, self::OUTCOME_ERROR, $reason, $context);
    }

    /**
     * Persist exactly one audit row.
     *
     * @param  array<string, mixed>|null  $context
     */
    private function write(
        Request $request,
        ?string $toolName,
        ?string $scope,
        string $outcome,
        ?string $reason,
        ?array $context,
    ): void {
        McpAuditLog::create([
            'access_token_id' => $this->resolveAccessTokenId($request),
            'mcp_identity' => $this->resolveIdentity($request),
            'tool_name' => $toolName,
            'scope_used' => $scope,
            'outcome' => $outcome,
            'reason' => $reason,
            // Normalise an empty metadata map to null so the column stays clean.
            'context' => ($context === null || $context === []) ? null : $context,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * The Passport access token id for the request, or null.
     *
     * This is a REFERENCE to the token (its primary key), never the token
     * value itself (Req 13.3). Returned as a string to match the column type.
     */
    private function resolveAccessTokenId(Request $request): ?string
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'token')) {
            $token = $user->token();
            if ($token !== null && method_exists($token, 'getKey')) {
                $key = $token->getKey();

                return $key !== null ? (string) $key : null;
            }
        }

        return null;
    }

    /**
     * A non-sensitive identity reference for the audit row.
     *
     * Prefers the authenticated user's email (the MCP bot identity), falling
     * back to the owning OAuth client name, then to 'unknown'.
     */
    private function resolveIdentity(Request $request): string
    {
        $user = $request->user();

        if ($user !== null && isset($user->email) && is_string($user->email)) {
            return $user->email;
        }

        if ($user !== null && method_exists($user, 'token')) {
            $token = $user->token();
            if ($token !== null && isset($token->client) && isset($token->client->name)) {
                return (string) $token->client->name;
            }
        }

        return 'unknown';
    }
}
