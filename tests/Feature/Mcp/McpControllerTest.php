<?php

namespace Tests\Feature\Mcp;

use App\Http\Controllers\Mcp\McpController;
use App\Mcp\Audit\McpAuditLogger;
use App\Mcp\Registry\ToolDispatcher;
use App\Mcp\Registry\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Behavioural tests for the MCP JSON-RPC transport (task 8.2).
 *
 * The controller is driven directly through handle() with a stub Passport
 * user/token so the tests exercise the transport's method routing, the
 * initialize handshake, and the scope-filtered tools/list advertisement
 * without standing up the full OAuth/HTTP stack. This mirrors the approach in
 * {@see CheckMcpScopeTest}.
 */
class McpControllerTest extends TestCase
{
    use RefreshDatabase;

    private function controller(): McpController
    {
        return new McpController(new ToolRegistry, new ToolDispatcher, new McpAuditLogger);
    }

    public function test_initialize_returns_protocol_version_and_capabilities(): void
    {
        $request = $this->jsonRpcRequest('initialize');

        $response = $this->controller()->handle($request);
        $body = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2.0', $body['jsonrpc']);
        $this->assertArrayHasKey('result', $body);

        $result = $body['result'];
        $this->assertNotEmpty($result['protocolVersion']);
        $this->assertNotEmpty($result['serverInfo']['name']);
        $this->assertNotEmpty($result['serverInfo']['version']);
        $this->assertArrayHasKey('tools', $result['capabilities']);
    }

    public function test_tools_list_is_filtered_to_token_scopes(): void
    {
        $request = $this->jsonRpcRequest('tools/list');
        // Only customers:read — exactly the two customer read tools advertise.
        $this->actingAsToken($request, ['customers:read']);

        $response = $this->controller()->handle($request);
        $body = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());

        $names = array_column($body['result']['tools'], 'name');
        sort($names);

        $this->assertSame(['get_customer', 'search_customers'], $names);

