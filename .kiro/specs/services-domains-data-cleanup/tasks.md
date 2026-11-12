# Implementation Plan: Services, Domains and Data Cleanup

## Overview

The plan follows the phase order of the requirements and design: data model, shop and webhook, WHM, domains and eNom, web apps, admin UI. It starts with a baseline test run and the test and factory groundwork. The language is PHP 8.3+ on Laravel 13 with PHPUnit. There is no property-testing library, so property tests are seeded PHPUnit loops using the `Tests\Support\Gen` helper from task 1.2.

Each implementation task includes the tests that prove it. Pure property tests and optional hardening are separate sub-tasks marked with `*`. Every phase ends with a checkpoint task.

Open decisions from the design (Open Questions 1 to 5) use the safest default below. Each default is repeated in the task that applies it. To change one, edit that task before it is executed.

| # | Open decision | Safest default used in these tasks | Applied in |
|---|---|---|---|
| 1 | Reselling a domain held by a Cancelled service | Do not reuse or reactivate the cancelled service. The attempt fails with a validation error naming the existing service, and shop fulfilment holds the order for an admin. | 4.5, 8.8 |
| 2 | `ReviewWizardController::moveToDomain` | Link the Service to the Domain and never delete the Service. | 8.17 |
| 3 | Admin order cancellation restocking | Restock through the shared service, but only items with a recorded `stock_reserved_quantity`, so legacy orders are never restocked by guesswork. | 4.17 |
| 4 | Abandoned hosting-only order expiry | 48 hours through `shop.abandoned_hosting_hours`. Only never-provisioned Pending services are deleted. | 4.1, 4.15 |
| 5 | `whm_default_package` setting | Keep the setting and its field on the WHM settings page, mark it as unused for provisioning, and do not delete the stored value. | 6.21 |

## Tasks

- [x] 1. Baseline test run and test groundwork
  - [x] 1.1 Run the full test suite and record the baseline
    - Run `php artisan test` and list every failing test with its failure reason in `.kiro/specs/services-domains-data-cleanup/baseline-failures.md`.
    - Confirm the two failures the design expects: `CheckoutServiceTest` (three constructor arguments against a four-argument constructor) and `FulfilmentServiceTest` (rental stock decrement assertions).
    - Make no code changes. Later checkpoints compare against this list so old failures are not treated as regressions.
    - _Requirements: all (regression baseline)_

  - [x] 1.2 Add the seeded generator helper `Tests\Support\Gen`
    - Provide seeded generators for strings, domains with casing, `www.`, trailing dot and Unicode variants, statuses, quantities and findings payloads.
    - Include the seed in assertion messages so failures reproduce.
    - Add a small self-test that the same seed gives the same output.
    - _Requirements: supports properties 1 to 29_

  - [x] 1.3 Add `ServiceFactory` and `DomainFactory`
    - `ServiceFactory` states: `webHosting`, `pending`, `withCpanel`, `provisioning(status)`.
    - `DomainFactory` states: `enomSourced`, `unassigned`, `missingFromEnom`. Add states that need later columns only when those columns exist, or guard them with `Schema::hasColumn`.
    - Add a factory smoke test that creates each state.
    - _Requirements: supports 1.x, 2.x, 3.x, 13.x, 15.x_

  - [x] 1.4 Add `OrderFactory`, `OrderItemFactory`, `CustomerAppFactory` and `SecurityFindingFactory`
    - Cover hosting and one-off order items, and apps with findings in each status.
    - Add a factory smoke test.
    - _Requirements: supports 3.x, 4.x, 16.x, 17.x_

  - [x] 1.5 Add fake Stripe and fake WHM test doubles
    - `FakeStripeService`: records calls, can fail at a chosen step, records `DB::transactionLevel()` at each call, records created and cancelled object ids.
    - `FakeWhmService`: configurable accounts, failures, and call counters.
    - Add tests for the doubles themselves.
    - _Requirements: supports 5.x, 6.x, 8.x, 9.x_

  - [ ]* 1.6 Add optional MySQL test configuration `phpunit.mysql.xml`
    - Create a `mysql` test group and a config that points at a local MySQL database.
    - Document the run command in a comment at the top of the file.
    - Use it for unique-index NULL behaviour and migration preflight tests.
    - _Requirements: 2.1, 2.2, 2.4 (MySQL validation)_

