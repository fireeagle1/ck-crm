<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Mcp\Audit\McpAuditLogger;
use App\Mcp\Registry\HandlerNotWiredException;
use App\Mcp\Registry\ToolDefinition;
use App\Mcp\Registry\ToolDispatcher;
use App\Mcp\Registry\ToolRegistry;
use App\Mcp\Support\SchemaValidator;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * MCP streamable-HTTP transport endpoint.
 *
 * Implements the JSON-RPC 2.0 methods OpenAI's remote MCP connector needs over
 * plain request/response HTTP (no stdio process, so it is compatible with
 * cPanel cron hosting — Req 14.3):
 *
 *  - `initialize`  — protocol handshake returning server capabilities (Req 3.1).
 *  - `tools/list`  — the registered tools, each as `{ name, description,
 *                    inputSchema }`, FILTERED to the scopes carried by the
 *                    presenting Passport access token (Req 3.2).
 *  - `tools/call`  — validate the arguments against the tool's JSON Schema,
 *                    dispatch through the {@see ToolDispatcher} seam, and return
 *                    the MCP result shape `{ content: [...] }` on success or a
 *                    structured JSON-RPC error on failure (Req 3.3).
 *
 * Per-tool scope enforcement for `tools/call` is performed by the
 * {@see \App\Http\Middleware\CheckMcpScope} middleware BEFORE this controller
 * runs, so by the time `tools/call` reaches here the token is known to carry
 * the tool's required scope. `tools/list` is still filtered here because the
 * middleware passes lifecycle methods through untouched.
 *
 * Concrete handler wiring is deliberately left to task 12.1: the dispatch seam
 * ({@see ToolDispatcher}) resolves each tool's stable handler key to a callable,
 * and a not-yet-wired key surfaces as a clean JSON-RPC error rather than a
 * stack trace.
 */
class McpController extends Controller
{
    /**
     * The MCP protocol revision this server speaks. Advertised in the
     * `initialize` handshake so the connector can negotiate compatibility.
     */
    private const PROTOCOL_VERSION = '2024-11-05';

    /**
     * JSON-RPC 2.0 reserved error codes used by the transport.
     */
    private const ERR_PARSE = -32700;

    private const ERR_INVALID_REQUEST = -32600;

    private const ERR_METHOD_NOT_FOUND = -32601;

    private const ERR_INVALID_PARAMS = -32602;

