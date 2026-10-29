<?php

namespace Tests\Feature\CustomerApp;

use App\Console\Commands\IngestAppFindings;
use App\Http\Controllers\Api\FindingsController;
use App\Providers\PassportServiceProvider;
use App\Services\FindingsIngestService;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Structural / smoke guarantees for the findings receiving stub (task 7.5).
 *
 * These tests do not touch the database. They assert, by inspecting the
 * application's registered scopes, its route table, and the source of the three
 * findings components, that:
 *
 *   1. `apps:write` is in the MCP allowed-scope set (Req 3.5).
 *   2. The findings route sits inside the MCP middleware group so it inherits
 *      `auth:api`, `EnsureHttps`, and `throttle:mcp` (Req 3.5).
 *   3. The controller, command, and ingest service contain no GitHub / OpenAI
 *      client import and no outbound HTTP client call to an external scan API
 *      (Req 3.6 — no scanning logic is built in this spec).
 *   4. The `apps:ingest-findings` command / `IngestAppFindings` class is not
 *      registered in the scheduler (Req 3.6 — the stub never schedules the
 *      daily sync).
 */
class FindingsScopeAndNoScanStructureTest extends TestCase
{
    /**
     * The route URI and verb under test.
     */
    private const FINDINGS_URI = 'api/mcp/apps/{identifier}/findings';

    /**
     * Source tokens that would indicate a GitHub / OpenAI SDK import or an
     * outbound scan HTTP call. Checked case-insensitively against each
     * component's source. The components' docblocks truthfully disclaim scan
     * logic in prose ("no GitHub, ChatGPT, or OpenAI logic"), so rather than
     * banning the bare words we ban the concrete code shapes: SDK namespace
     * imports and HTTP-client calls to external hosts.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_TOKENS = [
        'use OpenAI',          // OpenAI PHP SDK import
        'use Github',          // GitHub SDK import (e.g. knplabs/github-api)
        'OpenAI\\',            // fully-qualified OpenAI SDK reference
        'Github\\',            // fully-qualified GitHub SDK reference
        'GuzzleHttp\\Client',  // direct Guzzle HTTP client instantiation
        'Http::post',          // Laravel HTTP client POST to an external host
        'Http::get',           // Laravel HTTP client GET to an external host
        'curl_init',           // raw cURL
        'curl_exec',           // raw cURL
        'file_get_contents(\'http', // remote fetch over http
        'file_get_contents("http', // remote fetch over http
    ];

    // --- 1. Scope registration (Req 3.5) -------------------------------------

    public function test_apps_write_is_in_the_allowed_mcp_scope_set(): void
    {
        $this->assertArrayHasKey(
            'apps:write',
            PassportServiceProvider::MCP_SCOPES,
            'The findings endpoint write scope "apps:write" must be in the MCP allowed-scope set (Req 3.5).',
        );
    }

    // --- 2. Route sits inside the MCP middleware group (Req 3.5) --------------

    public function test_findings_route_is_registered_in_the_mcp_group(): void
    {
        $route = $this->findingsRoute();

        $this->assertNotNull(
            $route,
            sprintf('Expected a POST route for "%s" to be registered.', self::FINDINGS_URI),
        );

        $this->assertContains(
            'POST',
            $route->methods(),
            'The findings route must accept POST.',
        );

        $this->assertSame(
            FindingsController::class . '@store',
            $route->getActionName(),
            'The findings route must be handled by FindingsController::store.',
        );
    }

    public function test_findings_route_inherits_the_mcp_group_middleware(): void
    {
        $route = $this->findingsRoute();
        $this->assertNotNull($route, 'Findings route must be registered.');

        $middleware = $route->gatherMiddleware();

        // Passport guard: the endpoint authenticates callers via auth:api (Req 3.5).
        $this->assertContains(
            'auth:api',
            $middleware,
            'The findings route must inherit the MCP group "auth:api" (Passport) guard.',
        );

        // The MCP group's transport hardening: HTTPS-only + the named limiter.
        $this->assertContains(
            \App\Http\Middleware\EnsureHttps::class,
            $middleware,
            'The findings route must inherit the MCP group EnsureHttps middleware.',
        );

        $this->assertContains(
            'throttle:mcp',
            $middleware,
            'The findings route must inherit the MCP group throttle:mcp limiter.',
        );
    }

    // --- 3. No GitHub / OpenAI client, no scan HTTP call (Req 3.6) ------------

    #[DataProvider('noScanComponents')]
    public function test_component_contains_no_scan_client_or_http_call(string $class): void
    {
        $source = $this->sourceOf($class);

        foreach (self::FORBIDDEN_TOKENS as $token) {
            $this->assertStringNotContainsStringIgnoringCase(
                $token,
                $source,
                sprintf(
                    'Component %s must contain no GitHub/OpenAI client import or outbound scan HTTP call (Req 3.6); found "%s".',
                    $class,
                    $token,
                ),
            );
        }
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function noScanComponents(): array
    {
        return [
            'controller' => [FindingsController::class],
            'command' => [IngestAppFindings::class],
            'ingest service' => [FindingsIngestService::class],
        ];
    }

    // --- 4. Command is not scheduler-registered (Req 3.6) --------------------

    public function test_ingest_findings_command_is_not_scheduler_registered(): void
    {
        $scheduleSources = [
            base_path('routes/console.php'),
            base_path('bootstrap/app.php'),
        ];

        // app/Console/Kernel.php does not exist on this Laravel 13 app, but
        // guard for it in case a future upgrade reintroduces it.
        $kernel = app_path('Console/Kernel.php');
        if (is_file($kernel)) {
            $scheduleSources[] = $kernel;
        }

        foreach ($scheduleSources as $path) {
            $source = (string) file_get_contents($path);

            $this->assertStringNotContainsString(
                'apps:ingest-findings',
                $source,
                sprintf(
                    'The findings command signature "apps:ingest-findings" must not be scheduler-registered (found in %s, Req 3.6).',
                    $path,
                ),
            );

            $this->assertStringNotContainsString(
                'IngestAppFindings',
                $source,
                sprintf(
                    'The IngestAppFindings command must not be scheduler-registered (found in %s, Req 3.6).',
                    $path,
                ),
            );
        }
    }

    // --- helpers -------------------------------------------------------------

    /**
     * Resolve the registered findings route via the router's route collection.
     */
    private function findingsRoute(): ?Route
    {
        foreach (RouteFacade::getRoutes() as $route) {
            if ($route->uri() === self::FINDINGS_URI && in_array('POST', $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Read the source file backing a class.
     *
     * @param  class-string  $class
     */
    private function sourceOf(string $class): string
    {
        $file = (new ReflectionClass($class))->getFileName();

        $this->assertIsString($file, sprintf('Could not resolve source file for %s.', $class));
        $this->assertFileExists($file);

        return (string) file_get_contents($file);
    }
}