- [x] 2. Phase 1: Data model
  - [x] 2.1 Create the enums
    - Add `ServiceType`, `ServiceStatus`, `ProvisioningStatus` and `DomainSyncStatus` in `app/Enums` with `values()`.
    - Add `fromLoose()` to `ServiceType` and `ServiceStatus` with alias constants (`hosting`, `pending`, `payment_failed`, `cancelled`).
    - Do not add model casts, so existing string comparisons keep working.
    - Unit tests: exact labels, alias mapping, whitespace and case handling, null for unknown.
    - _Requirements: 1.1, 1.2, 1.9, 1.10_

  - [ ]* 2.2 Write property test for label mapping
    - **Property 4: Label mapping**
    - **Validates: Requirements 1.9, 1.10, 1.11**

  - [x] 2.3 Implement `DomainNormaliser` and `InvalidDomainException`
    - Add `App\Support\DomainNormaliser` with `normalise`, `tryNormalise` and `toHttpsUrl`.
    - Order: trim, lowercase, strip trailing dots, strip leading `www.` repeatedly, IDNA to ASCII, then validate length and labels.
    - Unit tests: known examples, idempotence on canonical input, Unicode to punycode, rejection cases.
    - _Requirements: 11.1, 11.2, 11.3_

  - [ ]* 2.4 Write property tests for the normaliser
    - **Property 1: Domain canonicalisation**
    - **Property 2: Invalid domains are rejected**
    - **Validates: Requirements 11.1, 11.2, 11.3**

  - [x] 2.5 Add the `ValidDomain` rule and `NormalisesDomain` request trait
    - The trait merges the canonical value in `prepareForValidation()`.
    - Unit tests: rule passes canonical and variant input, fails invalid input with a field error.
    - _Requirements: 11.3, 11.4_

  - [x] 2.6 Create the shared `ServiceRequest`
    - One class for web and API, branching on the route `service` parameter.
    - Rules: `Rule::enum` for `service_type` and `status`, `ValidDomain`, domain and cpanel_username unique rules that ignore the current service, and the optional web-only and API-only fields.
    - Unit tests for each rule and for create against update behaviour.
    - _Requirements: 1.3, 1.4, 1.5, 18.3_

  - [x] 2.7 Use `ServiceRequest` in `Admin\ServiceController` store and update
    - Replace inline validation. Keep the existing behaviour otherwise.
    - Feature tests: invalid type or status is rejected, canonical domain is saved, variants are treated as duplicates.
    - _Requirements: 1.3, 11.5_

  - [x] 2.8 Use `ServiceRequest` in `Api\Admin\ServiceController` store and update
    - Replace inline validation. Add no routes.
    - Tests call the controller methods directly and assert the same verdict as the web controller.
    - _Requirements: 1.4, 1.5, 11.6_

  - [ ]* 2.9 Write property test for shared service validation
    - **Property 7: Shared service validation**
    - **Validates: Requirements 1.3, 1.4, 1.5**

  - [x] 2.10 Add migrations M1 and M1b for services and order_items columns
    - M1: nullable `provisioning_status`, `provisioning_error`, `provisioning_updated_at` and `domain_id` (FK to `domains.id`, `nullOnDelete`) on `services`.
    - M1b: nullable `domain_id` FK on `order_items`.
    - Guard with `hasColumn` so re-runs are safe. Implement `down()`.
    - Schema tests for the columns and FK behaviour.
    - _Requirements: 2.5, 2.6_

  - [x] 2.11 Add migration M2 for the change-log table and model
    - Table `service_data_cleanup_changes` with `migration`, `table_name`, `row_id`, `column_name`, `old_value`, `new_value`, `created_at`.
    - Add a small model and a `record()` helper.
    - Schema test.
    - _Requirements: 1.11, 2.4 (supports reversible backfill)_

  - [x] 2.12 Implement `ServiceDomainBackfill::plan()` (read-only)
    - Cover: trim and empty string to NULL, `cpanel_username` lowercase, label mapping via `fromLoose()`, canonicalisation of `domains`, `services` and `order_items` names, services-to-domain matching, and `provisioning_status = succeeded` for Web Hosting services that already have a `cpanel_username`.
    - Report unmatched labels, invalid domains and canonicalisation collisions. Read only columns that exist before the migrations.
    - Tests on seeded data for each category, including no writes.
    - _Requirements: 1.9, 1.10, 1.11, 2.3, 11.9, 12.1_

  - [x] 2.13 Implement `ServiceDomainBackfill::apply()` with change log and idempotence
    - Apply the plan in chunks of 200 inside transactions and log every change.
    - Set `services.domain_id` where the canonical names match. Leave unmatched values unchanged and log row id and value.
    - Abort with logged details on canonicalisation collisions between domains.
    - Copying `enom_domain_name_id` waits for task 8.2, because that column is added in Phase 4.
    - Tests: results match the plan, second run changes nothing, unmatched rows stay unchanged and are logged.
    - _Requirements: 1.9, 1.10, 1.11, 2.3, 11.9, 12.1_

  - [x] 2.14 Add migration M3 to run the backfill with a reversible `down()`
    - `up()` calls `apply()`. `down()` replays the change log in reverse.
    - Test up, down and up again restores original values.
    - _Requirements: 1.9, 1.10, 1.11, 2.3, 11.9, 12.1_

  - [x] 2.15 Implement `UniqueIndexPreflight` and migration M4 (unique indexes)
    - Preflight runs `GROUP BY ... HAVING COUNT(*) > 1` for `cpanel_username` and `domain_name`, logs each duplicate with service ids, and throws before any index is created.
    - M4 adds `unique(cpanel_username)` and `unique(domain_name)`, guarded with `hasIndex`.
    - Tests: duplicates abort with the log, many NULLs pass, and the indexes exist afterwards.
    - _Requirements: 2.1, 2.2, 2.4_

  - [ ]* 2.16 Write property test for backfill
    - **Property 5: Backfill is canonical, linking and idempotent**
    - For `enom_domain_name_id` this property is completed in task 8.2.
    - **Validates: Requirements 2.3, 11.9, 12.1**

  - [ ]* 2.17 Write property test for duplicate preflight
    - **Property 6: Duplicate preflight**
    - **Validates: Requirements 2.4**

  - [x] 2.18 Add the data migration dry-run command `services:normalise-data`
    - Default mode is read-only and prints counts per action, unmatched labels, empty-string conversions, would-be duplicates and canonicalisation collisions. `--apply` runs the backfill.
    - Log runs through `ScheduledTaskLog`.
    - Add the command to `SettingsController::runnableCommands`, dry-run only from the UI, so it runs without SSH.
    - Tests: the default run writes nothing, `--apply` matches `apply()`, the UI whitelist allows the dry run only.
    - _Requirements: 1.9, 1.10, 1.11, 2.3, 2.4_

- [x] 3. Checkpoint - Phase 1 complete
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