    private const ERR_INTERNAL = -32603;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ToolDispatcher $dispatcher,
        private readonly McpAuditLogger $auditLogger,
    ) {
    }

    /**
     * POST /mcp — JSON-RPC entrypoint (initialize, tools/list, tools/call).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        // A JSON-RPC request must be a single object with a string method.
        // (Batch requests are not used by the connector and are rejected.)
        if (! is_array($payload) || $payload === []) {
            return $this->error(null, self::ERR_PARSE, 'Invalid JSON-RPC payload.', 400);
        }

        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? null;
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        if (! is_string($method) || $method === '') {
            return $this->error($id, self::ERR_INVALID_REQUEST, 'Missing or invalid "method".', 400);
        }

        return match ($method) {
            'initialize' => $this->initialize($id),
            'tools/list' => $this->toolsList($request, $id),
            'tools/call' => $this->toolsCall($request, $id, $params),
            // Notifications the connector may send after initialize; ack them.
            'notifications/initialized', 'initialized' => $this->ack($id),
            'ping' => $this->result($id, (object) []),
            default => $this->error(
                $id,
                self::ERR_METHOD_NOT_FOUND,
                sprintf('Unknown method: "%s".', $method),
                404,
            ),
        };
    }

    /**
     * GET /mcp — streamable HTTP (SSE) channel.
     *
     * OpenAI's remote connector opens an SSE stream to receive server-pushed
     * JSON-RPC messages. Because the deployment is request/response only (no
     * long-lived process — Req 14.3), this channel emits an MCP `endpoint`
     * event pointing the connector back at the POST entrypoint for actual
     * request/response traffic, then completes. It never blocks on a persistent
     * loop, so it is safe under cron-based cPanel hosting.
     */
    public function handleStream(Request $request): StreamedResponse
    {
        $endpoint = $request->getBasePath().$request->getPathInfo();

        $response = new StreamedResponse(function () use ($endpoint): void {
            // MCP/SSE convention: advertise the POST endpoint the client should
            // use for JSON-RPC request/response, then flush and finish.
            echo 'event: endpoint'."\n";
            echo 'data: '.$endpoint."\n\n";

            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
            }
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        // Disable proxy buffering so chunks reach the connector promptly.
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * `initialize` — return the protocol version, server identity, and the
     * capabilities this server supports (it offers tools; no prompts/resources).
     */
    private function initialize(mixed $id): JsonResponse
    {
        return $this->result($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'serverInfo' => [
                'name' => config('app.name', 'CK CRM').' MCP',
                'version' => '1.0.0',
            ],
            'capabilities' => [
                // The connector may call tools/list; tools are not static, so
                // advertise listChanged = false (no push notifications).
                'tools' => [
                    'listChanged' => false,
                ],
            ],
        ]);
    }

    /**
     * `tools/list` — advertise the registered tools FILTERED to the scopes on
     * the presenting token. A tool is advertised iff the token carries its
     * required scope (Req 3.2). Each entry is `{ name, description, inputSchema }`
     * via {@see ToolDefinition::toArray()}.
     */
    private function toolsList(Request $request, mixed $id): JsonResponse
    {
        $tools = [];

        foreach ($this->registry->all() as $definition) {
            if ($this->tokenHasScope($request, $definition->requiredScope)) {
                $tools[] = $definition->toArray();
            }
        }

        return $this->result($id, ['tools' => $tools]);
    }

    /**
     * `tools/call` — validate arguments against the tool schema, dispatch via
     * the registry handler seam, and return the MCP result or a structured
     * error. Per-tool scope has already been enforced by CheckMcpScope.
     *
     * @param  array<string, mixed>  $params
     */
    private function toolsCall(Request $request, mixed $id, array $params): JsonResponse
    {
        $name = $params['name'] ?? null;

        if (! is_string($name) || $name === '') {
            return $this->error($id, self::ERR_INVALID_PARAMS, 'Missing tool name in params.name.', 400);
        }

        $definition = $this->registry->get($name);

        // Default-deny: unregistered tools are not callable. (CheckMcpScope
        // also guards this; the transport repeats it so a direct dispatch can
        // never reach an unknown tool.)
        if ($definition === null) {
            return $this->error($id, self::ERR_METHOD_NOT_FOUND, sprintf('Unknown tool: "%s".', $name), 404);
        }

        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        // Validate the arguments against the tool's JSON Schema (required
        // fields, types, enums, numeric bounds).
        $validator = SchemaValidator::make($arguments, $definition->inputSchema);
        if ($validator->fails()) {
            // A reached-but-rejected call: record a single `error` audit row
            // (the scope gate already passed in CheckMcpScope, so this is not a
            // denial). No handler ran.
            $this->auditLogger->error(
                $request,
                $definition->name,
                $definition->requiredScope,
                'invalid_arguments',
            );

            return $this->validationError($id, $definition, $validator);
        }

        $validated = $validator->validated();

        // Non-sensitive metadata only (e.g. ticket_id / company_id) — never the
        // full argument payload, credentials, or the token value (Req 13.3).
        $context = $this->auditContext($validated);

        try {
            $payload = $this->dispatcher->dispatch($definition, $validated);
        } catch (HandlerNotWiredException $e) {
            // The tool is registered and authorized but its concrete handler is
            // wired in task 12.1. Surface a clean JSON-RPC error.
            $this->auditLogger->error(
                $request,
                $definition->name,
                $definition->requiredScope,
                'handler_not_wired',
                $context,
            );

            return $this->error(
                $id,
                self::ERR_INTERNAL,
                sprintf('Tool "%s" is not available yet.', $definition->name),
                501,
            );
        } catch (ValidationException $e) {
            // The write tools (create/update/reply) re-validate business rules
            // in the shared TicketService — which exists (`company_id`), enum
            // membership, etc. — AFTER the transport's schema gate. A failure
            // throws before any row is written, so no ticket/reply/activity is
            // created (Req 9.2 / 10.5 / 11.1). Render the same structured
            // field-naming error shape as the schema gate.
            $this->auditLogger->error(
                $request,
                $definition->name,
                $definition->requiredScope,
                'invalid_arguments',
                $context,
            );

            return $this->error(
                $id,
                self::ERR_INVALID_PARAMS,
                sprintf('Invalid arguments for "%s".', $definition->name),
                422,
                ['fields' => $e->errors()],
            );
        } catch (Throwable $e) {
            // Never leak a stack trace to the connector.
            report($e);

            $this->auditLogger->error(
                $request,
                $definition->name,
                $definition->requiredScope,
                'handler_exception',
                $context,
            );

            return $this->error(
                $id,
                self::ERR_INTERNAL,
                sprintf('The "%s" tool failed to execute.', $definition->name),
                500,
            );
        }

        // Exactly one success row per successful tools/call (Req 13.1).
        $this->auditLogger->success($request, $definition->name, $definition->requiredScope, $context);

        return $this->result($id, $this->toolResult($payload));
    }

    /**
     * Extract a small, non-sensitive context map from the validated arguments
     * for the audit row.
     *
     * Only a fixed allow-list of harmless reference/filter keys is copied
     * (e.g. `ticket_id`, `company_id`, `invoice_id`, status/priority filters).
     * Reply/description bodies, credentials, and any other payload content are
     * deliberately never stored (Req 13.3).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function auditContext(array $arguments): array
    {
        $allowed = [
            'company_id',
            'invoice_id',
            'service_id',
            'project_id',
            'ticket_id',
            'id',
            'status',
            'invoice_status',
            'priority',
            'ticket_type',
            'is_internal',
            'notify_customer',
            'page',
            'per_page',
        ];

        $context = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $arguments)) {
                $context[$key] = $arguments[$key];
            }
        }

        return $context;
    }

    /**
     * Wrap a handler's return value in the MCP tool-result envelope.
     *
     * MCP tool results are a `content` array of typed parts. Structured data is
     * returned as a JSON text part (and mirrored under `structuredContent` for
     * connectors that consume it directly).
     *
     * @return array<string, mixed>
     */
    private function toolResult(mixed $payload): array
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $json === false ? '{}' : $json,
                ],
            ],
            'structuredContent' => $payload,
            'isError' => false,
        ];
    }

    /**
     * Whether the presenting access token carries the given scope. Consults
     * ONLY the OAuth scope on the Passport token, never `is_admin` (Req 2.1).
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

    /**
     * Build a structured JSON-RPC error from a failed schema validation,
     * naming the offending fields in the error data (Req 9.2 / 10.5 / 11.1
     * shape parity — the write tools reuse this envelope).
     */
    private function validationError(mixed $id, ToolDefinition $definition, ValidatorContract $validator): JsonResponse
    {
        return $this->error(
            $id,
            self::ERR_INVALID_PARAMS,
            sprintf('Invalid arguments for "%s".', $definition->name),
            422,
            ['fields' => $validator->errors()->toArray()],
        );
    }

    /**
     * A JSON-RPC success response: `{ jsonrpc, id, result }`.
     */
    private function result(mixed $id, mixed $result): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    /**
     * Acknowledge a JSON-RPC notification (no result body expected).
     */
    private function ack(mixed $id): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => (object) [],
        ]);
    }

    /**
     * A JSON-RPC error response: `{ jsonrpc, id, error: { code, message, data? } }`.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function error(mixed $id, int $code, string $message, int $httpStatus, ?array $data = null): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ], $httpStatus);
    }
}
