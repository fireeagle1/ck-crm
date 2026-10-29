# Implementation Plan: ChatGPT MCP Integration

## Overview

This plan builds the MCP integration incrementally and test-first, in the existing Laravel 13 / PHP 8.3 CRM. The implementation language is PHP (Laravel), as specified throughout the design — no language selection is required.

The ordering follows the design's dependency structure: first the additive data-layer and identity changes (migrations, bot user, `is_login_disabled`), then the shared `TicketService` extraction (preserving admin behaviour), then DTO transformers and read query services, then the OAuth/Passport authorization server, then the MCP transport + registry + scope middleware, then the write tools, then auditing and rate limiting, and finally end-to-end wiring and non-disruption regression. Property-based tests (`innmind/black-box`, minimum 100 generated cases each, tagged `// Feature: chatgpt-mcp-integration, Property {n}: {text}`) are placed next to the code they validate so failures surface early.

Property-based test sub-tasks and other test sub-tasks are marked optional with `*`.

## Tasks

- [x] 1. Additive data-layer foundation (migrations, models, bot identity)
  - [x] 1.1 Add `is_login_disabled` to users and enforce it at login
    - Create a migration adding nullable/`default(false)` boolean `is_login_disabled` to the `users` table
    - Mark `is_login_disabled` as a guarded/non-fillable, cast-as-boolean attribute on the `User` model (mirroring `is_admin`), add to `$casts`
    - Update the web login path and the Sanctum `AuthController::login` to reject authentication when `is_login_disabled = true`
    - _Requirements: 2.5_

  - [ ]* 1.2 Write example tests for the non-login guard
    - Assert a user with `is_login_disabled = true` is rejected by web login and by the Sanctum admin API login
    - Assert an ordinary user is unaffected (regression)
    - _Requirements: 2.5_

  - [x] 1.3 Create the `mcp_audit_logs` table and `McpAuditLog` model
    - Migration with columns: `id`, `access_token_id` (string, nullable), `mcp_identity` (string), `tool_name` (string, nullable), `scope_used` (string, nullable), `outcome` (enum success/denied/error), `reason` (string, nullable), `context` (json, nullable), `created_at`
    - `McpAuditLog` model with `$fillable` limited to those columns and no sensitive casts
    - _Requirements: 13.1, 13.3_

  - [x] 1.4 Add nullable `source` column to ticket_replies and ticket_activities
    - Migration adding nullable `source` (`'admin'`/`'mcp'`) to both tables; additive and nullable so existing admin writes are unaffected
    - Add `source` to the `$fillable` of `TicketReply` and `TicketActivity`
    - _Requirements: 14.2_

  - [x] 1.5 Create the dedicated MCP bot user and a cached resolver
    - Seeder/factory creating the bot user: `first_name = 'MCP'`, `last_name = 'Assistant'`, `is_admin = false`, `is_login_disabled = true`, no usable password, email `mcp-bot@system.local`
    - A resolver service (cached) that returns the bot `User` for injection as the MCP write actor
    - _Requirements: 2.5, 10.2, 11.5_

- [x] 2. DTO transformers (data-minimisation enforcement point)
  - [x] 2.1 Implement read DTO transformer classes
    - `CustomerDto`, `InvoiceDto`, `ProjectDto`, `ServiceDto`, `TicketSummaryDto`, `TicketDetailDto`, `TicketReplyDto`, `TicketActivityDto`
    - Each exposes `fromModel(Model): array` returning a fixed associative array with an explicit field allow-list; never spread model attributes or call `->toArray()`
    - Customer: address/contact fields only, never traverse `users()`; exclude `stripe_customer_id`
    - Invoice: include `stripe_hosted_url`, exclude `stripe_invoice_id`
    - Service: exclude `cpanel_username`, `stripe_subscription_id`
    - Ticket reply/activity: expose `user_name` (full name), never the user row; include both internal and non-internal replies in detail
    - _Requirements: 4.1, 4.4, 5.1, 5.4, 6.2, 7.1, 7.4, 8.1, 8.3, 12.1, 12.2, 12.3_

  - [ ]* 2.2 Write property test for DTO field safety
    - **Property 4: Tool output contains exactly the allow-listed fields and no security field**
    - Generate models with sensitive attributes populated; run every read DTO; assert keys ⊆ allow-list and ∩ forbidden-set = ∅ recursively (including nested replies/activities)
    - **Validates: Requirements 4.1, 4.4, 5.1, 6.2, 7.1, 7.4, 8.1, 8.3, 12.1, 12.2, 12.3**

  - [ ]* 2.3 Write unit tests for each DTO's exact expected key set
    - Concrete per-domain assertions pinning the exact allow-listed keys
    - _Requirements: 4.1, 5.1, 6.2, 7.1, 8.1_