- [x] 4. Phase 2: Shop and webhook
  - [x] 4.1 Add configuration for currency and abandoned hosting orders
    - Add `services.stripe.currency` (`STRIPE_CURRENCY`, default `gbp`) and `shop.abandoned_hosting_hours`.
    - Open decision 4 default: 48 hours. Change the config default to change it.
    - Add the `.env.example` entry for `STRIPE_CURRENCY`.
    - Test config defaults.
    - _Requirements: 5.6_

  - [x] 4.2 Add migration M5 for order-item linkage and stock reservation
    - `services.order_item_id` (nullable FK to `order_items.id`, `nullOnDelete`), `order_items.stock_reserved_quantity` (unsigned int, nullable).
    - Backfill `order_item_id` from `order_items.service_id`, backfill `stock_reserved_quantity` only for one-off items on orders still `payment_status = pending`, preflight duplicates, then add `unique(order_item_id)`.
    - Tests: the unique index allows multiple NULLs, a duplicate aborts the migration, the backfill is idempotent.
    - _Requirements: 3.1, 3.2_

  - [x] 4.3 Implement `StockService`
    - `reserve()` locks the product row, checks stock, decrements, and records `stock_reserved_quantity`. `restoreForOrder()` restocks each item once and sets the reserved quantity to 0.
    - Unlimited stock reserves 0 and never restocks. Both methods require a caller transaction.
    - Tests: quantity greater than 1, insufficient stock, unlimited stock, restore called twice.
    - _Requirements: 4.1, 4.3, 4.5, 4.6_

  - [x] 4.4 Implement `DomainLinker::findOrCreate()`
    - Look up by canonical name including trashed rows (use `withTrashed()` only when the model uses `SoftDeletes`, which arrives in task 8.3; plain lookup before that). Create a Domain with the customer's `company_id`, `registrar` null and `status = active` when missing. Never restore a trashed row.
    - Because the `domains.status` column arrives in task 8.1, set it only when `Schema::hasColumn('domains', 'status')`.
    - Tests: existing domain reused, missing domain created, name variants map to one row.
    - _Requirements: 3.6, 12.2_

  - [x] 4.5 Implement `HostingServiceCreator` and `DomainAlreadyInUseException`
    - `forOrderItem()` runs in a transaction, returns the existing Service for the `order_item_id`, otherwise creates one with `Web Hosting`, `Pending`, canonical domain, `domain_id` and `order_item_id`, then updates the order item. Do not write `whm_package` (that column arrives in task 6.10).
    - A unique violation returns the existing Service for the item or throws `DomainAlreadyInUseException`.
    - Open decision 1 default: a domain held by another service (including a Cancelled one) is never reused. The exception names the existing service.
    - Tests: repeated calls return one Service, domain collision, canonical domain stored.
    - _Requirements: 1.6, 3.1, 3.3, 3.5, 3.6_

  - [ ]* 4.6 Write property test for one service per hosting order item
    - **Property 8: One service per hosting order item**
    - **Validates: Requirements 1.6, 3.1, 3.3, 3.4, 3.6, 12.2**

  - [x] 4.7 Implement `OrderCancellationService`
    - Methods: `failOrder()`, `expire()`, `cancel()`. Each restores stock through `StockService`, cancels bookings, releases assets, and deletes never-provisioned Pending hosting services created by the order.
    - Order states: `failed` and `cancelled` for failure, `expired` for abandoned, `cancelled` for admin cancel. Service cancellation uses `ServiceStatus::Cancelled`.
    - After commit, cancel or expire tracked Stripe objects on a best-effort basis. Append compensation failures to `orders.admin_notes`.
    - Tests: restock happens once for repeated calls, Stripe cancel failures are logged and noted.
    - _Requirements: 4.4, 4.5, 5.3, 5.4_

  - [x] 4.8 Extend `StripeService` for compensation and coupons
    - Add `createDiscountCoupon`, `expireCheckoutSession`, `cancelSubscription` helpers, and read the currency from `services.stripe.currency`.
    - Tests with the Stripe client faked.
    - _Requirements: 5.4, 5.6_

  - [x] 4.9 Remove legacy checkout methods and update `CheckoutService` wiring
    - Delete `processCheckoutFromArray`, `processOneOffItemsLegacy`, `processRecurringItemsLegacy` and `createOrderFromCheckoutLegacy`.
    - Update the constructor for `AssetAssignmentService`, `StockService`, `HostingServiceCreator` and `OrderCancellationService`.
    - Migrate `CheckoutServiceTest` to a `cartFor()` helper and `processCheckout()`, replacing `createCheckoutSession` expectations with `createCheckoutSessionWithParams`.
    - _Requirements: 5.5_

  - [x] 4.10 Restructure `processCheckout` into a transaction followed by Stripe calls
    - Transaction 1 creates the order, items, answers, bookings, hosting services through `HostingServiceCreator`, and reserves stock through `StockService`. No Stripe call happens inside it.
    - After commit, create the coupon, Checkout Session (with `client_reference_id` and `metadata.order_id`), then each hosting subscription, tracking ids in a `StripeCreated` value object. Use the single currency helper and canonical domains for items and metadata.
    - Fully replace the existing one-off decrement in checkout with `StockService::reserve()`.
    - Tests: the fake Stripe records transaction level 0 on every call, currency comes from config, hosting service and stock effects are correct.
    - _Requirements: 1.6, 4.1, 5.1, 5.2, 5.6, 11.8_

  - [x] 4.11 Add Stripe failure compensation to checkout
    - On any Stripe failure after commit, call `OrderCancellationService::failOrder()` with the tracked objects and return an unsuccessful `CheckoutResult`.
    - Tests: failure at coupon, session, and each subscription leaves the order failed, stock restored, no Pending service, and created Stripe objects cancelled.
    - Update `CheckoutFlowTest` literals to `Web Hosting` and `Pending`, and add a mixed-cart case.
    - _Requirements: 5.3, 5.4_

  - [ ]* 4.12 Write property test for checkout failure compensation
    - **Property 10: Checkout failure compensation**
    - **Validates: Requirements 5.3, 5.4**

  - [x] 4.13 Use `HostingServiceCreator` in `FulfilmentService::handleHostingPurchase`
    - Call the creator, set the order `awaiting_fulfilment`, catch `DomainAlreadyInUseException` by holding the order with an `admin_notes` line and an admin notification.
    - Provisioning dispatch is added in task 6.20.
    - Migrate the hosting tests in `FulfilmentServiceTest` to canonical labels and repeated-call idempotence.
    - _Requirements: 3.3, 3.4, 3.5_

  - [x] 4.14 Remove stock handling from `FulfilmentService` and fix `fulfilOrder`
    - `handleOneOffPurchase` becomes a logging no-op. Remove `decrementStock()`.
    - `fulfilOrder` no longer activates hosting services and compares against `ServiceStatus::Pending`.
    - In `FulfilmentServiceTest`, replace stock-decrement tests with "fulfilment leaves stock unchanged" and delete the two rental decrement tests that assert behaviour the code never had. Re-point the rollback test at an injected creator failure.
    - _Requirements: 4.2, 4.3_

  - [x] 4.15 Rework `CleanupAbandonedOrdersCommand`
    - Per order: transaction, re-read with `lockForUpdate()`, skip if no longer pending, call `OrderCancellationService::expire()`, mark `expired`.
    - Add the hosting-only selector using `shop.abandoned_hosting_hours` (open decision 4 default: 48 hours). Delete only never-provisioned Pending services.
    - Tests: stock restored once across repeated runs, hosting-only orders expire, paid orders are untouched.
    - _Requirements: 4.4, 4.5, 4.6_

  - [ ]* 4.16 Write property test for stock conservation
    - **Property 9: Stock conservation**
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5**

  - [x] 4.17 Route admin order cancel through `OrderCancellationService`
    - Update `Admin\ShopOrderController::cancel` and `Api\Admin\ShopOrderController::cancel`, and replace lower-case status literals with `ServiceStatus` values.
    - Open decision 3 default: restock through the shared service, only for items with a recorded `stock_reserved_quantity`.
    - Tests: stock restored once for a new order, nothing restocked for a legacy order with no reserved quantity, cancel twice is safe.
    - _Requirements: 4.4, 4.5, 1.8_

  - [x] 4.18 Make `StripeWebhookController` transactional and exactly-once
    - Insert the `ProcessedWebhookEvent` marker inside the same `DB::transaction` as the handlers. A `UniqueConstraintViolationException` returns 200 and skips processing. Any other exception rolls back, is reported, and returns 500.
    - Handlers push closures onto an `$afterCommit` list that runs after commit, each in its own try/catch.
    - Add a schema test that `stripe_event_id` is unique (Requirement 6.1 is already met by the existing migration).
    - Update `StripeWebhookControllerTest`: duplicate delivery, rollback with marker, `Mail::fake` after-commit.
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

  - [x] 4.19 Update webhook handlers for statuses and session lookup
    - `invoice.payment_failed`: set `Active` or `Pending` services to `Suspended`, never change `Cancelled`, queue the email after commit.
    - `customer.subscription.deleted`: set `Cancelled`.
    - `checkout.session.completed`: look up by `stripe_checkout_session_id`, then `client_reference_id`. Return when the session is not ours, throw (500) when metadata names an order that does not exist yet.
    - Update tests that expected `payment_failed` or `cancelled` literals, and invert the stock assertion.
    - _Requirements: 1.7, 1.8, 6.4, 6.6_

  - [x] 4.20 Add the `invoice.paid` handler
    - For `billing_reason = subscription_create` on an order with no Checkout Session, mark the order paid and call `handleHostingPurchase`.
    - Tests: first invoice handled once, other billing reasons ignored, redelivery has no effect.
    - _Requirements: 3.4, 6.2_

  - [x] 4.21 Guard `StripeSyncInvoices` against activating Pending shop services
    - Do not change `status` while `status = Pending` and `order_item_id` is set. Still update charge and dates.
    - Test the guard and that hand-created services still sync.
    - _Requirements: 1.6, 9.6_

  - [ ]* 4.22 Write property test for webhook exactly-once and atomicity
    - **Property 11: Webhook exactly-once and atomic**
    - **Validates: Requirements 6.2, 6.3, 6.4, 6.5, 6.6**

  - [ ]* 4.23 Write property test for webhook status transitions
    - **Property 12: Webhook status transitions**
    - **Validates: Requirements 1.7, 1.8**

