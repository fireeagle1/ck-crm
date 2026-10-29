# Implementation Plan: Customer App Services

## Overview

This plan builds the Customer App Services feature additively on the existing Laravel 13 / PHP 8.3 CRM. The sequence is: database schema first (three cascading tables), then Eloquent models and the `Service` relationship, then the security-summary and DTO layers that gate the customer surface, then the Passport-scoped findings ingestion stub (service + HTTP endpoint + Artisan command), and finally the admin and portal controllers that wire everything together. Property-based and example/integration tests follow each unit of code so the eight correctness properties are validated incrementally.

Nothing in this plan writes GitHub, ChatGPT, or OpenAI scanning logic, and the Artisan command is never registered in the scheduler (Req 3.6). The feature assumes the Passport `auth:api` guard, `CheckMcpScope` middleware, and `McpAuditLogger` from the sibling `chatgpt-mcp-integration` spec are already present; only the new `apps:write` scope string and the new route are added here.

## Tasks

- [x] 1. Create the database schema (three cascading migrations)
  - [x] 1.1 Create `customer_apps` migration
    - Add `database/migrations/<timestamp>_create_customer_apps_table.php`
    - Columns: `id`, `service_id` (bigint, FK → `services.service_id`, **unique**, `onDelete('cascade')`), `repo_url` (string, nullable), `hosting_location` (string, nullable), `codebase_info` (text, nullable), `timestamps`
    - No billing columns
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.6_

  - [x] 1.2 Create `app_packages` migration
    - Add `database/migrations/<timestamp>_create_app_packages_table.php`
    - Columns: `id`, `customer_app_id` (bigint, FK → `customer_apps.id`, `onDelete('cascade')`), `name` (string), `version` (string), `synced_at` (timestamp, nullable), `timestamps`
    - _Requirements: 2.1, 2.3, 2.4, 1.6_

  - [x] 1.3 Create `security_findings` migration
    - Add `database/migrations/<timestamp>_create_security_findings_table.php`
    - Columns: `id`, `customer_app_id` (bigint, FK → `customer_apps.id`, `onDelete('cascade')`), `severity` (string), `description` (text), `status` (string, default `'open'`), `synced_at` (timestamp, nullable), `timestamps`
    - _Requirements: 2.2, 2.3, 2.4, 1.6_

  - [x]* 1.4 Write schema example tests
    - Assert the three tables and their columns exist, `service_id` is a unique index, FKs cascade, and `customer_apps` has no billing column
    - _Requirements: 1.1, 1.2, 1.5, 2.1_

- [x] 2. Implement Eloquent models and the Service relationship
  - [x] 2.1 Create `App\Models\AppPackage` and `App\Models\SecurityFinding`
    - `AppPackage`: `$fillable = ['customer_app_id', 'name', 'version', 'synced_at']`, `synced_at` cast to datetime, `belongsTo(CustomerApp::class)`
    - `SecurityFinding`: `$fillable = ['customer_app_id', 'severity', 'description', 'status', 'synced_at']`, `synced_at` cast to datetime, `belongsTo(CustomerApp::class)`, and the status constants `STATUS_OPEN`, `STATUS_RESOLVED`, `STATUS_DISMISSED`, `STATUSES`
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 2.2 Create `App\Models\CustomerApp`
    - `$fillable = ['service_id', 'repo_url', 'hosting_location', 'codebase_info']`
    - Relationships: `service(): BelongsTo` (on `service_id`/`service_id`), `packages(): HasMany`, `findings(): HasMany`, `openFindings(): HasMany` filtered to `SecurityFinding::STATUS_OPEN`
    - _Requirements: 1.1, 1.2, 2.4_

  - [x] 2.3 Add `customerApp(): HasOne` to `App\Models\Service`
    - Add only the `hasOne(CustomerApp::class, 'service_id', 'service_id')` relationship; leave `$fillable`/`$casts` untouched
    - _Requirements: 1.3_

  - [x]* 2.4 Write relationship example tests
    - Assert `Service::customerApp`, `CustomerApp::service/packages/findings/openFindings` resolve, and an app with zero packages/findings is valid
    - _Requirements: 1.2, 1.3, 2.4_

  - [x]* 2.5 Write property test for service deletion cascade
    - **Property 2: Service deletion removes the whole technical subtree**
    - Generate apps with random N packages / M findings, delete the service, assert zero `customer_apps`/`app_packages`/`security_findings` rows remain
    - **Validates: Requirements 1.6, 2.4**

