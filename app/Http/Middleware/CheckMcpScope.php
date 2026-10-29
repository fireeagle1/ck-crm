<?php

namespace App\Http\Middleware;

use App\Mcp\Audit\McpAuditLogger;
use App\Mcp\Registry\ToolRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-tool scope-checking middleware for the MCP route group.
 *
 * This is the single authorization gate for MCP `tools/call` invocations
 * (Req 1.4, 2.1, 2.3, 2.4). It runs after `auth:api` (so a valid, non-revoked
 * Passport access token is already attached to the request) and before any
 * tool handler executes, guaranteeing that:
 *
 *  - an unregistered tool is denied by default — the {@see ToolRegistry} is an
 *    allow-list, so no un-listed CRM operation is reachable (Req 2.3, 2.4);
 *  - a registered tool is permitted iff the presented token carries that
 *    tool's required scope (Req 1.4, 2.1);
 *  - authorization derives SOLELY from the OAuth scope on the token — it never
 *    consults `is_admin` or any user role (Req 2.1);
 *  - on any denial, NO downstream handler runs (no read or write side effect)
 *    and a `denied` {@see \App\Models\McpAuditLog} entry is written via the
 *    {@see McpAuditLogger} (Req 13.4).
 *
 * Only `tools/call` requests are gated per-tool. The JSON-RPC lifecycle methods
 * `initialize` and `tools/list` are not tool invocations and carry no required
 * scope, so they pass through untouched (scope-filtered advertisement for
 * `tools/list` is handled in the transport layer, not here).
 *
 * Denial audit rows are written exclusively through the dedicated
 * {@see McpAuditLogger} (task 11.1): the middleware never touches the audit
 * model directly. Because denials occur here — before the controller — and the
 * success/error rows are written in the controller after dispatch, every
 * `tools/call` produces exactly one audit row.
 */
class CheckMcpScope
{
    /**
     * The JSON-RPC method that invokes a tool and is therefore scope-gated.
     */
    private const METHOD_TOOLS_CALL = 'tools/call';

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly McpAuditLogger $auditLogger,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Only tool invocations are gated. Lifecycle methods such as
        // `initialize` and `tools/list` are not per-tool calls and pass through.
        if ((string) $request->input('method') !== self::METHOD_TOOLS_CALL) {
            return $next($request);
        }

        $toolName = $this->resolveToolName($request);

        // A `tools/call` with no resolvable tool name cannot be authorized
        // against the allow-list — deny by default.
        if ($toolName === null || $toolName === '') {
            $this->auditLogger->denied($request, null, null, 'unregistered_tool');

            return $this->toolNotFound($request, $toolName ?? '');
        }

        // Default-deny: any tool that is not in the registry allow-list is
        // rejected before any handler can run (Req 2.3, 2.4).
        if (! $this->registry->has($toolName)) {
            $this->auditLogger->denied($request, $toolName, null, 'unregistered_tool');

            return $this->toolNotFound($request, $toolName);
        }

        $definition = $this->registry->get($toolName);
        $requiredScope = $definition->requiredScope;

        // Authorization is based SOLELY on the OAuth scope carried by the
        // access token — never on `is_admin` or any user role (Req 2.1).
        if (! $this->tokenHasScope($request, $requiredScope)) {
            $this->auditLogger->denied($request, $toolName, $requiredScope, 'missing_scope');

            return $this->unauthorized($request, $toolName, $requiredScope);
        }

        return $next($request);
    }

    /**
     * Resolve the invoked tool name from a JSON-RPC `tools/call` request.
     *
     * The MCP transport carries the tool name in `params.name`. Reads it from
     * the request input so the check is independent of the transport parsing.
     */
    private function resolveToolName(Request $request): ?string
    {
        $name = $request->input('params.name');

        if (is_string($name)) {
            return $name;
        }

        return null;
    }

    /**
     * Whether the presenting access token carries the given scope.
     *
     * Uses ONLY the OAuth scope on the Passport access token. The `is_admin`
     * boolean and any user role are deliberately never consulted (Req 2.1).
     */
    private function tokenHasScope(Request $request, string $scope): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        // Prefer the Passport access token attached by the `auth:api` guard.
        if (method_exists($user, 'token')) {
            $token = $user->token();

            if ($token !== null && method_exists($token, 'can')) {
                return (bool) $token->can($scope);
            }
        }

        // Fall back to the HasApiTokens convenience method when available.
        if (method_exists($user, 'tokenCan')) {
            return (bool) $user->tokenCan($scope);
        }

        return false;
    }

    /**
     * Build the MCP method/tool-not-found error for an unregistered tool.
     *
     * Uses the JSON-RPC "Method not found" code (-32601) and echoes back the
     * JSON-RPC request id when present so the connector can correlate it.
     */
    private function toolNotFound(Request $request, string $toolName): JsonResponse
    {
        return $this->jsonRpcError(
            $request,
            code: -32601,
            message: $toolName === ''
                ? 'Unknown tool: the requested tool is not available.'
                : sprintf('Unknown tool: "%s" is not available.', $toolName),
            httpStatus: 404,
        );
    }

    /**
     * Build the MCP authorization error for a missing scope.
     *
     * Uses a JSON-RPC error with HTTP 403 so the call is rejected without any
     * handler running (Req 1.4, 2.4).
     */
    private function unauthorized(Request $request, string $toolName, string $scope): JsonResponse
    {
        return $this->jsonRpcError(
            $request,
            code: -32001,
            message: sprintf('Not authorized: the "%s" tool requires the "%s" scope.', $toolName, $scope),
            httpStatus: 403,
        );
    }

    /**
     * Render a structured JSON-RPC error response (never a raw stack trace).
     */
    private function jsonRpcError(Request $request, int $code, string $message, int $httpStatus): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $request->input('id'),
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $httpStatus);
    }
}