- [x] 5. Checkpoint - Phase 2 complete
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

- [x] 6. Phase 3: WHM
  - [x] 6.1 Add `WhmCredentials` and consolidate WHM requests in `WhmService`
    - `WhmCredentials::fromSettings()` decrypts `whm_hostname` and `whm_api_token`, reads `whm_username` (default `root`), and throws `WhmConnectionException` when host or token is missing.
    - One private `request()` always uses `verify => true` and the `Authorization: whm user:token` header. Remove the local-address verification bypass.
    - Connection errors, non-2xx responses and `metadata.result != 1` throw `WhmConnectionException` with the reason. Never log the token.
    - Tests with `Http::fake`, including local hostnames still verifying.
    - _Requirements: 7.1, 7.2, 7.3, 7.7_

  - [x] 6.2 Add the remaining `WhmService` methods
    - `createAccount($domain, $package, $contactEmail, $username)`, `findAccountByDomain`, `accountExists`, `listAccounts`, `getAccountInfo`, `createUserSession`.
    - Record `Http::fake` fixtures for each call. Confirm the `listaccts` search parameters and the `createacct` password requirement against the staging WHM version and keep any mismatch isolated in this class.
    - Tests for each method, success and failure.
    - _Requirements: 7.1, 7.7, 8.8_

  - [ ]* 6.3 Write property test for WHM failures
    - **Property 18: WHM failures raise WhmConnectionException**
    - **Validates: Requirements 7.7**

  - [x] 6.4 Add `whm:import-env-credentials` and `whm:check` commands
    - `whm:import-env-credentials` copies `config('services.whm.*')` into encrypted Settings only when the Setting values are empty, never overwrites existing values, and never prints secrets.
    - `whm:check` runs `testConnection` with TLS verification and prints a result.
    - Tests: import fills only empty settings, secrets are absent from output, check reports success and failure.
    - _Requirements: 7.2, 7.3, 7.6_

  - [x] 6.5 Update `CpanelMappingController` to use `WhmService` and reject duplicates
    - Use `listAccounts()` and degrade to an empty list plus a message on failure.
    - Validate `cpanel_username` is not assigned to another Service and name that Service in the error.
    - Tests: duplicate rejected, rejected batch applies no change, WHM failure shows a message.
    - _Requirements: 2.1, 7.1, 8.9_

  - [x] 6.6 Update the portal `SsoController` to use `WhmService`
    - Use `createUserSession()` and keep the `company_id` ownership guard. Remove `config('services.whm.*')` reads.
    - Tests: ownership guard still blocks other customers, session created through the client.
    - _Requirements: 7.1, 7.5_

  - [x] 6.7 Add one nameservers accessor
    - `Nameservers::get()` holds the defaults. Use it in `WhmConfigController` (both places) and `NotificationService`.
    - Tests: setting overrides, defaults when empty, every consumer shows the same values.
    - _Requirements: 8.5_

  - [x] 6.8 Implement `CpanelUsername` and hosting configuration
    - `candidates()` generator and `isValid()`. Lowercase `[a-z0-9]`, starts with a letter, length 3 to `hosting.username_max_length` (default 8), reserved list, numeric suffix replaces the tail of the base.
    - Add `config/hosting.php`.
    - Unit tests: long, Unicode, digits-only and reserved-word domains.
    - _Requirements: 8.6, 8.7_

  - [ ]* 6.9 Write property test for generated usernames
    - **Property 13: Generated cPanel usernames**
    - **Validates: Requirements 8.6, 8.7**

  - [x] 6.10 Add migration M6 for `services.whm_package`
    - Nullable string, add to `Service` fillable, implement `down()`.
    - Schema test.
    - _Requirements: 8.1_

  - [x] 6.11 Implement `ProvisioningDispatcher`
    - First create a skeleton `App\Jobs\ProvisionHostingJob` (constructor takes service id and optional requested username, empty `handle()`); task 6.15 completes it.
    - `dispatch()` runs an atomic `UPDATE` to `queued` only from NULL or `failed`, and dispatches the job only when a row changed. `retry()` accepts only `failed`.
    - Honour `queue.default`: `database` pushes to `jobs`, `sync` uses `dispatchAfterResponse()` for webhook callers and `dispatchSync()` for admin callers.
    - Tests: dispatch guard for every status, no dispatch when rejected, row unchanged on rejection.
    - _Requirements: 9.2, 9.11, 9.12_

  - [x] 6.12 Implement `HostingProvisioner` claim and input resolution
    - Atomic claim from `queued` to `running`. A `succeeded` service exits without calling WHM.
    - Resolve package (order item product `whm_package`, then `services.whm_package`), customer email and canonical domain before any WHM call. Missing package or email fails with a descriptive error and makes no WHM call.
    - Tests with the fake WHM for each precondition and the claim race.
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 9.5, 9.10_

  - [x] 6.13 Implement `HostingProvisioner` account creation, linking and outcome
    - Call `findAccountByDomain()` and link an existing account, refusing one owned by another service. Otherwise pick a free username (admin hint if valid, else `CpanelUsername` checked against `services.cpanel_username` and one `listAccounts()` snapshot, at most 99 candidates) and call `createAccount()`.
    - In one transaction save `cpanel_username`, `provisioning_status = succeeded`, clear the error, set `status = Active`, and complete the order only when all its items are hosting and succeeded.
    - On any earlier exception set `failed`, store the truncated error, and touch no other WHM field.
    - Tests: create, link, username collision, WHM failure, failure after account creation then a successful retry.
    - _Requirements: 8.6, 8.7, 8.8, 9.6, 9.7_

  - [x] 6.14 Send the provisioned notification after commit
    - Call `NotificationService::notifyCustomerHostingProvisioned()` after the transaction, inside try/catch. A send failure is logged and the status stays `succeeded`.
    - Tests: sent after commit, failing mailer leaves `succeeded`.
    - _Requirements: 9.8, 9.9_

  - [x] 6.15 Complete `ProvisionHostingJob` (skeleton created in 6.11)
    - `tries = 1`, `timeout = 90`, middleware `WithoutOverlapping("provision-service-{id}")->dontRelease()->expireAfter(300)`, `failed()` calls `markFailed()`.
    - Tests: job runs the provisioner, a held lock prevents a second run, a crash marks the service failed.
    - _Requirements: 9.3, 9.4, 9.10_

  - [ ]* 6.16 Write property test for provisioning inputs
    - **Property 14: Provisioning inputs**
    - **Validates: Requirements 8.1, 8.2, 8.3, 8.4**

  - [ ]* 6.17 Write property test for the provisioning state machine
    - **Property 15: Provisioning state machine**
    - **Validates: Requirements 8.8, 9.1, 9.2, 9.4, 9.6, 9.7, 9.8, 9.9, 9.10**

  - [ ]* 6.18 Write property test for the retry guard
    - **Property 16: Retry guard**
    - The UI rendering part of this property is covered in task 12.5.
    - **Validates: Requirements 9.11, 9.12**

  - [x] 6.19 Add `hosting:fail-stale` and tighten the queue schedule
    - `hosting:fail-stale` marks `running` services with `provisioning_updated_at` older than 15 minutes as `failed` with a timeout message. Register it every ten minutes through `Schedule::call` in `routes/console.php`.
    - Change the `queue:work` entry to `withoutOverlapping(10)` and `--max-time=50`. Add `DB_QUEUE_RETRY_AFTER=180` to `.env.example` only; do not edit `.env`.
    - Tests: stale rows failed, fresh rows untouched, schedule entries registered.
    - _Requirements: 9.7_

  - [x] 6.20 Dispatch provisioning from `FulfilmentService`
    - `handleHostingPurchase` registers a post-commit callback to call `ProvisioningDispatcher::dispatch()`. Repeat calls do not re-dispatch.
    - Tests: exactly one job per hosting item across repeated deliveries, status set to `queued`.
    - _Requirements: 9.1, 9.2_

  - [x] 6.21 Make `HostingProvisionController` dispatch the job
    - Replace synchronous provisioning with `ProvisioningDispatcher`. Use the product `whm_package`. Add `POST admin/hosting/provisioning/{service}/retry` using `retry()`, inside the admin middleware group.
    - Open decision 5 default: keep the `whm_default_package` setting and field, label it as unused for provisioning, and keep stored values.
    - Tests: no WHM call from the controller, retry only from `failed`, admin-only.
    - _Requirements: 9.11, 9.12, 9.13, 22.2_

  - [x] 6.22 Move `Admin\ServiceController` store to queued provisioning
    - With `provision_whm` ticked, store `whm_package` and call the dispatcher with the requested username. Persist the form `cpanel_username` only when provisioning is not requested.
    - Remove `provisionWhmAccount()`.
    - Tests: no inline WHM call, job dispatched, mapping of an existing account still works.
    - _Requirements: 7.4, 9.2_

  - [x] 6.23 Remove WHM calls from the Admin service show page and add refresh
    - `show()` makes no WHM call and removes `fetchWhmAccountInfo()`. Add `POST admin/services/{service}/refresh-whm` calling `getAccountInfo()` and redirecting with `whmInfo` or `whmError`.
    - Tests: show works with WHM down and makes no HTTP call, refresh shows info or the failure message while the rest of the page renders.
    - _Requirements: 7.4, 10.1, 10.2, 10.3_

  - [x] 6.24 Remove `.env`-based WHM configuration
    - Delete the `services.whm` block from `config/services.php` and the WHM lines from `.env.example`, and remove the import command's dependency on it only after the user confirms `whm:import-env-credentials` and `whm:check` passed in production (ask the user before executing this task).
    - Add a source-scan test: only `WhmService` contains `json-api/` and no code reads `services.whm`.
    - _Requirements: 7.1, 7.6_

  - [ ]* 6.25 Write property test for cPanel username uniqueness
    - **Property 17: cPanel username uniqueness**
    - **Validates: Requirements 2.1, 8.9**