- [x] 3. Checkpoint - schema and models
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Implement the security summary and customer DTO
  - [x] 4.1 Implement `App\Support\SecuritySummaryService`
    - Static `for(?CustomerApp $app): string` deriving the string from open-finding count alone: 0 → "Security checks passing", 1 → "1 issue being addressed", N → "{N} issues being addressed"
    - Read only `status`; never touch severity/description/packages
    - _Requirements: 6.1, 6.2, 6.3, 5.2_

  - [x]* 4.2 Write property test for the security summary
    - **Property 7: Security summary is a status-only function of open findings**
    - Random finding multisets; assert "passing" iff open-count == 0 else exact count wording, and the string contains no severity/description/package text
    - **Validates: Requirements 5.2, 6.1, 6.2, 6.3**

  - [x]* 4.3 Write unit tests for `SecuritySummaryService` branches
    - Zero open, one open (singular), many open (plural), and open-count ignoring `resolved`/`dismissed`
    - _Requirements: 6.1, 6.2, 6.3_

  - [x] 4.4 Implement `App\Support\Dto\CustomerAppSummaryDto`
    - Static `fromService(Service $service): array` returning exactly `name`, `status`, `billing`, `security_summary`
    - Billing derived from the `Service` record (Free when no `stripe_subscription_id` and no `service_monthly_charge`, else the formatted charge); summary from `SecuritySummaryService`
    - Never call `toArray()`; never read `repo_url`/`hosting_location`/`codebase_info`/packages/findings detail
    - _Requirements: 5.1, 5.3, 5.4, 1.5_

  - [ ]* 4.5 Write property test for DTO allow-list
    - **Property 6: Customer output contains exactly the allow-listed fields and no technical detail**
    - Generate app/service pairs with all technical fields populated; assert DTO keys ⊆ {name, status, billing, security_summary}, forbidden values recursively absent, and the DTO is not produced by model serialisation
    - **Validates: Requirements 5.1, 5.3, 5.4**

  - [ ]* 4.6 Write unit tests for `CustomerAppSummaryDto`
    - Assert the exact key set for a free service and a Stripe-charged service
    - _Requirements: 5.1_

- [x] 5. Implement the findings ingestion service
  - [x] 5.1 Implement `App\Services\FindingsIngestService`
    - `ingest(string $appIdentifier, array $packages, array $findings): array` returning `{packages:int, findings:int}`
    - Resolve the `CustomerApp` by identifier; throw a domain exception naming the unknown identifier and persist nothing if none matches
    - Validate payload against an explicit allow-list (package: `name`+`version`; finding: `severity`+`description`+`status ∈ SecurityFinding::STATUSES`); persist nothing on failure
    - Bulk replace inside `DB::transaction`: delete existing packages + findings for the app, insert submitted rows stamping `synced_at = now()`; return counts only
    - Reference no GitHub/OpenAI client
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 2.3, 2.5_

  - [x]* 5.2 Write property test for bulk-replace idempotence
    - **Property 3: Findings ingestion is a full, idempotent bulk replacement**
    - Random prior set + random valid payload; assert stored set equals payload with `synced_at` stamped; re-apply same payload and assert unchanged
    - **Validates: Requirements 2.3, 2.5, 3.1, 3.2**

  - [x]* 5.3 Write property test for rejection without mutation
    - **Property 4: Invalid or unknown-app payloads are rejected with no mutation**
    - Random unknown identifiers and random invalid payloads; assert reject, DB unchanged, and error identifies the unknown app or the invalid fields
    - **Validates: Requirements 2.2, 3.3, 3.4**

  - [x]* 5.4 Write unit tests for `FindingsIngestService`
    - Reject-unknown-app, reject-invalid-payload (no mutation), successful transactional replace, and out-of-set status rejection
    - _Requirements: 2.2, 3.3, 3.4_

- [x] 6. Checkpoint - summary, DTO, and ingestion service
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Implement the findings receiving endpoint and command stub
  - [x] 7.1 Register the `apps:write` scope
    - Add `apps:write` to the MCP allowed-scope set (`Passport::tokensCan`) alongside the existing MCP scopes
    - _Requirements: 3.5_

  - [x] 7.2 Implement `App\Http\Controllers\Api\FindingsController` and route
    - Add `POST /mcp/apps/{identifier}/findings` to the MCP route group so it inherits `EnsureHttps`, throttle, `auth:api`, and `CheckMcpScope` gated on `apps:write`
    - Validate the request payload, delegate to `FindingsIngestService`, map unknown-app to an error naming the identifier and validation failure to 422, return counts-only summary on success
    - Write one `McpAuditLogger` entry per outcome (denied/error/success) with `tool_name`, `scope_used = apps:write`, and non-sensitive context (app id, counts) only
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.7_

  - [x] 7.3 Implement `App\Console\Commands\IngestAppFindings` stub
    - Signature e.g. `apps:ingest-findings {app} {--file=}` following the `StripeSyncInvoices` command shape (begin/complete logging, summary counts)
    - Delegate to `FindingsIngestService`; do not register in the scheduler; reference no GitHub/ChatGPT/OpenAI logic
    - _Requirements: 3.1, 3.6_

  - [x]* 7.4 Write property test for endpoint authorization and audit
    - **Property 5: Findings endpoint authorization and audit**
    - Random token scope sets and request sequences; assert process iff valid token carries `apps:write`, no persistence on deny, exactly one audit row per request with matching outcome
    - **Validates: Requirements 3.5, 3.7**

  - [x]* 7.5 Write smoke/structural tests for scope and no-scan guarantees
    - Assert `apps:write` is in the allowed scope set and the route sits inside the MCP middleware group; assert the controller, command, and ingest service reference no GitHub/OpenAI client and the command is not scheduler-registered
    - _Requirements: 3.5, 3.6_

