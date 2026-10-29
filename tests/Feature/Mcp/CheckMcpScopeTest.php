<?php

namespace Tests\Feature\Mcp;

use App\Http\Middleware\CheckMcpScope;
use App\Mcp\Audit\McpAuditLogger;
use App\Mcp\Registry\ToolRegistry;
use App\Models\McpAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Behavioural tests for the MCP per-tool scope gate (task 7.2).
 *
 * The middleware is driven directly through handle() with a stub Passport
 * user/token so the tests exercise the authorization decision and the denial
 * audit write without standing up the full OAuth/HTTP stack.
 */
class CheckMcpScopeTest extends TestCase
{
    use RefreshDatabase;

    private CheckMcpScope $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckMcpScope(new ToolRegistry, new McpAuditLogger);
    }

    public function test_tools_list_passes_through_without_scope_check(): void
    {
        $request = $this->jsonRpcRequest('tools/list');
        // No authenticated user at all — lifecycle methods must still pass.
        $called = false;

        $response = $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called, 'tools/list must reach the downstream handler.');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseCount('mcp_audit_logs', 0);
    }

    public function test_initialize_passes_through_without_scope_check(): void
    {
        $request = $this->jsonRpcRequest('initialize');
        $called = false;

        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response()->json(['ok' => true]);
        });

        $this->assertTrue($called);
        $this->assertDatabaseCount('mcp_audit_logs', 0);
    }

    public function test_registered_tool_with_scope_is_permitted(): void
    {
        $request = $this->toolsCall('get_customer', ['company_id' => 1]);
        $this->actingAsToken($request, ['customers:read'], isAdmin: false);

        $called = false;
        $response = $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response()->json(['result' => 'ok']);
        });

        $this->assertTrue($called, 'A registered tool with the required scope must run.');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseCount('mcp_audit_logs', 0);
    }

    public function test_registered_tool_without_scope_is_denied_and_audited(): void
    {
        $request = $this->toolsCall('get_customer', ['company_id' => 1]);
        // Token carries a different scope — the required customers:read is absent.
        $this->actingAsToken($request, ['invoices:read'], isAdmin: false);

        $called = false;
        $response = $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response()->json(['result' => 'ok']);
        });

        $this->assertFalse($called, 'Missing scope must stop the downstream handler.');
        $this->assertSame(403, $response->getStatusCode());

        $this->assertDatabaseHas('mcp_audit_logs', [
            'tool_name' => 'get_customer',
            'scope_used' => 'customers:read',
            'outcome' => 'denied',
            'reason' => 'missing_scope',
        ]);
    }

    public function test_is_admin_never_grants_access_without_scope(): void
    {
        $request = $this->toolsCall('create_ticket', ['company_id' => 1]);
        // is_admin = true but NO tickets:write scope -> still denied (Req 2.1).
        $this->actingAsToken($request, ['customers:read'], isAdmin: true);

        $called = false;
        $response = $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response()->json(['result' => 'ok']);
        });

        $this->assertFalse($called, 'is_admin must never substitute for a scope.');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertDatabaseHas('mcp_audit_logs', [
            'tool_name' => 'create_ticket',
            'scope_used' => 'tickets:write',
            'outcome' => 'denied',
            'reason' => 'missing_scope',
        ]);
    }

    public function test_unregistered_tool_is_denied_and_audited(): void
    {
        $request = $this->toolsCall('delete_customer', ['company_id' => 1]);
        // Even with a broad scope set, an unlisted tool is denied by default.
        $this->actingAsToken($request, ['customers:read', 'tickets:write'], isAdmin: true);

        $called = false;
        $response = $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response()->json(['result' => 'ok']);
        });

        $this->assertFalse($called, 'An unregistered tool must never dispatch.');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertDatabaseHas('mcp_audit_logs', [
            'tool_name' => 'delete_customer',
            'outcome' => 'denied',
            'reason' => 'unregistered_tool',
        ]);
    }

    public function test_tools_call_without_a_tool_name_is_denied(): void
    {
        $request = $this->jsonRpcRequest('tools/call', ['arguments' => []]);
        $this->actingAsToken($request, ['customers:read'], isAdmin: false);

        $called = false;
        $response = $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response()->json(['result' => 'ok']);
        });

        $this->assertFalse($called);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertDatabaseHas('mcp_audit_logs', [
            'tool_name' => null,
            'outcome' => 'denied',
            'reason' => 'unregistered_tool',
        ]);
    }

    // --- helpers -------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $params
     */
    private function toolsCall(string $tool, array $arguments = []): Request
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
     * given scopes. `is_admin` is set but must never influence the decision.
     *
     * @param  array<int, string>  $scopes
     */
    private function actingAsToken(Request $request, array $scopes, bool $isAdmin): void
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

        $user = new class($token, $isAdmin)
        {
            public string $email = 'mcp-bot@system.local';

            public bool $is_admin;

            public function __construct(private object $token, bool $isAdmin)
            {
                $this->is_admin = $isAdmin;
            }

            public function token(): object
            {
                return $this->token;
            }
        };

        $request->setUserResolver(fn () => $user);
    }
}