- [x] 7. Checkpoint - Phase 3 complete
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

- [x] 8. Phase 4: Domains and eNom
  - [x] 8.1 Add migration M7 for domain sync columns and soft deletes
    - `status` (default `active`, indexed), `enom_domain_name_id` (indexed), `last_synced_at`, `admin_edited_fields` (json), `deleted_at`.
    - Guard with `hasColumn`, implement `down()`, schema test. Update `DomainFactory` states to use the new columns.
    - _Requirements: 13.1, 15.3_

  - [x] 8.2 Backfill `enom_domain_name_id` and add `unique(services.domain_id)` (migration M7b, separate from M7)
    - Extend `ServiceDomainBackfill` to copy `DomainNameID` from `enom_response` JSON, ignoring malformed or empty JSON.
    - Preflight duplicate `services.domain_id` values, then add the unique index.
    - Tests: valid, malformed and missing JSON, idempotence, duplicate abort. This completes property 5.
    - _Requirements: 12.1, 13.2_

  - [x] 8.3 Update the `Domain` model and `Service` relations
    - `Domain`: `SoftDeletes`, `services()`, `ENOM_FIELDS`, scopes `enomSourced`, `expiring`, `expired`, `visible`, `unassigned`, `missingFromEnom`.
    - `Service`: `domain()` and `domainName()` (reads the linked Domain when `domain_id` is set). Domain rename updates linked `services.domain_name` in one transaction.
    - Tests: relations, scopes, name read-through, rename sync.
    - _Requirements: 12.5, 15.1, 15.3_

  - [x] 8.4 Implement `DomainEditTracker`
    - Merge changed `ENOM_FIELDS` keys into `admin_edited_fields`. Add a reset method clearing the flags.
    - Tests: only changed eNom fields recorded, other fields ignored, reset clears.
    - _Requirements: 13.3_

  - [x] 8.5 Update `Admin\DomainController` store and update
    - Normalise `domain_name` before the unique check and before saving, call `DomainEditTracker`, and include trashed rows in the uniqueness check with an error that offers restore.
    - Tests: variants treated as duplicates, edits flagged, trashed duplicate error.
    - _Requirements: 11.4, 13.3_

  - [x] 8.6 Update `ImportController` to normalise and report conflicts
    - Normalise names, use `DomainEditTracker`, and report unique-index conflicts per row instead of throwing.
    - Tests: variants merge, conflicting rows reported, valid rows still import.
    - _Requirements: 11.4, 11.9, 13.3_

  - [x] 8.7 Make `CartService` use `DomainNormaliser`
    - `isValidDomain()` becomes a wrapper over `tryNormalise() !== null` with the same public signature. Store the canonical name.
    - Update `CartServiceTest` to canonicalisation expectations.
    - _Requirements: 11.7_

  - [x] 8.8 Make `Admin\ServiceController` store transactional for domain checks
    - Run the duplicate check, Service creation, and Domain find-or-create and link in one transaction. Convert a unique violation into a validation error naming the duplicate domain.
    - Open decision 1 default: a domain held by a Cancelled service is rejected with an error naming that service, not reactivated.
    - Tests: duplicate domain, cancelled-service domain, new domain gets a Domain row linked by `domain_id`.
    - _Requirements: 12.2, 12.3, 12.4_

  - [x] 8.9 Implement `EnomClient`
    - POST with credentials in the body, `getDomainsPage($start, $pageSize)` returning domains, total and ok. Retry up to 3 times with delays 500, 1500 and 4000 ms on connection errors and 5xx, then throw `EnomUnavailableException` with no request body in the message. Redact `uid` and `pw` in debug output.
    - Verify the pagination parameter names and total-count field against eNom GetDomains documentation and the sandbox, and capture XML fixtures.
    - Tests from the fixtures: pagination to total, retry counts and delays, no password in URL, logs or messages.
    - _Requirements: 14.1, 14.2, 14.3, 14.4_

  - [ ]* 8.10 Write property test for the eNom client
    - **Property 21: eNom client pagination, retry and secrecy**
    - **Validates: Requirements 14.1, 14.3, 14.4**

  - [x] 8.11 Implement `EnomDomainSync` and make `enom:sync` a thin wrapper
    - Fetch all pages into memory first and abort without writes if any page fails. In one transaction match `withTrashed()` by canonical name, update only non-edited `ENOM_FIELDS` plus `enom_response`, `last_synced_at` and `status = active`, keep the existing expiry when unparseable (log the domain), and create unmatched domains with null `company_id`.
    - After a complete run, set `missing_from_enom` on eNom-sourced domains with `last_synced_at` older than the run start. Never delete rows.
    - Tests: ownership, new domains, flagging, reactivation, failed page flags nothing, trashed rows matched.
    - _Requirements: 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 13.10, 13.11, 14.5_

  - [ ]* 8.12 Write property test for eNom field ownership
    - **Property 19: eNom field ownership**
    - **Validates: Requirements 13.3, 13.4, 13.5, 13.6, 20.3**

  - [ ]* 8.13 Write property test for missing-domain flagging
    - **Property 20: eNom missing-domain flagging**
    - **Validates: Requirements 13.7, 13.8, 13.9, 13.10, 13.11**

  - [x] 8.14 Update the `Admin\DomainController` index counts and add customer search
    - Compute counts from the shared model scopes. Add the `customerSearch` JSON endpoint returning at most 20 matches, and use it for the customer dropdown.
    - Tests: counts equal filtered rows, search cap and matching.
    - _Requirements: 15.1, 15.2_

  - [x] 8.15 Make domain deletion soft and safe
    - `destroy` refuses when `services()->exists()` and names the linked Service, otherwise soft deletes.
    - Tests: unlinked domain soft deleted and found with trashed, linked domain refused.
    - _Requirements: 15.3, 15.4, 15.5_

  - [x] 8.16 Fix `CleanupController` queries
    - Eager load domains and related services, replace the per-row `exists()` loop with one set-based query, and apply the linked-service refusal in `deleteDomains`.
    - Tests: query count does not grow with row count, linked domains are not deleted.
    - _Requirements: 15.5, 15.6_

  - [x] 8.17 Update `ReviewWizardController::moveToDomain`
    - Set `services.domain_id`, move `stripe_subscription_id` and cost to the Domain when it has none, and remove duplicated domain data.
    - Open decision 2 default: never delete the Service.
    - Tests: Service survives and is linked, a Domain with existing values keeps them.
    - _Requirements: 15.7_

  - [ ]* 8.18 Write property test for domain lists, filters and counts
    - **Property 22: Domain lists, filters and counts**
    - **Validates: Requirements 15.1, 15.2, 20.1, 20.2**

  - [ ]* 8.19 Write property test for deletion and linking safety
    - **Property 23: Domain deletion and linking safety**
    - **Validates: Requirements 12.4, 15.4, 15.5, 20.6**

  - [ ]* 8.20 Write property test for cleanup query count
    - **Property 29: Query count is independent of row count**
    - **Validates: Requirements 15.6**

  - [ ]* 8.21 Write property test for canonical domains at every entry point
    - **Property 3: Every entry point stores canonical domains**
    - Cover admin domain store/update, admin and API service store/update, cart add, checkout order items and Stripe metadata, and eNom sync.
    - **Validates: Requirements 11.4, 11.5, 11.6, 11.7, 11.8, 14.5**

