# Implementation Plan: Domain Billing and Admin Attention

## Overview

The plan follows the design order: groundwork and schema, the billing core, triggers and the daily command, webhooks, admin screens, the order attention flag, the one-off fold command, the orphan report, and finally the attention dashboard. The language is PHP 8.4 on Laravel 13 with PHPUnit. There is no property-testing library, so property tests are seeded PHPUnit loops of at least 100 iterations using `Tests\Support\Gen`, as in the previous spec.

Each implementation task includes the example tests that prove it. Property tests are separate sub-tasks marked with `*`. Every phase ends with a checkpoint. Nothing in this plan creates a Stripe subscription in production until `domains:ensure-billing --apply` is run by hand (see Notes).

## Tasks

- [ ] 1. Groundwork
  - [ ] 1.1 Run the full test suite and record the baseline
    - Run `php artisan test` and list failing tests in `.kiro/specs/domain-billing-and-admin-attention/baseline-failures.md`.
    - Compare with `.kiro/specs/services-domains-data-cleanup/baseline-failures.md`. Make no code changes.
    - Later checkpoints compare against this list so old failures are not treated as regressions.
    - _Requirements: all (regression baseline)_

  - [ ] 1.2 Extend `FakeStripeService` and `Tests\Support\Gen`
    - `FakeStripeService`: add `retrievePrice`, `retrieveSubscription`, `findSubscriptionForDomain`, `listActiveSubscriptions` and the `$options` argument on `createInvoicedSubscription`. Record calls as `read` or `write` and add `writeCalls()`. Support configurable prices, subscriptions and failure injection per method.
    - `Gen`: add seeded generators for domains (customer none, exempt or other; managed flag; trashed; expiry; subscription id), services of type `Other` with subscriptions, fake Stripe subscriptions and prices, and scheduled task log rows.
    - Tests for the doubles and for seed reproducibility.
    - _Requirements: supports 1.x to 13.x_

- [ ] 2. Schema and models
  - [ ] 2.1 Add migration D1 and update the `Domain` model
    - Add `managed_externally` (boolean, default false), `billing_state` (string 20, indexed), `billing_error` (text) and `billing_updated_at` to `domains`, guarded with `hasColumn`, with a `down()`.
    - Add the `BILLING_*` constants, fillable fields and the boolean cast to `Domain`. Add `managed_externally` handling to `DomainFactory`.
    - Schema test for the columns and defaults.
    - _Requirements: 1.1, 9.4_

  - [ ] 2.2 Add migration D2 and the `DomainBilling` settings helper
    - Seed `domain_stripe_price_id = price_1QXM7mKOtRMQ2ybuHlnPu2hx` and `domain_billing_exempt_company_id = 1` with `insertOrIgnore`, never overwriting. `down()` leaves settings alone.
    - Add `App\Support\DomainBilling` with `priceId()`, `exemptCompanyId()`, `isExempt()` and `dashboardUrl()`. An empty or non-numeric exempt value means no exempt company.
    - Tests: defaults, existing values kept, invalid exempt value, test and live dashboard URLs.
    - _Requirements: 1.4, 2.1, 2.2_

  - [ ] 2.3 Add the `Domain` billing scopes and `billingStatus()`
    - Add `billable`, `awaitingEnom`, `externallyManaged`, `billingProblem` and `expiringWithoutAutoRenew` scopes, all using `DomainBilling::exemptCompanyId()`.
    - Add `billingStatus()` with the precedence from the design.
    - Tests: one example per precedence step and per scope.
    - _Requirements: 1.1, 1.2, 1.3, 9.3_

  - [ ]* 2.4 Write property test for the billable set
    - **Property 1: Billable set equals the definition**
    - **Validates: Requirements 1.1, 1.2, 1.3, 9.7**

  - [ ] 2.5 Add migration D3 and the `Order` attention scope
    - Add `needs_attention` (boolean, default false, indexed) and `attention_reason` (string 255) to `orders`. Backfill orders whose `admin_notes` contain a line starting `Held: domain` and that are not completed.
    - Add fillable fields, the cast and `scopeNeedsAttention()` to `Order`.
    - Tests: columns, backfill, idempotence, `down()`.
    - _Requirements: 12.1, 12.6_

  - [ ] 2.6 Add `Service` scopes and use them in existing screens
    - Add `webHosting`, `provisioningFailed`, `provisioningStuck(15)` and `billingFailed` scopes to `Service`.
    - Switch `HostingProvisionController::index` and the services index to these scopes where they apply, with no behaviour change.
    - Tests for each scope and the unchanged lists.
    - _Requirements: 13.2, 13.4_