- [x] 3. Read query services and read tools (per domain)
  - [x] 3.1 Implement a shared pagination/page-size cap helper
    - Compute effective page size as `min($requested, 100)` bounded to a positive value, matching existing controllers
    - _Requirements: 4.3, 5.3, 6.4, 7.3, 8.4_

  - [x] 3.2 Implement Customers query service and read tools
    - `get_customer(company_id)`, `search_customers(name, page, per_page)`; map rows through `CustomerDto`; apply cap helper
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [x] 3.3 Implement Invoices query service and read tools
    - `get_invoice(invoice_id)`, `list_invoices(company_id?, invoice_status?, page, per_page)`; `scopeCollectable` default; map through `InvoiceDto`; apply cap helper
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 3.4 Implement Projects query service and read tools (new read surface)
    - `get_project(id)`, `list_projects(company_id?, status?, page, per_page)`; expose `progress_percentage`; constrain `status ∈ Project::STATUSES`; map through `ProjectDto`; apply cap helper
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [x] 3.5 Implement Services query service and read tools
    - `get_service(service_id)`, `list_services(company_id?, status?, page, per_page)`; map through `ServiceDto`; apply cap helper
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

  - [x] 3.6 Implement Tickets read query service and read tools
    - `search_tickets(status?, priority?, company_id?, search?, page, per_page)` → summaries via `TicketSummaryDto`; `get_ticket(ticket_id)` → detail via `TicketDetailDto` including description, customer/service/asset references, internal + non-internal replies, activity history; apply cap helper
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [ ]* 3.7 Write property test for filtered reads
    - **Property 5: Filtered reads return only matching records**
    - Random datasets + `company_id`/status/priority filters across all five domains; assert every result satisfies all supplied predicates
    - **Validates: Requirements 4.2, 5.2, 6.3, 7.2, 8.2**

  - [ ]* 3.8 Write property test for pagination cap
    - **Property 6: Pagination page size is capped**
    - Random `per_page` including >100, 0, negative across all five read tools; assert effective size = `min(requested, 100)` bounded positive and page length never exceeds it
    - **Validates: Requirements 4.3, 5.3, 6.4, 7.3, 8.4**

