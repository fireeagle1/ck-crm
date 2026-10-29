<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidFindingsPayloadException;
use App\Exceptions\UnknownCustomerAppException;
use App\Http\Controllers\Controller;
use App\Mcp\Audit\McpAuditLogger;
use App\Services\FindingsIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Findings receiving endpoint stub — the HTTP half of the findings stub
 * (Req 3.1–3.5, 3.7). Accepts a payload of packages and security findings for
 * one identified app and bulk-replaces that app's stored records via
 * {@see FindingsIngestService}.
 *
 * This controller contains NO GitHub, ChatGPT, or OpenAI logic (Req 3.6): the
 * future daily sync runs its scan elsewhere and POSTs its results here.
 *
 * ## Why the scope is enforced here, not by `mcp.scope`
 *
 * The route lives in the MCP route group so it inherits `EnsureHttps`,
 * `throttle:mcp`, and `auth:api` (Passport). It also inherits
 * {@see \App\Http\Middleware\CheckMcpScope} (`mcp.scope`), but that middleware
 * only gates JSON-RPC `tools/call` bodies against the ToolRegistry allow-list —
 * it passes through any request that is not a `tools/call`. A plain REST POST
 * such as this one would therefore reach the controller with ONLY `auth:api`
 * applied (any valid token, any scope).
 *
 * Requirement 3.5 requires the endpoint to reject requests lacking the
 * `apps:write` scope, so the scope gate is performed explicitly here using the
 * same Passport token technique {@see CheckMcpScope::tokenHasScope()} uses. A
 * denial writes a single `denied` audit row and returns 403; nothing is
 * persisted (Req 3.5, 3.7).
 */
class FindingsController extends Controller
{
    /**
     * The audit `tool_name` recorded for every outcome of this endpoint.
     */
    private const TOOL_NAME = 'ingest_app_findings';

    /**
     * The OAuth scope a token must carry to reach this write surface (Req 3.5).
     */
    private const REQUIRED_SCOPE = 'apps:write';

    public function __construct(
        private readonly FindingsIngestService $ingestService,
        private readonly McpAuditLogger $auditLogger,
    ) {
    }

    /**
     * POST /mcp/apps/{identifier}/findings
     *
     * Enforces the `apps:write` scope, validates the payload shape, delegates
     * to the ingest service, and audits exactly one outcome (denied/error/
     * success). Returns a counts-only summary on success (Req 3.1, 3.2) and
     * never echoes technical detail back.
     */
    public function store(Request $request, string $identifier): JsonResponse
    {
        // Explicit scope gate (Req 3.5). `mcp.scope` does not enforce for a
        // plain REST body, so the check is performed here before any work.
        if (! $this->tokenHasScope($request, self::REQUIRED_SCOPE)) {
            $this->auditLogger->denied($request, self::TOOL_NAME, self::REQUIRED_SCOPE, 'missing_scope');

            return response()->json([
                'error' => sprintf(
                    'Not authorized: the findings endpoint requires the "%s" scope.',
                    self::REQUIRED_SCOPE,
                ),
            ], 403);
        }

        // Shape validation: the payload must carry arrays of packages and
        // findings. Per-item field validation lives in the ingest service's
        // allow-list and surfaces as InvalidFindingsPayloadException below.
        $payload = $request->validate([
            'packages' => ['sometimes', 'array'],
            'findings' => ['sometimes', 'array'],
        ]);

        $packages = array_values((array) ($payload['packages'] ?? []));
        $findings = array_values((array) ($payload['findings'] ?? []));

        try {
            $counts = $this->ingestService->ingest($identifier, $packages, $findings);
        } catch (UnknownCustomerAppException $e) {
            // Unknown app: error naming the identifier; nothing persisted (Req 3.3).
            $this->auditLogger->error(
                $request,
                self::TOOL_NAME,
                self::REQUIRED_SCOPE,
                'unknown_app',
                ['app' => $identifier],
            );

            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        } catch (InvalidFindingsPayloadException $e) {
            // Validation failure: 422 listing offending fields; nothing persisted (Req 3.4).
            $this->auditLogger->error(
                $request,
                self::TOOL_NAME,
                self::REQUIRED_SCOPE,
                'invalid_payload',
                ['app' => $identifier],
            );

            return response()->json([
                'error' => 'The findings payload failed validation.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Success: counts-only summary; non-sensitive context only (Req 3.2, 3.7).
        $this->auditLogger->success(
            $request,
            self::TOOL_NAME,
            self::REQUIRED_SCOPE,
            [
                'app' => $identifier,
                'packages' => $counts['packages'],
                'findings' => $counts['findings'],
            ],
        );

        return response()->json([
            'status' => 'ok',
            'app' => $identifier,
            'packages' => $counts['packages'],
            'findings' => $counts['findings'],
        ], 200);
    }

    /**
     * Whether the presenting Passport access token carries the given scope.
     *
     * Mirrors {@see \App\Http\Middleware\CheckMcpScope::tokenHasScope()}:
     * authorization derives SOLELY from the OAuth scope on the token, never
     * from `is_admin` or any user role.
     */
    private function tokenHasScope(Request $request, string $scope): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'token')) {
            $token = $user->token();

            if ($token !== null && method_exists($token, 'can')) {
                return (bool) $token->can($scope);
            }
        }

        if (method_exists($user, 'tokenCan')) {
            return (bool) $user->tokenCan($scope);
        }

        return false;
    }
}