- [ ] 3. Billing core
  - [ ] 3.1 Add `StripeService` read helpers and subscription options
    - Add `retrievePrice` (expands product), `retrieveSubscription`, `findSubscriptionForDomain` and `listActiveSubscriptions` (auto-paginating).
    - Add an optional `$options` array to `createInvoicedSubscription` carrying `trial_end`, `proration_behavior` and an idempotency key. Existing callers keep working.
    - Tests with the Stripe client faked, including the existing `ServiceBillingService` call.
    - _Requirements: 2.4, 3.1, 5.5, 5.6, 11.3_

  - [ ] 3.2 Implement `DomainBillingDate`, `DomainPriceValidator` and `ExpiryNotInFutureException`
    - `DomainBillingDate::trialEnd()` and `anchoredToAnniversary()` as pure functions with the 725-day window and `subYearsNoOverflow`.
    - `DomainPriceValidator::check(Price): ?string` returns the failed rule or null.
    - Unit tests: 29 February, exactly 725 and 726 days, expiry today or past, inactive, one-off, monthly and two-year prices.
    - _Requirements: 2.6, 4.1, 4.2, 4.3, 4.4_

  - [ ]* 3.3 Write property tests for the pure functions
    - **Property 4: Billing date**
    - **Property 3: Price validation** (validator part)
    - **Validates: Requirements 2.6, 4.1, 4.2, 4.3, 4.4**

  - [ ] 3.4 Implement `DomainBillingService`
    - Implement `ensure(Domain, bool $force)` with the early exits, atomic claim, stale re-claim after 10 minutes, price check, billing date, adopt, create, guarded store and failure recording, using Eloquent date bindings so SQLite and MySQL behave alike.
    - Implement `ensureAfterCommit()` with `DB::afterCommit()`. Never rethrow Stripe errors.
    - Add `DomainBillingResult`. Tests: success, each failure step, claim lost, stale claim, adopt, already billed, awaiting eNom, deleted state with and without force, Stripe called at transaction level 0.
    - _Requirements: 1.5, 2.8, 2.9, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 4.3, 4.5, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 6.8_

  - [ ]* 3.5 Write property tests for `ensure()`
    - **Property 2: Non-billable domains create no Stripe object**
    - **Property 3: Price validation** (ensure part)
    - **Property 5: Created subscription parameters**
    - **Property 6: Failure is recorded and recoverable**
    - **Property 7: Idempotence and at most one subscription**
    - **Property 8: Deleted subscriptions are not recreated automatically**
    - **Validates: Requirements 1.5, 2.8, 2.9, 3.1, 3.2, 3.3, 3.4, 3.5, 4.5, 5.1, 5.2, 5.3, 5.4, 5.5, 5.7, 6.6, 6.8, 7.6, 14.3**