- [x] 4. Checkpoint — data layer, DTOs, and reads
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Shared TicketService extraction (behaviour-preserving)
  - [x] 5.1 Extract write logic into `TicketService`
    - Create `final class TicketService` with `create(array $data, User $actor, bool $notifyCustomer): Ticket`, `update(Ticket $ticket, array $data, User $actor): Ticket`, `reply(Ticket $ticket, array $data, User $actor, bool $isInternal): TicketReply`
    - Move logic verbatim from `App\Http\Controllers\Api\Admin\TicketController`; replace `request->user()` with the `$actor` parameter; set `source` from actor context
    - Preserve: create sets `status = 'Open'`, conditional `emails.ticket-opened`, always `NewTicketNotification` to admins; update validates enums, diffs `TicketActivity` rows, emails on status change, `CriticalTicketNotification` on Critical; reply validates `body`/`is_internal`, sets `first_replied_at` once, reopens Closed tickets with `status_changed` activity, emails only for non-internal
    - _Requirements: 9.3, 9.4, 10.2, 10.3, 10.4, 11.2, 11.3, 11.4, 11.5, 14.2_

  - [x] 5.2 Refactor admin TicketController to delegate to TicketService
    - Admin controller calls `TicketService` passing `request->user()`; keep JSON responses and status codes identical
    - _Requirements: 14.1, 14.2_

  - [ ]* 5.3 Write unit tests for TicketService branch behaviour
    - Cover reopen-on-closed, `first_replied_at` set-once, internal-vs-non-internal email gating, Critical→admin notification, each `TicketActivity` type
    - _Requirements: 9.3, 10.2, 10.3, 10.4, 11.2, 11.3, 11.4_

  - [ ]* 5.4 Write non-disruption regression test for the admin API
    - Assert existing admin `TicketController` JSON responses and status codes are unchanged after extraction; existing admin test suite passes unchanged
    - _Requirements: 14.1, 14.2_

- [x] 6. OAuth 2.1 authorization server (Laravel Passport)
  - [x] 6.1 Install and configure Passport as a new api guard
    - Add `laravel/passport`; publish/run Passport migrations; add a new `api` guard in `config/auth.php` leaving `web` and `sanctum` untouched; document cPanel no-SSH install steps (composer.lock commit, `migrate --force`, `passport:keys`, key deployment) in `DEPLOYMENT.md`
    - _Requirements: 1.1, 14.1, 14.3_

  - [x] 6.2 Register MCP scopes and bounded token lifetimes
    - `Passport::tokensCan([...])` limited to {`customers:read`, `invoices:read`, `projects:read`, `services:read`, `tickets:read`, `tickets:write`}; configure short-lived access tokens and bounded refresh tokens in the Passport service provider; enforce PKCE
    - _Requirements: 1.2, 1.3, 1.7_

  - [x] 6.3 Create the PKCE public client and admin token management
    - Provide a command/step to create the `mcp_client` public (no-secret) client; add admin-only `GET /admin/mcp/tokens` (list id/scopes/created/last_used/expires, no token values) and `POST /admin/mcp/tokens/{id}/revoke`
    - _Requirements: 1.2, 1.5_

  - [ ]* 6.4 Write property test for issued token scope minimality
    - **Property 3: Issued token scopes are minimal and in-set**
    - Random requested-scope lists including junk; assert issued scopes = requested ∩ allowed-set and contain nothing outside it
    - **Validates: Requirements 1.3**

  - [ ]* 6.5 Write OAuth flow integration + revocation tests
    - authorize+token with PKCE, refresh, and revocation (revoked token → `401` on next call); smoke-assert bounded TTL + refresh configured
    - _Requirements: 1.1, 1.2, 1.5, 1.7_

- [x] 7. Tool Registry and scope-checking middleware
  - [x] 7.1 Implement the Tool Registry allow-list
    - Central map: tool name → `{ description, inputSchema, requiredScope, handler }` for all read tools and `create_ticket`/`update_ticket`/`reply_ticket`; single source of truth for advertisement and dispatch; any tool not registered is denied by default
    - Descriptions distinguish read tools from the three ticket write tools
    - _Requirements: 2.2, 2.3, 3.1, 3.3_

  - [x] 7.2 Implement `CheckMcpScope` middleware
    - Resolve the invoked tool, look up its required scope, authorize using only `$token->can($scope)` (never `is_admin`); on missing scope or unregistered tool, return an MCP authorization/not-found error, execute no side effect, and write a denied audit entry
    - _Requirements: 1.4, 2.1, 2.3, 2.4, 13.4_

  - [ ]* 7.3 Write property test for scope enforcement
    - **Property 1: Scope enforcement is the sole authorization gate**
    - Random `(toolName, tokenScopes, is_admin)`; assert permit ⇔ (registered ∧ scope present), decision independent of `is_admin`, handler not invoked and no side effect on deny
    - **Validates: Requirements 1.4, 2.1, 2.2, 2.3, 2.4**

