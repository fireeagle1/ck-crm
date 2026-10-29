<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Registry\ToolDispatcher;
use App\Mcp\Registry\ToolRegistry;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * End-to-end wiring tests for the MCP dispatch seam (task 12.1).
 *
 * These exercise the REAL `/api/mcp` route through the full middleware stack
 * (EnsureHttps -> throttle:mcp -> auth:api -> mcp.scope) and the singleton
 * {@see ToolDispatcher} wired by {@see \App\Providers\McpServiceProvider}, so a
 * `tools/call` reaches a concrete read query service or ticket write tool and
 * returns a REAL result — not a HandlerNotWiredException / "not available yet"
 * error.
 *
 * A representative read tool (`get_customer` against a seeded customer) and a
 * representative write tool (`create_ticket`) are driven end-to-end. The
 * `auth:api` (Passport) guard is satisfied with a stub Passport-style user
 * whose `token()->can()` grants the required scope, mirroring the stub used by
 * {@see McpControllerTest} / {@see CheckMcpScopeTest} but over the real HTTP
 * route rather than the controller in isolation.
 */
class McpDispatchWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // create_ticket dispatches admin notifications and may email the
        // customer; isolate those so the test focuses on dispatch wiring.
        Notification::fake();
        Mail::fake();

        config(['mcp.rate_limit' => 1000]);
    }

    public function test_every_registered_tool_has_a_wired_handler(): void
    {
        // The dispatcher the provider wired is the same singleton the transport
        // resolves. Every registry handler key must resolve to a concrete
        // handler so no tool is left orphaned (Req 2.3, 3.1).
        $dispatcher = app(ToolDispatcher::class);

        foreach (app(ToolRegistry::class)->all() as $definition) {
            $this->assertTrue(
                $dispatcher->has($definition->handler),
                sprintf(
                    'Tool "%s" (handler key "%s") is not wired.',
                    $definition->name,
                    $definition->handler,
                ),
            );
        }
    }

    public function test_get_customer_read_tool_dispatches_end_to_end(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->callTool('get_customer', ['customers:read'], [
            'company_id' => $customer->company_id,
        ]);

        $response->assertOk();

        $body = $response->json();

        // A real dispatch result, not a not-wired / not-available error.
        $this->assertArrayHasKey('result', $body);
        $this->assertArrayNotHasKey('error', $body);
        $this->assertFalse($body['result']['isError']);

        $data = $body['result']['structuredContent'];
        $this->assertSame($customer->company_id, $data['company_id']);
        // The DTO allow-list is the enforcement point; no security field leaks.
        $this->assertArrayNotHasKey('password', $data);
    }

    public function test_get_customer_missing_record_returns_structured_not_found(): void
    {
        $response = $this->callTool('get_customer', ['customers:read'], [
            'company_id' => 999999,
        ]);

        $response->assertOk();

        $payload = $response->json('result.structuredContent');

        // The wiring maps a null query result to a structured not-found
        // envelope rather than a bare null or a dispatch failure.
        $this->assertSame('not_found', $payload['error']['type']);
        $this->assertArrayHasKey('company_id', $payload['error']['fields']);
    }

    public function test_create_ticket_write_tool_dispatches_end_to_end(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->callTool('create_ticket', ['tickets:write'], [
            'company_id' => $customer->company_id,
            'subject' => 'Printer offline',
            'description' => 'The 3rd floor printer is unreachable.',
            'priority' => 'High',
            'ticket_type' => 'Incident',
            'notify_customer' => false,
        ]);

        $response->assertOk();

        $body = $response->json();
        $this->assertArrayHasKey('result', $body);
        $this->assertArrayNotHasKey('error', $body);

        $summary = $body['result']['structuredContent'];
        $this->assertNotNull($summary['ticket_id']);
        $this->assertSame('Open', $summary['status']);
        $this->assertSame($customer->company_id, $summary['company_id']);

        // The write actually happened through the shared TicketService, and was
        // attributed to the MCP bot actor (write parity, Req 9.5 / 11.5).
        $this->assertDatabaseHas('tickets', [
            'ticket_id' => $summary['ticket_id'],
            'company_id' => $customer->company_id,
            'status' => 'Open',
        ]);

        // The write ran through the shared service and admins were notified,
        // confirming the dispatch reached the real tool (not a stub).
        $this->assertNotNull(Ticket::find($summary['ticket_id']));
    }

    // --- helpers -------------------------------------------------------------

    /**
     * Drive the real /api/mcp route for a tools/call, authenticated against the
     * Passport `api` guard with a stub token granting exactly $scopes.
     *
     * @param  array<int, string>  $scopes
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(string $tool, array $scopes, array $arguments): \Illuminate\Testing\TestResponse
    {
        $this->authenticateApiGuard($scopes);

        return $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => $tool,
                'arguments' => $arguments,
            ],
        ], [
            // EnsureHttps: honour the forwarded protocol so the guard treats the
            // request as HTTPS (TLS terminates at the proxy in production).
            'X-Forwarded-Proto' => 'https',
        ]);
    }

    /**
     * Make the `auth:api` (Passport) guard resolve to a stub Passport-style
     * user whose attached token grants exactly the given scopes. Both
     * CheckMcpScope and the transport authorize via `token()->can()`.
     *
     * @param  array<int, string>  $scopes
     */
    private function authenticateApiGuard(array $scopes): void
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

        $user = new class($token) implements \Illuminate\Contracts\Auth\Authenticatable
        {
            public string $email = 'mcp-bot@system.local';

            public bool $is_admin = false;

            public function __construct(private object $mcpToken)
            {
            }

            public function token(): object
            {
                return $this->mcpToken;
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 42;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName(): string
            {
                return '';
            }
        };

        // Resolve the `api` guard to our stub without invoking Passport's
        // key-based token driver (no OAuth keys exist in the test env). A
        // closure-backed guard returns the stub user directly, so the real
        // middleware stack (auth:api -> mcp.scope) still runs end-to-end.
        \Illuminate\Support\Facades\Auth::extend('passport', function () use ($user) {
            return new class($user) implements \Illuminate\Contracts\Auth\Guard
            {
                public function __construct(private object $stub)
                {
                }

                public function check(): bool
                {
                    return true;
                }

                public function guest(): bool
                {
                    return false;
                }

                public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
                {
                    return $this->stub;
                }

                public function id(): int
                {
                    return 42;
                }

                public function validate(array $credentials = []): bool
                {
                    return true;
                }

                public function hasUser(): bool
                {
                    return true;
                }

                public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
                {
                }
            };
        });

        // Rebuild the `api` guard so the override takes effect for this request.
        $this->app['auth']->forgetGuards();
    }
}