- [ ] 4. Triggers and the ensure command
  - [ ] 4.1 Add the `domains:ensure-billing` command, whitelist entry and schedule
    - Default dry run lists Billable_Domains with billing date and anchor, awaiting domains, and a summary, and reads no Stripe data. `--apply` calls `ensure()` per domain and logs counts in `ScheduledTaskLog`.
    - Add the command to `SettingsController::runnableCommands` and the `in:` rule, always run without `--apply` from the UI. Schedule `--apply` daily at 03:30 in `routes/console.php` through `Schedule::call(fn () => Artisan::call(...))`.
    - Tests: dry run writes nothing, apply bills only the billable set, `subscription_deleted` skipped, UI never applies, schedule entry exists.
    - _Requirements: 6.8, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

  - [ ]* 4.2 Write property test for the ensure command
    - **Property 9: Ensure command dry run and apply**
    - **Validates: Requirements 7.1, 7.2, 7.3**

  - [ ] 4.3 Return billing candidates from the eNom sync and trigger billing
    - `EnomDomainSync::apply()` returns `billing_candidates`: ids of domains whose `expiry_date` went from null to a value, including newly created ones, that match `Domain::billable()`.
    - `EnomSyncDomains` calls `ensure()` for each candidate after `sync()` returns, each in its own try/catch, and adds counts to its log.
    - Tests: candidate detection, no candidate for unchanged expiry, exempt or external domains, one failing candidate does not stop the rest.
    - _Requirements: 6.1, 6.6_

  - [ ] 4.4 Trigger billing from the domain forms
    - `DomainController::assign`, `store` and `update` call `ensureAfterCommit()` when the domain belongs to a non-exempt customer. Add `managed_externally` to `DomainRequest`.
    - When the customer of a domain with a subscription changes, flash a warning and leave the subscription alone.
    - Tests: assign from none and from the exempt company, create, update, failure leaves the action successful, reassign warning, not-managed domains untouched.
    - _Requirements: 6.2, 6.3, 6.4, 6.6, 6.7, 9.4_

  - [ ] 4.5 Add the retry billing action
    - Add `POST admin/domains/{domain}/retry-billing` (`admin.domains.retry-billing`) calling `ensure($domain, force: true)` and flashing the outcome. Reject states where a retry makes no sense with a flash error.
    - Tests: retry from `billing_failed` and `subscription_deleted`, already billed, non-admin denied.
    - _Requirements: 6.5, 6.8, 9.6, 9.8_

- [ ] 5. Checkpoint - billing core and triggers
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

- [ ] 6. Webhooks and sync
  - [ ] 6.1 Handle domain subscriptions in `StripeWebhookController`
    - `invoice.payment_failed`: when no Service matches, set the Domain to `payment_failed` with the reason and queue an admin notification after commit through the new `NotificationService::notifyAdminDomainPaymentFailed()`.
    - `customer.subscription.deleted`: clear the Domain id, set `subscription_deleted` and record the deletion.
    - `invoice.paid`: before the existing `subscription_create` early return, clear `payment_failed` for a matching Domain.
    - Services keep precedence. Match domains with `withTrashed()`. Keep everything inside the existing transaction.
    - Tests: each event, service precedence, duplicate delivery, rollback with marker, no email on failure.
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.9_

  - [ ] 6.2 Clear cancelled domain subscriptions in `StripeSyncInvoices`
    - For a subscription matching a Domain with Stripe status `canceled` or `incomplete_expired`, clear the id and set `subscription_deleted`. Keep the existing cost update for live subscriptions.
    - Tests: cancelled cleared, active unchanged, service rows unaffected.
    - _Requirements: 8.8_

  - [ ]* 6.3 Write property tests for domain webhooks
    - **Property 10: Domain webhook state transitions**
    - **Property 11: Domain webhook exactly-once and atomic**
    - **Validates: Requirements 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.9**