- [x] 8. Checkpoint - findings endpoint and command
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Implement the admin technical view
  - [x] 9.1 Implement web `App\Http\Controllers\Admin\CustomerAppController`
    - Resourceful controller mirroring `Admin\ServiceController`: route-model binding, `admin.customer-apps.*` Blade views, redirect-with-flash, admin middleware group
    - `create`/`store(Service)` build a `CustomerApp` against an existing Service; reject with a "service already has a linked app" message when `$service->customerApp()->exists()`; validate `repo_url` (nullable URL), `hosting_location`, `codebase_info`
    - `show(CustomerApp)` eager-loads `service.customer`, `packages`, `findings` and renders the full technical view plus the linked service name/status/billing
    - `edit`/`update` edit only `repo_url`, `hosting_location`, `codebase_info`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 1.4_

  - [-] 9.2 Create `admin.customer-apps.*` Blade views
    - Show view renders repo/hosting/codebase + all packages + all findings + service name/status/billing; create/edit forms for the three editable fields
    - _Requirements: 4.1, 4.2, 4.3_

  - [x] 9.3 Implement API `App\Http\Controllers\Api\Admin\CustomerAppController`
    - Follow the `Api\Admin\ServiceController` JSON shape (`{ data, meta }`, `min($perPage, 100)` cap)
    - Build an explicit array in `show()` rather than `$model->toArray()`
    - _Requirements: 4.1, 4.4_

  - [-]* 9.4 Write property test for one-to-one invariant
    - **Property 1: At most one CustomerApp per Service**
    - Random sequences of create attempts against a service; assert at most one app persists, first wins, subsequent creates rejected and reported
    - **Validates: Requirements 1.3, 1.4**

  - [-]* 9.5 Write admin feature tests
    - Admin show renders repo/hosting/codebase + all packages + all findings + service name/status/billing; create/edit persist the expected fields; duplicate create is rejected
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [ ] 10. Implement the customer portal view and ownership scoping
  - [~] 10.1 Wire the portal app summary into the `Portal\ServiceController` pattern
    - Reuse the existing `company_id` ownership guard (`if ($service->company_id !== $request->user()->company_id) abort(403);`)
    - Build the view data via `CustomerAppSummaryDto::fromService($service)` and render a Blade view with only the four DTO keys and no technical slot
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [ ]* 10.2 Write property test for company_id scoping
    - **Property 8: Customer app access is confined to the owning company**
    - Random services and users; assert access iff `company_id` matches, deny otherwise
    - **Validates: Requirements 5.5**

  - [ ]* 10.3 Write portal feature test
    - Assert the portal app view renders only name/status/billing/summary and exposes no technical field
    - _Requirements: 5.1, 5.6_

- [~] 11. Final checkpoint - full integration
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional (tests) and can be skipped for a faster MVP, but they validate the eight correctness properties and are strongly recommended.
- Each task references specific requirement sub-clauses for traceability.
- Property-based tests use `innmind/black-box` (or PHPUnit data-provider generation), run ≥ 100 generated cases, and each carries the tag `// Feature: customer-app-services, Property {number}: {property_text}`.
- The feature assumes the Passport `auth:api` guard, `CheckMcpScope`, and `McpAuditLogger` from `chatgpt-mcp-integration` exist; this spec adds only the `apps:write` scope and the new route.
- No GitHub/ChatGPT/OpenAI scanning logic is written, and the Artisan command is not scheduler-registered (Req 3.6).

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["1.4", "2.1"] },
    { "id": 2, "tasks": ["2.2"] },
    { "id": 3, "tasks": ["2.3", "4.1", "5.1"] },
    { "id": 4, "tasks": ["2.4", "2.5", "4.2", "4.3", "4.4", "5.2", "5.3", "5.4"] },
    { "id": 5, "tasks": ["4.5", "4.6", "7.1", "7.2", "7.3"] },
    { "id": 6, "tasks": ["7.4", "7.5", "9.1", "9.3"] },
    { "id": 7, "tasks": ["9.2", "9.4", "9.5", "10.1"] },
    { "id": 8, "tasks": ["10.2", "10.3"] }
  ]
}
```