- [x] 9. Checkpoint - Phase 4 complete
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

- [x] 10. Phase 5: Web apps
  - [x] 10.1 Add migration M8 and update the `CustomerApp` model
    - Add `domain_id` FK and `public_id` char(26) (unique, nullable, backfilled in chunks). Add a `creating` hook assigning `Str::ulid()`, and a `domain()` relation.
    - Test the existing `unique(service_id)` index is present, plus the new columns and backfill. Extend `CustomerAppSchemaTest`.
    - _Requirements: 16.1, 16.2_

  - [x] 10.2 Implement `CustomerAppService::createForService`
    - One transaction, rely on `unique(service_id)`, convert `UniqueConstraintViolationException` to a `ValidationException` on `service_id`. Default `install_url` to `DomainNormaliser::toHttpsUrl()` of the selected or linked domain when none is given.
    - Tests: single create, second create rejected, race simulated by a unique violation, default URL.
    - _Requirements: 16.3, 16.4, 16.5_

  - [x] 10.3 Use the shared action in both `CustomerAppController` classes
    - Use `createForService` in `Admin\CustomerAppController::store` and `Api\Admin\CustomerAppController::store`. Fix the `update` docblocks to list the five fields they update.
    - Keep `OneToOneInvariantPropertyTest` and `ServiceDeletionCascadeTest` passing, and assert the Domain survives service deletion.
    - _Requirements: 16.3, 16.6, 16.7_

  - [ ]* 10.4 Write property test for one web app per service
    - **Property 24: One web app per service**
    - **Validates: Requirements 16.3, 16.4, 16.5**

  - [x] 10.5 Expose `public_id` in the DTO and MCP app tools
    - Add `public_id` to `CustomerAppDto` and the `list_apps` and `get_app` outputs.
    - Update `CustomerAppDtoAllowListPropertyTest` and keep sensitive-field absence checks.
    - _Requirements: 17.1_

  - [x] 10.6 Add migration M9 for finding fingerprints
    - Add `fingerprint` char(64) and `last_seen_at`. Backfill fingerprints (sha256 of the trimmed, whitespace-collapsed, lower-cased description), de-duplicate within an app logging merged ids, then add `unique(customer_app_id, fingerprint)`.
    - Tests: backfill, duplicate merge, idempotence.
    - _Requirements: 17.2, 17.3_

  - [x] 10.7 Update the `SecurityFinding` model for `not_reported`
    - Add `STATUS_NOT_REPORTED` to `STATUSES`. Keep `PAYLOAD_STATUSES` as `open`, `resolved`, `dismissed` so connectors cannot send `not_reported`. `openFindings()` counts only `open`.
    - Unit tests for the constants and counts.
    - _Requirements: 17.4_

  - [x] 10.8 Resolve apps by `public_id` and enforce payload limits in `FindingsIngestService`
    - Look up by `public_id` only. Unknown identifiers (including numeric primary keys) throw `UnknownCustomerAppException`. Reject payloads over 1 MB of JSON or over 1000 findings with `InvalidFindingsPayloadException` before any write.
    - Tests: valid, unknown id, numeric id, size and count limits, nothing persisted on rejection.
    - _Requirements: 17.1, 17.5, 17.6_

  - [x] 10.9 Replace delete-and-replace with fingerprint upsert in `FindingsIngestService`
    - In one transaction: upsert by `(customer_app_id, fingerprint)`, update severity, description and timestamps, keep `resolved` and `dismissed` states, insert unmatched findings, mark stored `open` findings absent from the payload `not_reported`, collapse duplicates within a payload (last wins), never delete. Keep package replace semantics inside the same transaction.
    - Tests: each rule, idempotent re-ingest, mid-ingest failure leaves stored findings unchanged.
    - _Requirements: 17.2, 17.3, 17.4, 17.7_

  - [x] 10.10 Update the findings route, MCP tool and existing findings tests
    - Change the `FindingsController` route parameter, `RecordAppFindingsTool` and `ToolRegistry` (`app_id` becomes a string `public_id`), and add an early `Content-Length` check.
    - Update `FindingsIngestServiceTest`, `FindingsBulkReplaceIdempotenceTest`, `FindingsIngestRejectionTest`, `FindingsEndpointAuthorizationAuditTest`, `FindingsScopeAndNoScanStructureTest` and `McpDispatchWiringTest` for `public_id` and upsert semantics.
    - _Requirements: 17.1, 17.5, 17.6_

  - [ ]* 10.11 Write property test for findings preservation
    - **Property 25: Findings ingest preserves decisions and history**
    - **Validates: Requirements 17.1, 17.2, 17.3, 17.4, 17.7**

  - [ ]* 10.12 Write property test for findings limits
    - **Property 26: Findings payload limits**
    - **Validates: Requirements 17.5, 17.6**

  - [x] 10.13 Add migration M10 and `ServiceBillingService` for admin-created Stripe billing
    - Add nullable `billing_state` and `billing_error`. `Admin\ServiceController::store` creates Stripe objects after the Service transaction commits through `ServiceBillingService`.
    - A Stripe failure sets `billing_state = billing_failed` and stores the error. Success clears both.
    - Tests: success, failure state, the Service persists on failure.
    - _Requirements: 18.1, 18.2_

  - [x] 10.14 Add the billing retry action and persistent warning
    - `POST admin/services/{service}/retry-billing` (admin-only) calls `ServiceBillingService` again for `billing_failed` services. The Service show page shows a persistent warning with a retry button while the state is `billing_failed`.
    - Tests: retry success clears the warning, retry failure keeps it, non-failed services rejected.
    - _Requirements: 18.2, 18.4_