- [ ] 7. Admin screens for domain billing
  - [ ] 7.1 Add the domain billing settings screen
    - Add `Admin\DomainBillingSettingsController` with `edit`, `update` and `checkPrice`, routes `settings.domain-billing`, `.update` and `.check-price`, a view, and a link in the settings navigation.
    - `edit` makes no Stripe call. `checkPrice` shows product name, amount, currency and interval. `update` validates the id format and, when it changed, validates the price against Stripe. A Stripe outage keeps the stored value.
    - Tests: no Stripe call on load, check price, invalid price rejected, outage handling, exempt company validation, admin only.
    - _Requirements: 2.3, 2.4, 2.5, 2.6, 2.7, 9.8_

  - [ ] 7.2 Add the `domain-billing` map to `x-status-badge`
    - Labels: Billed, No subscription, Awaiting eNom, Exempt, Not managed, Billing failed, Payment failed, Subscription deleted. Each renders text and an accessible name.
    - Render tests for every state.
    - _Requirements: 9.3_

  - [ ] 7.3 Add domain index filters, counts and badges
    - Filters `no_subscription`, `awaiting_enom`, `not_managed`, `billing_problem` and `expiring_no_renew` using the model scopes, with pill counts from the same scopes and a billing badge per row.
    - Tests: each filter returns exactly the scope's rows, counts equal rows, badges rendered.
    - _Requirements: 9.1, 9.2, 9.3, 13.4_

  - [ ] 7.4 Update the domain edit page
    - Add the "Not managed by us" checkbox, a billing panel with status, `billing_error` and the Stripe dashboard link, and a persistent `role="alert"` warning with a "Retry billing" button for `billing_failed`, `payment_failed` and `subscription_deleted`.
    - Tests: toggle saves and excludes the domain from the billable scope while keeping the subscription id, warning and button shown only in those states.
    - _Requirements: 9.4, 9.5, 9.6, 9.7_

- [ ] 8. Order attention flag
  - [ ] 8.1 Set the flag in fulfilment and cancellation
    - `FulfilmentService::holdOrderForDomainConflict()` and `OrderCancellationService::compensateStripe()` set `needs_attention` and `attention_reason` alongside the existing note.
    - Tests: flag set once on repeat delivery, set on compensation failure, existing note behaviour unchanged.
    - _Requirements: 12.2, 12.3_

  - [ ] 8.2 Clear the flag and add the order list filter
    - Clear the flag on `fulfil` and `cancel`, add `POST shop/orders/{order}/resolve-attention` (`admin.shop.orders.resolve-attention`), and add a `needs_attention` filter on the order list using `Order::needsAttention()`.
    - Tests: each clearing path, filter returns only flagged orders, admin only.
    - _Requirements: 12.4, 12.5_

- [ ] 9. Checkpoint - webhooks, screens and order flag
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

- [ ] 10. Fold command (one-off cleanup)
  - [ ] 10.1 Implement `ServiceReferences`
    - Find every table with a foreign key to `services.service_id` using `Schema::getTables()` and `Schema::getForeignKeys()`, and count referencing rows for a service.
    - Tests: finds `order_items`, `tickets` and `customer_apps`, counts rows, picks up a table added in the test.
    - _Requirements: 10.6, 10.7, 10.8_

  - [ ] 10.2 Implement the `DomainBillingFold` planner with the generalised detectors
    - `plan(): FoldPlan` is read-only. Detector 1 plans `MoveSubscription` and `RetireService` for `Other` services holding an active Domain_Price subscription that resolve to a domain. Detector 2 plans a move from a domain to the service named in `metadata.service_id`. Use read-only Stripe calls and skip steps on unexpected price, status or read failure.
    - Print every step through a plan renderer.
    - Tests on a production-shaped fixture (services 139/140 with domains 32/34): planned moves, delete versus cancel, skipped steps.
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.9, 10.16_

  - [ ] 10.3 Add the explicit fix-ups
    - Add the reviewed `FIXUPS` list: Giant's Seat (domains 35 and 36, service 142) and Greenmount (domain 9). Re-check every pre-condition at plan time and turn failures into `Skip` steps.
    - Tests: expected steps on the production-shaped fixture, each failed pre-condition skips.
    - _Requirements: 10.10, 10.11, 10.12_

  - [ ] 10.4 Implement apply with the change log
    - Apply step groups, each in one transaction, clearing a source subscription id before setting the target. Log every column through `ServiceDataCleanupChange::record('domain-billing-fold', ...)`, including `__row__` snapshots for deleted services and `deleted_at` for domains. Continue after a failed group and report it.
    - Tests: applied result matches the plan, log rows, failed group rolls back alone, second apply writes nothing.
    - _Requirements: 10.5, 10.13, 10.15, 10.17_

  - [ ] 10.5 Add revert and the `domains:fold-billing` command
    - `--revert` replays the tagged log in reverse inside one transaction, restoring columns and re-inserting deleted rows with their original primary keys, then removes those log rows. Never call Stripe.
    - Register the command (dry run default, `--apply`, `--revert`), log runs to `ScheduledTaskLog`, and add it to the Scheduled Tasks whitelist as a dry run only.
    - Tests: dry run writes nothing, apply then revert restores a full snapshot, whitelist is dry run only, no Stripe write call in any mode.
    - _Requirements: 10.1, 10.3, 10.13, 10.14, 10.18_

  - [ ]* 10.6 Write property tests for the fold command
    - **Property 12: Fold dry run writes nothing and never writes to Stripe**
    - **Property 13: Fold planning rules**
    - **Property 14: Fold apply is idempotent and reversible**
    - **Validates: Requirements 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9, 10.13, 10.14, 10.15, 10.16, 10.17**

