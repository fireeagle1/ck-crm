<?php

namespace Tests\Feature\CustomerApp;

use App\Http\Controllers\Api\FindingsController;
use App\Mcp\Audit\McpAuditLogger;
use App\Models\AppPackage;
use App\Models\Customer;
use App\Models\CustomerApp;
use App\Models\McpAuditLog;
use App\Models\SecurityFinding;
use App\Models\Service;
use App\Services\FindingsIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feature: customer-app-services, Property 5: Findings endpoint authorization and audit.
 *
 * For any request to the Findings_Endpoint and any token scope set, the endpoint processes the
 * request if and only if the presenting token carries the `apps:write` scope; a denied request
 * persists no records; and across any sequence of requests (authorized successes,
 * validation/unknown-app errors, and authorization denials), exactly one audit entry is written
 * per request with an outcome matching that request.
 *
 * Code under test: App\Http\Controllers\Api\FindingsController::store — the explicit `apps:write`
 * scope gate (Req 3.5) and the single-audit-row-per-outcome discipline (Req 3.7).
 *
 * Validates: Requirements 3.5, 3.7
 *
 * innmind/black-box is not a dependency of this project, so rather than adding a new dependency the
 * universal property is exercised with a loop that generates >= 100 random cases. Each case picks a
 * random token scope set (sometimes including `apps:write`, sometimes not) and a random request
 * flavour (valid payload for an existing app, unknown app, or invalid payload), drives the
 * controller directly with the project's stub Passport user/token technique (as in
 * {@see \Tests\Feature\Mcp\CheckMcpScopeTest} and {@see \Tests\Feature\Mcp\McpControllerTest},
 * which deliberately avoid standing up the full OAuth/HTTP stack), and asserts:
 *   - the request is processed (reaches the ingest service) iff the token carries `apps:write`;
 *   - when denied (no `apps:write`): HTTP 403, nothing persisted, and exactly one audit row with
 *     outcome `denied`;
 *   - when `apps:write` is present: the HTTP status and audit outcome match the flavour
 *     (success 200 / unknown-app 404 / invalid 422), exactly one audit row with the matching
 *     outcome, and child records are persisted only on success.
 */
class FindingsEndpointAuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    private const GENERATED_CASES = 150;

    private const REQUIRED_SCOPE = 'apps:write';

    private const TOOL_NAME = 'ingest_app_findings';

    /** Scopes other than apps:write that must never, alone, authorise the endpoint. */
    private const OTHER_SCOPES = [
        'apps:read',
        'customers:read',
        'customers:write',
        'tickets:read',
        'tickets:write',
        'invoices:read',
    ];

    private FindingsController $controller;

    /** Observed count of ingest() invocations, used to assert "processed iff apps:write". */
    private int $ingestCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // A spy ingest service so we can observe whether the controller reached the ingest step
        // (i.e. passed the scope gate and payload-shape validation) without mocking away the real
        // persistence/exception behaviour. It delegates to the real service.
        $spy = new class($this) extends FindingsIngestService
        {
            public function __construct(private FindingsEndpointAuthorizationAuditTest $test)
            {
            }

            public function ingest(string $appIdentifier, array $packages, array $findings): array
            {
                $this->test->recordIngestCall();

                return (new FindingsIngestService)->ingest($appIdentifier, $packages, $findings);
            }
        };

        $this->controller = new FindingsController($spy, new McpAuditLogger);
    }

    public function recordIngestCall(): void
    {
        $this->ingestCalls++;
    }

    public function test_endpoint_processes_iff_apps_write_and_audits_exactly_one_matching_outcome(): void
    {
        for ($case = 0; $case < self::GENERATED_CASES; $case++) {
            // Clear audit + child tables so each case asserts "exactly one row per request".
            McpAuditLog::query()->delete();
            $this->ingestCalls = 0;

            $app = $this->seedApp();
            $priorPackages = AppPackage::count();
            $priorFindings = SecurityFinding::count();

            $hasWrite = $this->randomScopesIncludeWrite($case, $scopes);
            $flavour = $this->flavourForCase($case); // 'success' | 'unknown' | 'invalid'

            [$identifier, $payload] = $this->buildRequestInput($flavour, $app, $case);

            $request = $this->makeRequest($payload, $scopes);

            $response = $this->invoke($request, $identifier);
            $status = $response->getStatusCode();

            // --- Property: processed (reached ingest) IFF the token carries apps:write ----------
            // The ingest service is only reached after the scope gate AND the payload-shape
            // validation pass. A missing scope (denied) or a malformed body (422 before dispatch)
            // never reaches it; everything else does.
            $reachedIngest = $this->ingestCalls > 0;

            if (! $hasWrite) {
                $this->assertFalse(
                    $reachedIngest,
                    "case {$case} ({$flavour}, no apps:write): request must NOT be processed"
                );
            } else {
                // With apps:write and a shape-valid body, the controller always reaches ingest.
                // success persists; unknown-app and allow-list-invalid both enter ingest and throw
                // (the service resolves the app then validates), so the request is "processed".
                $this->assertTrue(
                    $reachedIngest,
                    "case {$case} ({$flavour}, apps:write): request must be processed (reach ingest)"
                );
            }

            if (! $hasWrite) {
                // --- Denied branch (Req 3.5): 403, nothing persisted, one `denied` audit row ----
                $this->assertSame(403, $status, "case {$case}: missing apps:write must return 403");

                $this->assertSame(
                    $priorPackages,
                    AppPackage::count(),
                    "case {$case}: a denied request must persist no packages"
                );
                $this->assertSame(
                    $priorFindings,
                    SecurityFinding::count(),
                    "case {$case}: a denied request must persist no findings"
                );

                $this->assertExactlyOneAuditRow($case, McpAuditLogger::OUTCOME_DENIED, 'missing_scope');
            } else {
                // --- Authorised branch: outcome matches the flavour (Req 3.7) -------------------
                match ($flavour) {
                    'success' => $this->assertSuccessCase($case, $status),
                    'unknown' => $this->assertUnknownCase($case, $status, $priorPackages, $priorFindings),
                    'invalid' => $this->assertInvalidCase($case, $status, $priorPackages, $priorFindings),
                };
            }

            // Clean slate for the next generated case (cascade removes app + children).
            $app->service->customer()->delete();
        }
    }

    /**
     * Success flavour with apps:write: HTTP 200, exactly one `success` audit row, and the submitted
     * child records are persisted.
     */
    private function assertSuccessCase(int $case, int $status): void
    {
        $this->assertSame(200, $status, "case {$case}: a valid authorised payload must return 200");

        $this->assertExactlyOneAuditRow($case, McpAuditLogger::OUTCOME_SUCCESS, null);

        // Persistence happens only on success. The valid payload always carries >= 1 of each.
        $this->assertGreaterThan(
            0,
            AppPackage::count(),
            "case {$case}: a successful ingest must persist packages"
        );
        $this->assertGreaterThan(
            0,
            SecurityFinding::count(),
            "case {$case}: a successful ingest must persist findings"
        );
    }

    /**
     * Unknown-app flavour with apps:write: HTTP 404, exactly one `error` audit row, nothing persisted.
     */
    private function assertUnknownCase(int $case, int $status, int $priorPackages, int $priorFindings): void
    {
        $this->assertSame(404, $status, "case {$case}: an unknown app must return 404");

        $this->assertExactlyOneAuditRow($case, McpAuditLogger::OUTCOME_ERROR, 'unknown_app');

        $this->assertSame(
            $priorPackages,
            AppPackage::count(),
            "case {$case}: an unknown-app request must persist no packages"
        );
        $this->assertSame(
            $priorFindings,
            SecurityFinding::count(),
            "case {$case}: an unknown-app request must persist no findings"
        );
    }

    /**
     * Invalid-payload flavour with apps:write: HTTP 422, exactly one `error` audit row, nothing
     * persisted. Covers both shape-validation (caught by the controller's validate() -> 422 before
     * dispatch) and allow-list validation (InvalidFindingsPayloadException -> 422 after dispatch).
     */
    private function assertInvalidCase(int $case, int $status, int $priorPackages, int $priorFindings): void
    {
        $this->assertSame(422, $status, "case {$case}: an invalid payload must return 422");

        $this->assertExactlyOneAuditRow($case, McpAuditLogger::OUTCOME_ERROR, 'invalid_payload');

        $this->assertSame(
            $priorPackages,
            AppPackage::count(),
            "case {$case}: an invalid payload must persist no packages"
        );
        $this->assertSame(
            $priorFindings,
            SecurityFinding::count(),
            "case {$case}: an invalid payload must persist no findings"
        );
    }

    /**
     * Assert that the request wrote EXACTLY ONE audit row with the expected outcome and reason, and
     * that the row names this endpoint's tool and scope (Req 3.7).
     */
    private function assertExactlyOneAuditRow(int $case, string $outcome, ?string $reason): void
    {
        $this->assertSame(
            1,
            McpAuditLog::count(),
            "case {$case}: exactly one audit row must be written per request"
        );

        $log = McpAuditLog::firstOrFail();

        $this->assertSame($outcome, $log->outcome, "case {$case}: audit outcome mismatch");
        $this->assertSame($reason, $log->reason, "case {$case}: audit reason mismatch");
        $this->assertSame(self::TOOL_NAME, $log->tool_name, "case {$case}: audit tool_name mismatch");
        $this->assertSame(self::REQUIRED_SCOPE, $log->scope_used, "case {$case}: audit scope_used mismatch");
    }

    /**
     * Drive the controller's store() as the route would. All generated payloads are well-shaped
     * (they pass the controller's `packages`/`findings` array rule), so store() returns a JSON
     * response directly rather than throwing the framework's shape-validation exception. The guard
     * remains as defence so an unexpected shape rejection surfaces the same 422 a real request
     * would, instead of leaking as an uncaught exception.
     */
    private function invoke(Request $request, string $identifier)
    {
        try {
            return $this->controller->store($request, $identifier);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Decide this case's scope set. Roughly half the cases carry apps:write (possibly alongside
     * noise scopes); the rest carry only noise scopes (never apps:write).
     *
     * @param  array<int, string>  $scopes  populated by reference
     */
    private function randomScopesIncludeWrite(int $case, ?array &$scopes): bool
    {
        // Pin the two structural boundaries so both authorised and denied paths are always covered.
        $include = match ($case) {
            0 => false,          // empty scope set -> denied
            1 => true,           // apps:write only -> authorised
            default => (bool) random_int(0, 1),
        };

        $scopes = $this->randomNoiseScopes();

        if ($include) {
            $scopes[] = self::REQUIRED_SCOPE;
            shuffle($scopes);
        } else {
            // Ensure the required scope is absent even if a noise scope ever collided.
            $scopes = array_values(array_filter($scopes, static fn (string $s): bool => $s !== self::REQUIRED_SCOPE));
        }

        return $include;
    }

    /**
     * A random subset (possibly empty) of non-authorising scopes.
     *
     * @return array<int, string>
     */
    private function randomNoiseScopes(): array
    {
        $scopes = [];

        foreach (self::OTHER_SCOPES as $scope) {
            if (random_int(0, 1) === 1) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    /**
     * Pick the request flavour for this case, pinning one of each early so every outcome is covered.
     */
    private function flavourForCase(int $case): string
    {
        return match ($case) {
            0, 1 => 'success',
            2 => 'unknown',
            3 => 'invalid',
            default => ['success', 'unknown', 'invalid'][random_int(0, 2)],
        };
    }

    /**
     * Build the (identifier, payload) pair for a flavour.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildRequestInput(string $flavour, CustomerApp $app, int $case): array
    {
        return match ($flavour) {
            'success' => [(string) $app->id, $this->validPayload($case)],
            'unknown' => [$this->unknownIdentifier($app), $this->validPayload($case)],
            'invalid' => [(string) $app->id, $this->invalidPayload($case)],
        };
    }

    /**
     * A shape-valid, allow-list-valid payload carrying at least one package and one finding so a
     * success persists observable rows.
     *
     * @return array<string, mixed>
     */
    private function validPayload(int $case): array
    {
        $packages = [];
        $packageCount = random_int(1, 4);
        for ($i = 0; $i < $packageCount; $i++) {
            $packages[] = [
                'name' => "vendor/pkg-{$case}-{$i}",
                'version' => random_int(0, 9).'.'.random_int(0, 20).'.'.random_int(0, 50),
            ];
        }

        $findings = [];
        $findingCount = random_int(1, 4);
        $severities = ['low', 'medium', 'high', 'critical'];
        for ($i = 0; $i < $findingCount; $i++) {
            $findings[] = [
                'severity' => $severities[random_int(0, count($severities) - 1)],
                'description' => "Finding {$case}-{$i}",
                'status' => SecurityFinding::STATUSES[random_int(0, count(SecurityFinding::STATUSES) - 1)],
            ];
        }

        return ['packages' => $packages, 'findings' => $findings];
    }

    /**
     * A payload that is well-shaped (passes the controller's `packages`/`findings` array rule) but
     * fails the ingest service's allow-list validation, so it reaches the controller's AUDITED 422
     * path (InvalidFindingsPayloadException -> `error`/`invalid_payload` audit row).
     *
     * Shape-invalid bodies (e.g. `packages` not an array) are deliberately NOT generated here: the
     * controller rejects those via Laravel's own validation BEFORE any audit write, so they are a
     * different, un-audited framework-level rejection outside this property's "invalid payload"
     * flavour.
     *
     * @return array<string, mixed>
     */
    private function invalidPayload(int $case): array
    {
        return match (random_int(0, 3)) {
            0 => ['packages' => [['version' => '1.0.0']], 'findings' => []], // package missing name
            1 => ['packages' => [['name' => 'acme/widget']], 'findings' => []], // package missing version
            2 => [
                'packages' => [],
                'findings' => [['severity' => 'high', 'description' => 'x', 'status' => 'bogus-'.$case]],
            ], // finding status outside the allowed set
            default => [
                'packages' => [],
                'findings' => [['severity' => 'high', 'status' => SecurityFinding::STATUS_OPEN]],
            ], // finding missing description
        };
    }

    /**
     * An identifier guaranteed not to resolve to an existing CustomerApp.
     */
    private function unknownIdentifier(CustomerApp $app): string
    {
        return match (random_int(0, 2)) {
            0 => (string) ($app->id + random_int(1000, 9999)),
            1 => 'missing-'.uniqid('', true),
            default => (string) random_int(100000, 999999),
        };
    }

    /**
     * Build a POST request to the findings endpoint carrying the given JSON body and a stub
     * Passport user whose token authorises exactly the given scopes.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $scopes
     */
    private function makeRequest(array $payload, array $scopes): Request
    {
        $request = Request::create(
            '/api/mcp/apps/1/findings',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                // Mark the request secure so it matches the HTTPS the route group requires; the
                // controller is invoked directly here so EnsureHttps does not run, but this keeps
                // the request representative.
                'HTTPS' => 'on',
            ],
            json_encode($payload)
        );

        $this->actingAsToken($request, $scopes);

        return $request;
    }

    /**
     * Attach a stub Passport-style user whose token authorises exactly the given scopes. Mirrors
     * the technique in CheckMcpScopeTest / McpControllerTest: authorisation derives SOLELY from the
     * token scope via can(); is_admin is present but must never substitute for a scope.
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

            public bool $is_admin = true;

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

    /**
     * Seed one existing CustomerApp (with its Service + Customer) that success/invalid cases target.
     */
    private function seedApp(): CustomerApp
    {
        $customer = Customer::create([
            'company_name' => 'Acme '.uniqid('', true),
            'customer_name' => 'Owner',
            'phone_number' => '0000000000',
            'address_line1' => '1 Test Street',
            'city' => 'Testville',
            'postal_code' => 'TE5 7ST',
            'country' => 'GB',
        ]);

        $service = Service::create([
            'company_id' => $customer->company_id,
            'service_short' => 'Managed App',
            'status' => 'Active',
        ]);

        return CustomerApp::create([
            'service_id' => $service->service_id,
            'repo_url' => 'https://git.example.test/app.git',
            'hosting_location' => 'cpanel://host/app',
            'codebase_info' => 'PHP 8.3 / Laravel 13',
        ]);
    }
}