- [x] 11. Checkpoint - Phase 5 complete
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

- [x] 12. Phase 6: Admin UI
  - [x] 12.1 Add the `x-status-badge` Blade component
    - Always render a text label plus an accessible name, never colour alone. Replace inline badge ternaries in services index/show, domain index and the provisioning queue as those screens are edited.
    - Render tests for each status and provisioning status.
    - _Requirements: 22.3_

  - [x] 12.2 Make flash and error display consistent in the admin layout
    - Confirm `x-admin-layout` renders success, error and validation messages. Add anything missing and remove the duplicated flash markup in `hosting/provision/index.blade.php`.
    - Render test across service, domain and customer-app screens.
    - _Requirements: 22.1_

  - [x] 12.3 Render service type and status selectors from the enums
    - Use `ServiceType::values()` and `ServiceStatus::values()` in the service create and edit views.
    - Tests: options match the enums exactly, no hard-coded lists remain.
    - _Requirements: 19.6_

  - [x] 12.4 Rebuild the provisioning queue index
    - List every `Web Hosting` service with customer and domain eager loaded, 25 per page. Show provisioning status badge, error, customer, domain, product and created date. Add the status filter (`queued`, `running`, `succeeded`, `failed`, "Not tracked").
    - Tests: shop-bought and hand-created services appear, filters return only matching rows, rows show the required fields.
    - _Requirements: 19.1, 19.2, 19.3_

  - [x] 12.5 Add Retry and Provision actions to the queue
    - Show Retry only for `failed` and Provision only for NULL status with `Pending` service status. Retry uses the route from task 6.21 and shows the updated status. Add a `provision` route for NULL status, admin-only.
    - Tests: buttons rendered for the right rows only, retry updates the status, non-admin denied.
    - _Requirements: 19.4, 19.5, 22.2_

  - [x] 12.6 Update the Admin service show page
    - Show provisioning status and error, the "Refresh WHM info" button, and either a "Create web app" link (no app) or a link to the app.
    - Tests for each state.
    - _Requirements: 19.7, 21.1, 21.3_

  - [x] 12.7 Prefill the shared web app creation form
    - The "Create web app" action opens `customer-apps.create` prefilled with the Service and its linked Domain. Point the `pick` screen at the same form.
    - Tests: prefilled values and the single creation route.
    - _Requirements: 16.6, 21.2_

  - [x] 12.8 Link Domain and Service from the CustomerApp screens
    - Show the linked Domain and Service as links on index and show screens.
    - Render tests.
    - _Requirements: 21.4_

  - [x] 12.9 Add the Unassigned and Missing from eNom domain filters
    - Add filter pills using the shared scopes.
    - Tests: each filter returns exactly the matching domains.
    - _Requirements: 20.1, 20.2_

  - [x] 12.10 Add inline customer assignment on the domain index
    - `POST admin/domains/{domain}/assign` saves `company_id` only and leaves eNom fields and the edited-field set unchanged. Add an inline form on unassigned rows using the customer search endpoint.
    - Tests: only `company_id` changes, admin-only.
    - _Requirements: 20.3, 20.4, 22.2_

  - [x] 12.11 Update the domain show/edit page
    - Show `status`, `last_synced_at`, the linked Service, edited-field badges, and a "Reset to eNom control" action using `DomainEditTracker`.
    - Tests: values displayed, reset clears the flags.
    - _Requirements: 20.5_

  - [x] 12.12 Add domain link-service and restore actions
    - `POST admin/domains/{domain}/link-service` sets `services.domain_id` and rejects a Domain already linked to another Service. `POST admin/domains/{id}/restore` restores trashed rows. Both admin-only.
    - Tests: link success, already-linked rejection, restore, non-admin denied.
    - _Requirements: 20.6, 22.2_

  - [ ]* 12.13 Write property test for the provisioning queue listing
    - **Property 27: Provisioning queue listing**
    - **Validates: Requirements 19.1, 19.2, 19.3**

  - [ ]* 12.14 Write the admin-only route matrix test
    - **Property 28: Admin-only actions**
    - Cover provisioning retry and provision, domain assign, link and restore, WHM refresh, and billing retry for guests and non-admins.
    - **Validates: Requirements 22.2**

  - [ ]* 12.15 Write UI consistency and accessible-name tests
    - Assert shared layout and message markup across service, domain and CustomerApp screens, and text labels and accessible names on status badges, filters and action buttons. These checks do not replace manual testing with assistive technologies.
    - _Requirements: 22.1, 22.3_