- [x] 8. MCP transport, route group, and tool discovery
  - [x] 8.1 Add the MCP route group with the middleware stack
    - Append an `mcp` prefix group in `routes/api.php` after the admin group with middleware order `EnsureHttps` → `throttle:mcp` → `auth:api` → (per-tool `CheckMcpScope`)
    - Implement `EnsureHttps` honouring `X-Forwarded-Proto` behind the cPanel proxy
    - _Requirements: 1.6, 14.1, 14.3_

  - [x] 8.2 Implement McpController transport (streamable HTTP JSON-RPC)
    - `initialize` handshake returning server capabilities; `tools/list` returning name/description/schema filtered to the token's scopes; `tools/call` validating params against the tool schema, running `CheckMcpScope`, dispatching via the registry, and returning MCP results or structured errors; use chunked/SSE responses compatible with OpenAI's remote connector (no stdio process)
    - _Requirements: 3.1, 3.2, 3.3, 14.3_

  - [ ]* 8.3 Write property test for tool advertisement
    - **Property 2: Tool advertisement matches token scopes**
    - Random scope subsets; assert advertised set equals scope-matched registered tools and each advertised tool has non-empty name, description, and valid input schema
    - **Validates: Requirements 3.1, 3.2**

  - [ ]* 8.4 Write example tests for HTTPS enforcement and tool descriptions
    - http rejected / https allowed (honouring `X-Forwarded-Proto`); snapshot assertions distinguishing read vs write tool descriptions
    - _Requirements: 1.6, 3.3_

- [x] 9. Checkpoint — authorization, transport, and discovery
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Ticket write tools
  - [x] 10.1 Implement `create_ticket` tool
    - Validate against the admin create rules (`company_id` exists, `subject`, `description`, `priority ∈ {Low,Normal,High,Critical}`, `ticket_type ∈ {Incident,Service Request}`, optional `user_id`/`asset_id`/`service_id`/`request_category`); call `TicketService::create` with the bot actor and `notify_customer` flag; on failure return a structured error naming invalid fields and perform no write; on success return `ticket_id` + summary
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [x] 10.2 Implement `update_ticket` tool
    - Validate against the admin update rules (status/priority/type enums, optional `asset_id`/`service_id`/`user_id`/`due_at`); call `TicketService::update` with the bot actor; on validation failure or missing ticket return a structured error and perform no mutation
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [x] 10.3 Implement `reply_ticket` tool
    - Validate required `body` + optional `is_internal`; call `TicketService::reply` with the bot actor; on failure return a structured error and perform no write
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

  - [ ]* 10.4 Write property test for write validation parity
    - **Property 7: Write validation parity and no-mutation-on-invalid**
    - Random create/update/reply payloads; assert MCP accepts ⇔ admin validation rules accept; on reject, a structured field-naming error is returned and tickets/replies/activities are unchanged
    - **Validates: Requirements 9.1, 9.2, 10.1, 10.5, 11.1**

  - [ ]* 10.5 Write model-based property test for write side-effect parity
    - **Property 8: Write side-effect parity with the admin controller (model-based)**
    - `Notification::fake()` + `Mail::fake()`; apply the same op via the admin controller (reference) and via the MCP write tool; assert identical ticket state, identical `TicketActivity` rows (modulo actor/source), identical `first_replied_at`/reopen behaviour, identical dispatched notifications/emails, and that create returns the persisted `ticket_id`
    - **Validates: Requirements 9.3, 9.4, 9.5, 10.2, 10.3, 10.4, 11.2, 11.3, 11.4, 11.5, 14.2**

  - [ ]* 10.6 Write Queue::fake write test for non-blocking side effects
    - Assert notifications/mail are dispatched to the queue and the write tool returns the ticket without blocking, honouring the existing queue configuration
    - _Requirements: 14.4_