        // Every advertised tool carries the MCP advertisement shape.
        foreach ($body['result']['tools'] as $tool) {
            $this->assertNotEmpty($tool['name']);
            $this->assertNotEmpty($tool['description']);
            $this->assertIsArray($tool['inputSchema']);
            // Internal registry concerns are never advertised.
            $this->assertArrayNotHasKey('requiredScope', $tool);
            $this->assertArrayNotHasKey('handler', $tool);
        }
    }

    public function test_tools_list_advertises_write_tools_only_with_write_scope(): void
    {
        $request = $this->jsonRpcRequest('tools/list');
        $this->actingAsToken($request, ['tickets:read', 'tickets:write']);

        $response = $this->controller()->handle($request);
        $names = array_column($response->getData(true)['result']['tools'], 'name');

        $this->assertContains('create_ticket', $names);
        $this->assertContains('update_ticket', $names);
        $this->assertContains('reply_ticket', $names);
        $this->assertContains('get_ticket', $names);
        // A customer read tool is NOT advertised without customers:read.
        $this->assertNotContains('get_customer', $names);
    }

    public function test_tools_list_with_no_matching_scopes_is_empty(): void
    {
        $request = $this->jsonRpcRequest('tools/list');
        $this->actingAsToken($request, []);

        $response = $this->controller()->handle($request);

        $this->assertSame([], $response->getData(true)['result']['tools']);
    }

    public function test_unknown_method_returns_method_not_found(): void
    {
        $request = $this->jsonRpcRequest('does/not/exist');

        $response = $this->controller()->handle($request);
        $body = $response->getData(true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(-32601, $body['error']['code']);
    }

    public function test_tools_call_rejects_invalid_arguments_before_dispatch(): void
    {
        // get_customer requires an integer company_id; omit it.
        $request = $this->toolsCall('get_customer', []);
        $this->actingAsToken($request, ['customers:read']);

        $response = $this->controller()->handle($request);
        $body = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(-32602, $body['error']['code']);
        $this->assertArrayHasKey('company_id', $body['error']['data']['fields']);
    }

    public function test_tools_call_on_unwired_handler_returns_clean_error(): void
    {
        // Valid args, registered + (transport-side) callable tool, but no
        // concrete handler is wired yet (task 12.1) -> structured error, no trace.
        $request = $this->toolsCall('get_customer', ['company_id' => 1]);
        $this->actingAsToken($request, ['customers:read']);

        $response = $this->controller()->handle($request);
        $body = $response->getData(true);

        $this->assertSame(501, $response->getStatusCode());
        $this->assertSame(-32603, $body['error']['code']);
        $this->assertArrayNotHasKey('result', $body);
    }

    public function test_tools_call_dispatches_wired_handler_and_wraps_result(): void
    {
        $dispatcher = new ToolDispatcher;
        $dispatcher->register('customers.get', fn (array $args) => [
            'company_id' => $args['company_id'],
            'company_name' => 'Acme',
        ]);

        $controller = new McpController(new ToolRegistry, $dispatcher, new McpAuditLogger);

        $request = $this->toolsCall('get_customer', ['company_id' => 7]);
        $this->actingAsToken($request, ['customers:read']);

        $response = $controller->handle($request);
        $body = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($body['result']['isError']);
        $this->assertSame(7, $body['result']['structuredContent']['company_id']);
        $this->assertSame('text', $body['result']['content'][0]['type']);
    }

    public function test_successful_call_writes_exactly_one_success_audit_row_with_safe_context(): void
    {
        $dispatcher = new ToolDispatcher;
        $dispatcher->register('customers.get', fn (array $args) => [
            'company_id' => $args['company_id'],
            'company_name' => 'Acme',
        ]);

        $controller = new McpController(new ToolRegistry, $dispatcher, new McpAuditLogger);

        $request = $this->toolsCall('get_customer', ['company_id' => 7]);
        $this->actingAsToken($request, ['customers:read']);

        $controller->handle($request);

        // Exactly one row, outcome success, with the non-sensitive reference.
        $this->assertDatabaseCount('mcp_audit_logs', 1);
        $log = \App\Models\McpAuditLog::firstOrFail();
        $this->assertSame('success', $log->outcome);
        $this->assertSame('get_customer', $log->tool_name);
        $this->assertSame('customers:read', $log->scope_used);
        $this->assertSame('mcp-bot@system.local', $log->mcp_identity);
        // The token id reference is stored, never the token value.
        $this->assertSame('42', $log->access_token_id);
        $this->assertSame(['company_id' => 7], $log->context);
        $this->assertNull($log->reason);
    }

    public function test_unwired_handler_writes_exactly_one_error_audit_row(): void
    {
        // No handler registered for get_customer -> HandlerNotWiredException.
        $request = $this->toolsCall('get_customer', ['company_id' => 1]);
        $this->actingAsToken($request, ['customers:read']);

        $this->controller()->handle($request);

        $this->assertDatabaseCount('mcp_audit_logs', 1);
        $this->assertDatabaseHas('mcp_audit_logs', [
            'tool_name' => 'get_customer',
            'scope_used' => 'customers:read',
            'outcome' => 'error',
            'reason' => 'handler_not_wired',
        ]);
    }

    public function test_invalid_arguments_write_exactly_one_error_audit_row(): void
    {
        // Missing required company_id fails the schema gate (no handler runs).
        $request = $this->toolsCall('get_customer', []);
        $this->actingAsToken($request, ['customers:read']);

        $this->controller()->handle($request);

        $this->assertDatabaseCount('mcp_audit_logs', 1);
        $this->assertDatabaseHas('mcp_audit_logs', [
            'tool_name' => 'get_customer',
            'outcome' => 'error',
            'reason' => 'invalid_arguments',
        ]);
    }

    // --- helpers -------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function toolsCall(string $tool, array $arguments): Request
    {
        return $this->jsonRpcRequest('tools/call', [
            'name' => $tool,
            'arguments' => $arguments,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function jsonRpcRequest(string $method, array $params = []): Request
    {
        return Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => $method,
            'params' => $params,
        ]));
    }

    /**
     * Attach a stub Passport-style user whose token authorizes exactly the
     * given scopes.
     *
     * @param  array<int, string>  $scopes
     */
    private function actingAsToken(Request $request, array $scopes): void
    {
        $token = new class($scopes)
        {
            /** @param array<int, string> $scopes */
            public function __construct(private array $scopes)
            {
            }

            public function can(string $scope): bool
            {
                return in_array($scope, $this->scopes, true);
            }

            public function getKey(): int
            {
                return 42;
            }
        };

        $user = new class($token)
        {
            public string $email = 'mcp-bot@system.local';

            public function __construct(private object $token)
            {
            }

            public function token(): object
            {
                return $this->token;
            }
        };

        $request->setUserResolver(fn () => $user);
    }
}