- [ ] 11. Checkpoint - fold command
  - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

- [ ] 12. Orphaned subscription report
  - [ ] 12.1 Implement `stripe:orphan-report`
    - Read active subscriptions on the Domain_Price (or any price with `--all`), compare with `domains.stripe_subscription_id` (including trashed) and `services.stripe_subscription_id`, and log `{orphans (max 200), total, scope}` in `ScheduledTaskLog`. Log a failed entry on error.
    - Schedule weekly on Sunday at 05:00 with `Schedule::call`, and add the command to the Scheduled Tasks whitelist.
    - Tests: listing, `--all`, cap with true total, read-only Stripe, failure logging, schedule and whitelist.
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.8_

  - [ ] 12.2 Add the orphan list page
    - Add `GET admin/stripe/orphans` (`admin.stripe.orphans`) showing the latest completed run with links to the Stripe dashboard, and a friendly message when no run exists.
    - Tests: list shown, empty state, admin only.
    - _Requirements: 11.7_

  - [ ]* 12.3 Write property test for the orphan report
    - **Property 15: Orphan listing**
    - **Validates: Requirements 11.1, 11.2, 11.4**

- [ ] 13. Admin attention dashboard
  - [ ] 13.1 Implement `ScheduledTaskHealth`
    - Report failed and overdue tasks for `enom:sync`, `stripe:sync`, `domains:ensure-billing`, `hosting:fail-stale` and `heartbeat` using a fixed number of queries and the allowances from the design.
    - Tests: failed, overdue, running, completed, no rows.
    - _Requirements: 13.7, 13.8_

  - [ ] 13.2 Implement `AdminAttentionService` and `AttentionItem`
    - Build the items from the shared scopes (one count query per item), the latest orphan-report log, and `ScheduledTaskHealth`. Return only items with a count above zero, each with label, count, URL and accessible name. Cache nothing.
    - Tests: each item's count, link target, zero items hidden.
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6_

  - [ ] 13.3 Add the linked-screen filters for services and hosting
    - Add `billing=failed` to the services index and `status=stuck` to the provisioning queue, both using the new `Service` scopes.
    - Tests: each filter returns exactly the scope's rows.
    - _Requirements: 13.2, 13.4_

  - [ ] 13.4 Add the "Needs attention" panel to the dashboard
    - Pass `$attentionItems` from `DashboardController@index` and render the `admin/partials/attention-panel` partial: a `section` with a heading and a list, text label and count in each link, an accessible name, and nothing rendered when the list is empty.
    - Tests: hidden at zero, items and links shown, accessible names, admin only, no cache.
    - _Requirements: 13.1, 13.3, 13.6, 13.9, 13.10_

  - [ ]* 13.5 Write property test for counts and filters
    - **Property 16: Attention counts equal filtered list counts**
    - **Validates: Requirements 9.1, 9.2, 12.5, 13.1, 13.2, 13.3, 13.4**

  - [ ]* 13.6 Write property test for the query count
    - **Property 17: Constant query count**
    - **Validates: Requirements 13.5**

  - [ ]* 13.7 Write property test for task health
    - **Property 18: Scheduled task health**
    - **Validates: Requirements 13.7, 13.8**