- [x] 13. Final checkpoint - Phase 6 complete and all tests pass
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP.
- Each task references specific requirements for traceability. Property tasks reference the design's numbered properties.
- Run the data migration dry run `php artisan services:normalise-data` (task 2.18) and review its output before running the Phase 1 migrations in production. Run `php artisan whm:import-env-credentials` then `php artisan whm:check` (task 6.4) before deploying Phase 3 and before executing task 6.24.
- Deploy each phase using the design's Deployment Runbook: backup, deploy, dry run, `migrate --force`, verify.
- Task 4.4 and task 4.5 guard the `domains.status` column until task 8.1 adds it, so Phase 2 can ship before Phase 4.
- Open decision defaults are listed in the Overview table. Change the named task before executing it to use a different choice.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3", "1.4", "1.5"] },
    { "id": 2, "tasks": ["1.6"] },
    { "id": 3, "tasks": ["2.1", "2.3", "2.10"] },
    { "id": 4, "tasks": ["2.2", "2.4", "2.5", "2.11"] },
    { "id": 5, "tasks": ["2.6", "2.12"] },
    { "id": 6, "tasks": ["2.7", "2.13"] },
    { "id": 7, "tasks": ["2.8", "2.14"] },
    { "id": 8, "tasks": ["2.9", "2.15", "2.16"] },
    { "id": 9, "tasks": ["2.17", "2.18"] },
    { "id": 10, "tasks": ["4.1", "4.2"] },
    { "id": 11, "tasks": ["4.3", "4.4", "4.8"] },
    { "id": 12, "tasks": ["4.5", "4.7"] },
    { "id": 13, "tasks": ["4.6", "4.9"] },
    { "id": 14, "tasks": ["4.10"] },
    { "id": 15, "tasks": ["4.11"] },
    { "id": 16, "tasks": ["4.12", "4.13", "4.15"] },
    { "id": 17, "tasks": ["4.14", "4.17", "4.18"] },
    { "id": 18, "tasks": ["4.16", "4.19"] },
    { "id": 19, "tasks": ["4.20"] },
    { "id": 20, "tasks": ["4.21", "4.22"] },
    { "id": 21, "tasks": ["4.23"] },
    { "id": 22, "tasks": ["6.1", "6.7", "6.8", "6.10"] },
    { "id": 23, "tasks": ["6.2", "6.9"] },
    { "id": 24, "tasks": ["6.3", "6.4", "6.5", "6.6", "6.11"] },
    { "id": 25, "tasks": ["6.12", "6.25"] },
    { "id": 26, "tasks": ["6.13"] },
    { "id": 27, "tasks": ["6.14"] },
    { "id": 28, "tasks": ["6.15"] },
    { "id": 29, "tasks": ["6.16", "6.17", "6.18", "6.19", "6.20"] },
    { "id": 30, "tasks": ["6.21", "6.22"] },
    { "id": 31, "tasks": ["6.23"] },
    { "id": 32, "tasks": ["6.24"] },
    { "id": 33, "tasks": ["8.1", "8.7", "8.9"] },
    { "id": 34, "tasks": ["8.2", "8.3", "8.10"] },
    { "id": 35, "tasks": ["8.4", "8.8"] },
    { "id": 36, "tasks": ["8.5", "8.6", "8.11"] },
    { "id": 37, "tasks": ["8.12", "8.13", "8.14", "8.16"] },
    { "id": 38, "tasks": ["8.15", "8.17", "8.18"] },
    { "id": 39, "tasks": ["8.19", "8.20", "8.21"] },
    { "id": 40, "tasks": ["10.1", "10.6", "10.13"] },
    { "id": 41, "tasks": ["10.2", "10.5", "10.7", "10.14"] },
    { "id": 42, "tasks": ["10.3", "10.8"] },
    { "id": 43, "tasks": ["10.4", "10.9"] },
    { "id": 44, "tasks": ["10.10"] },
    { "id": 45, "tasks": ["10.11", "10.12"] },
    { "id": 46, "tasks": ["12.1", "12.2"] },
    { "id": 47, "tasks": ["12.3", "12.4", "12.9"] },
    { "id": 48, "tasks": ["12.5", "12.6"] },
    { "id": 49, "tasks": ["12.7", "12.8", "12.10"] },
    { "id": 50, "tasks": ["12.11"] },
    { "id": 51, "tasks": ["12.12"] },
    { "id": 52, "tasks": ["12.13", "12.14", "12.15"] }
  ]
}
```