- [x] 11. Audit logging and rate limiting
  - [x] 11.1 Implement `McpAuditLogger` and wire it into the request path
    - Write exactly one `mcp_audit_logs` row per tool call and per denied authorization attempt; record token/client reference (token id, never the value), identity, tool name, scope used/required, timestamp, outcome, and short denial reason; store only non-sensitive metadata (e.g. `ticket_id`, `company_id` filter), never security fields or full payloads
    - Wire logging into `CheckMcpScope` (denials) and the registry dispatch (success/error)
    - _Requirements: 13.1, 13.3, 13.4_

  - [x] 11.2 Register the `mcp` named rate limiter
    - Per-token limiter (e.g. 60/min, configurable) returning standard `429 Too Many Requests` with `Retry-After` / `X-RateLimit-*` headers
    - _Requirements: 13.2_

  - [ ]* 11.3 Write property test for audit completeness
    - **Property 9: Audit completeness**
    - Random call sequences (successes, errors, denials); assert exactly one audit row per call with matching `outcome`, `tool_name` (when resolved), `scope_used`, identity reference, and timestamp
    - **Validates: Requirements 13.1, 13.4**

  - [ ]* 11.4 Write property test for audit content safety
    - **Property 10: Audit content safety**
    - Tool calls whose input carries sensitive values; assert the stored row contains no forbidden security keys/values and does not store the full request payload
    - **Validates: Requirements 13.3**

  - [ ]* 11.5 Write rate-limiting integration test
    - Fire limit+1 requests; assert the final response is `429` with the expected headers
    - _Requirements: 13.2_

- [x] 12. Final integration and wiring
  - [x] 12.1 Wire the registry handlers to read query services and write tools
    - Connect every registered tool name to its concrete handler (read query service or write tool) so `tools/call` dispatches end-to-end through the full middleware stack with no orphaned code
    - _Requirements: 2.3, 3.1, 9.5, 10.2, 11.5_

  - [x] 12.2 Document cPanel deployment and manual verification
    - In `DEPLOYMENT.md`: Passport install/keys without SSH, queue (database driver / cron worker), and manual verification that MCP routes are request/response only (no persistent process)
    - _Requirements: 14.3, 14.4_

- [x] 13. Final checkpoint — full suite green
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional (property, unit, integration, example tests) and can be skipped for a faster MVP, but they validate the 10 correctness properties and the non-disruption guarantee.
- Each task references specific requirement sub-clauses for traceability; every one of the 14 requirements and all 10 correctness properties is covered.
- Property-based tests use `innmind/black-box` with a minimum of 100 generated cases each and are tagged `// Feature: chatgpt-mcp-integration, Property {n}: {text}`.
- The design uses PHP/Laravel throughout, so all tasks are implemented in PHP.
- The `TicketService` extraction (task 5) must preserve the admin API's observable behaviour; task 5.2 and the regression test (5.4) guard Requirement 14.
- The deployment model is cPanel no-SSH with cron-based queue draining; no long-lived process is introduced.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.3", "1.4", "2.1", "3.1"] },
    { "id": 1, "tasks": ["1.2", "1.5", "2.2", "2.3", "6.1"] },
    { "id": 2, "tasks": ["3.2", "3.3", "3.4", "3.5", "3.6", "5.1", "6.2"] },
    { "id": 3, "tasks": ["3.7", "3.8", "5.2", "5.3", "6.3", "7.1"] },
    { "id": 4, "tasks": ["5.4", "6.4", "6.5", "7.2", "8.1"] },
    { "id": 5, "tasks": ["7.3", "8.2", "10.1", "10.2", "10.3"] },
    { "id": 6, "tasks": ["8.3", "8.4", "10.4", "11.1", "11.2"] },
    { "id": 7, "tasks": ["10.5", "10.6", "11.3", "11.4", "11.5", "12.1"] },
    { "id": 8, "tasks": ["12.2"] }
  ]
}
```