- [ ] 14. Documentation and final check
  - [ ] 14.1 Document the workflow and rollout in `DEPLOYMENT.md`
    - Add a "Domain billing" section with the workflow, the rollout steps from the Notes below, and the rule that no subscription is created until `domains:ensure-billing --apply` runs.
    - Add a source-scan test that no code outside eNom reads calls a registration endpoint and that the fold and orphan classes call no Stripe write method.
    - _Requirements: 3.8, 10.3, 11.3, 14.5_

  - [ ] 14.2 Final checkpoint - all tests pass
    - Ensure all tests pass and compare any failures with `baseline-failures.md`, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP.
- Each task references specific requirements. Property tasks reference the numbered properties in the design.
- Production rollout order: back up the database, deploy, run `php artisan migrate --force`, then run `php artisan domains:fold-billing` as a dry run on production and review every printed change with the owner. Only then run `php artisan domains:fold-billing --apply`. `--revert` restores the logged changes if something looks wrong. The fold never calls a Stripe write API.
- After the fold, run `php artisan domains:ensure-billing` as a dry run and review the list of domains and billing dates. No Stripe subscription is created by the migrations, the fold, deploys or the settings screen. Subscriptions are created only when `domains:ensure-billing --apply` runs (by hand or by the 03:30 schedule), after an eNom sync gives a domain an expiry, or after an admin assigns, saves or retries a domain. To keep the schedule from billing before review, run the first `--apply` manually before the next 03:30 run, or deploy the schedule entry in a second release.
- Check the Domain_Price on the settings screen ("Check price") before the first `--apply`.
- The first scheduled run of `stripe:orphan-report` may list existing subscriptions that are legitimately unlinked, such as the Giant's Seat subscription before the fold. Run the fold first.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.5"] },
    { "id": 3, "tasks": ["2.3", "2.6"] },
    { "id": 4, "tasks": ["2.4", "3.1", "3.2"] },
    { "id": 5, "tasks": ["3.3", "3.4"] },
    { "id": 6, "tasks": ["3.5", "4.1", "4.3", "6.1"] },
    { "id": 7, "tasks": ["4.2", "4.4", "6.2"] },
    { "id": 8, "tasks": ["4.5", "6.3"] },
    { "id": 9, "tasks": ["7.1", "7.2"] },
    { "id": 10, "tasks": ["7.3", "8.1"] },
    { "id": 11, "tasks": ["7.4", "8.2"] },
    { "id": 12, "tasks": ["10.1", "12.1"] },
    { "id": 13, "tasks": ["10.2", "12.2"] },
    { "id": 14, "tasks": ["10.3", "12.3"] },
    { "id": 15, "tasks": ["10.4"] },
    { "id": 16, "tasks": ["10.5"] },
    { "id": 17, "tasks": ["10.6"] },
    { "id": 18, "tasks": ["13.1", "13.3"] },
    { "id": 19, "tasks": ["13.2"] },
    { "id": 20, "tasks": ["13.4"] },
    { "id": 21, "tasks": ["13.5", "13.6", "13.7"] },
    { "id": 22, "tasks": ["14.1"] }
  ]
}
```
