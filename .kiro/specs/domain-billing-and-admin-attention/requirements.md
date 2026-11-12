# Requirements Document

## Introduction

Domains are renewed through eNom, but only some have a Stripe subscription, so renewals can be missed. This feature makes the Domain the owner of its yearly renewal subscription, creates that subscription automatically once eNom reports an expiry date, folds the duplicate "Domain Name - Annual Registration Fee" services into their domains, and surfaces everything that needs an admin on a "Needs attention" dashboard panel.

Scope:

1. Domain billing rule, shared price setting, and an idempotent `DomainBillingService::ensure()` step.
2. Triggers (eNom sync, customer assignment, manual save, retry) and the `domains:ensure-billing` command.
3. Webhook handling for domain subscriptions.
4. Domain screens: filters, badges, toggle, retry.
5. A one-off, reversible `domains:fold-billing` cleanup of production data.
6. A weekly report of Stripe subscriptions that the CRM does not know about.
7. The admin attention dashboard and the `orders.needs_attention` flag it needs.

The CRM never registers domains in eNom, and this feature keeps it that way. Production now has SSH, but scheduled work still runs through `Schedule::call(fn () => Artisan::call(...))` because `proc_open` is disabled.

## Glossary

- **CRM**: The Laravel application.
- **Domain**: A row in `domains`. Soft deleted rows are "trashed".
- **Domain_Billing_Service**: The service class whose `ensure(Domain)` creates the subscription.
- **Billable_Domain**: A Domain matching the billable definition in Requirement 1.
- **Exempt_Company**: The customer whose `company_id` equals the setting `domain_billing_exempt_company_id` (default 1, CK Enterprises UK).
- **Domain_Price**: The Stripe price identified by the setting `domain_stripe_price_id` (default `price_1QXM7mKOtRMQ2ybuHlnPu2hx`).
- **Domain_Subscription**: A Stripe subscription on the Domain_Price whose id is stored in `domains.stripe_subscription_id`.
- **Managed_Externally**: The boolean `domains.managed_externally`, labelled "Not managed by us".
- **Billing_State**: The nullable string `domains.billing_state`. Values: `creating`, `billing_failed`, `payment_failed`, `subscription_deleted`.
- **Awaiting_eNom**: A non-exempt, managed, non-trashed Domain with a customer and no `expiry_date`.
- **Ensure_Command**: The artisan command `domains:ensure-billing`.
- **Fold_Command**: The artisan command `domains:fold-billing`.
- **Orphan_Report**: The artisan command `stripe:orphan-report`.
- **Webhook_Controller**: `StripeWebhookController`.
- **Attention_Service**: The class that computes the panel items.
- **Attention_Panel**: The "Needs attention" panel on the admin dashboard.
- **Admin**: An authenticated user passing `EnsureIsAdmin`.
- **Cleanup_Log**: The `service_data_cleanup_changes` table.
- **Task_Log**: The `scheduled_task_logs` table (`ScheduledTaskLog`).

## Requirements

### Requirement 1: Billable domain definition

**User Story:** As an admin, I want one clear rule for which domains need a subscription, so that no renewal is missed and no exempt domain is billed.

#### Acceptance Criteria

1. THE CRM SHALL treat a Domain as a Billable_Domain only when `company_id` is not null, `company_id` is not the Exempt_Company id, Managed_Externally is false, the Domain is not trashed, `expiry_date` is not null, and `stripe_subscription_id` is null or empty.
2. THE CRM SHALL expose the billable definition as one Domain model scope that the Ensure_Command, the domain index, the Attention_Service and the tests all use.
3. THE CRM SHALL expose the Awaiting_eNom definition as one Domain model scope.
4. THE CRM SHALL store the Exempt_Company id in the setting `domain_billing_exempt_company_id` with the default value 1.
5. WHEN a Domain is not a Billable_Domain, THE Domain_Billing_Service SHALL create no Stripe object for the Domain.

### Requirement 2: Shared domain price

**User Story:** As an admin, I want one editable Stripe price for every domain subscription, so that pricing changes do not need a deployment.

#### Acceptance Criteria

1. THE CRM SHALL store the Domain_Price id in the setting `domain_stripe_price_id`.
2. WHEN the migration runs and the setting is absent, THE CRM SHALL set `domain_stripe_price_id` to `price_1QXM7mKOtRMQ2ybuHlnPu2hx` and `domain_billing_exempt_company_id` to 1.
3. THE CRM SHALL provide an admin settings screen to edit `domain_stripe_price_id` and `domain_billing_exempt_company_id`.
4. WHEN an Admin presses "Check price" on the settings screen, THE CRM SHALL fetch the price from Stripe and show its product name, amount, currency and interval.
5. THE CRM SHALL make no Stripe request when the settings screen loads.
6. WHEN an Admin saves a price id, THE CRM SHALL reject the value unless Stripe returns a price that is active and recurring with interval `year` and interval count 1.
7. IF Stripe is unreachable while an Admin saves a price id, THEN THE CRM SHALL keep the stored value and show the error.
8. WHEN the Domain_Billing_Service is about to create a subscription, THE Domain_Billing_Service SHALL validate the Domain_Price against the rules in criterion 6.
9. IF the Domain_Price is missing, inactive, or not yearly, THEN THE Domain_Billing_Service SHALL set the Domain Billing_State to `billing_failed` with an error naming the price id and the failed rule, and SHALL create no subscription.

### Requirement 3: Create the subscription

**User Story:** As an admin, I want a subscription created automatically for each billable domain, so that renewals are invoiced without manual Stripe work.

#### Acceptance Criteria

1. WHEN `ensure()` runs for a Billable_Domain, THE Domain_Billing_Service SHALL create one Stripe subscription on the Domain_Price with `collection_method` `send_invoice`, `days_until_due` 7 and proration behaviour `none`.
2. THE Domain_Billing_Service SHALL set the subscription metadata to `company_id`, `domain_id` and `domain_name`.
3. THE Domain_Billing_Service SHALL obtain the Stripe customer through `StripeService::ensureCustomerWithEmail`.
4. WHEN Stripe returns the subscription, THE Domain_Billing_Service SHALL store its id in `domains.stripe_subscription_id` and clear Billing_State and `billing_error`.
5. IF any Stripe call fails, THEN THE Domain_Billing_Service SHALL set Billing_State to `billing_failed`, store the error message in `billing_error`, and leave `stripe_subscription_id` unchanged.
6. THE Domain_Billing_Service SHALL create the subscription outside any open database transaction.
7. WHEN `ensure()` is called inside a database transaction, THE Domain_Billing_Service SHALL defer the Stripe call until after commit.
8. THE CRM SHALL never register, renew or transfer a domain in eNom.

### Requirement 4: Billing date

**User Story:** As an admin, I want no charge until the domain is due, so that customers are billed once at renewal.

#### Acceptance Criteria

1. WHEN the Domain `expiry_date` is later than today and at most 725 days away, THE Domain_Billing_Service SHALL set the subscription `trial_end` to 00:00 UTC on the expiry date.
2. WHEN the Domain `expiry_date` is more than 725 days away, THE Domain_Billing_Service SHALL set `trial_end` to 00:00 UTC on the latest anniversary of the expiry date that is later than today and at most 725 days away.
3. IF the Domain `expiry_date` is today or earlier, THEN THE Domain_Billing_Service SHALL set Billing_State to `billing_failed` with an error asking the Admin to update the expiry or mark the Domain as not managed, and SHALL create no subscription.
4. THE Domain_Billing_Service SHALL compute the billing date in one pure function that takes the expiry date and today's date.
5. THE Domain_Billing_Service SHALL create no invoice item and no immediate charge.

### Requirement 5: Idempotence and concurrency

**User Story:** As an admin, I want a domain never to get two subscriptions, so that customers are not double billed.

#### Acceptance Criteria

1. WHEN `ensure()` runs for a Domain that already has a `stripe_subscription_id`, THE Domain_Billing_Service SHALL make no Stripe write call and return the existing id.
2. WHEN `ensure()` runs, THE Domain_Billing_Service SHALL claim the Domain with one atomic conditional update to Billing_State `creating` before any Stripe write call.
3. IF the claim updates no row, THEN THE Domain_Billing_Service SHALL make no Stripe call.
4. WHEN a claim finds Billing_State `creating` older than 10 minutes, THE Domain_Billing_Service SHALL treat the claim as stale and retry it.
5. BEFORE creating a subscription, THE Domain_Billing_Service SHALL look for an existing Stripe subscription for the same Stripe customer on the Domain_Price with metadata `domain_id` equal to the Domain id, and SHALL store that subscription instead of creating another.
6. THE Domain_Billing_Service SHALL send a Stripe idempotency key built from the Domain id and the billing date.
7. FOR ALL sequences of one or more `ensure()` calls on the same Domain, THE CRM SHALL create at most one Domain_Subscription.

### Requirement 6: Triggers

**User Story:** As an admin, I want billing set up at the moment a domain becomes billable, so that I do not have to remember to run anything.

#### Acceptance Criteria

1. WHEN the eNom sync gives a Domain an expiry date that it did not have before, THE CRM SHALL call `ensure()` for that Domain after the sync transaction commits.
2. WHEN an Admin assigns a Domain to a customer through the inline assign form, THE CRM SHALL call `ensure()` for that Domain after the change commits.
3. WHEN an Admin changes the customer of a Domain from the Exempt_Company or from no customer to another customer, THE CRM SHALL call `ensure()` for that Domain after the change commits.
4. WHEN an Admin saves a Domain through the create or edit form, THE CRM SHALL call `ensure()` for that Domain after the save commits.
5. WHEN an Admin submits `POST admin/domains/{domain}/retry-billing`, THE CRM SHALL call `ensure()` with force enabled and show the outcome.
6. IF `ensure()` fails in an automatic trigger, THEN THE CRM SHALL complete the originating action and report the failure through Billing_State rather than an HTTP error.
7. WHEN an Admin changes the customer of a Domain that already has a `stripe_subscription_id`, THE CRM SHALL show a warning that the subscription still belongs to the previous Stripe customer and SHALL leave the subscription unchanged.
8. WHERE a Domain has Billing_State `subscription_deleted`, THE CRM SHALL skip the Domain in every automatic trigger and in the Ensure_Command, and SHALL recreate the subscription only on an Admin retry.

### Requirement 7: Ensure command and schedule

**User Story:** As an admin, I want a safe bulk run and a daily safety net, so that missed domains are caught.

#### Acceptance Criteria

1. THE Ensure_Command SHALL run as a dry run by default, print every Billable_Domain it would bill with its billing date, and write nothing.
2. WHEN the Ensure_Command runs with `--apply`, THE Ensure_Command SHALL call `ensure()` for each Billable_Domain and print the outcome per Domain.
3. THE Ensure_Command SHALL record each run in the Task_Log with counts of billed, skipped and failed Domains.
4. THE CRM SHALL list `domains:ensure-billing` in the admin Scheduled Tasks whitelist and run it from the UI as a dry run only.
5. THE CRM SHALL schedule `domains:ensure-billing --apply` daily at 03:30 through `Schedule::call(fn () => Artisan::call(...))`.
6. WHEN a Domain is Awaiting_eNom, THE Ensure_Command SHALL list it as "awaiting eNom" and SHALL NOT mark it failed.

### Requirement 8: Webhook handling for domain subscriptions

**User Story:** As an admin, I want payment failures and cancellations on domain subscriptions recorded, so that I see them without opening Stripe.

#### Acceptance Criteria

1. WHEN `invoice.payment_failed` carries a subscription id that matches a Domain and no Service, THE Webhook_Controller SHALL set the Domain Billing_State to `payment_failed` and store the failure reason in `billing_error`.
2. WHEN a domain payment failure is recorded, THE Webhook_Controller SHALL notify admins after the transaction commits.
3. WHEN `customer.subscription.deleted` carries a subscription id that matches a Domain, THE Webhook_Controller SHALL clear `stripe_subscription_id`, set Billing_State to `subscription_deleted`, and record the deletion in `billing_error`.
4. WHEN `invoice.paid` carries a subscription id that matches a Domain with Billing_State `payment_failed`, THE Webhook_Controller SHALL clear Billing_State and `billing_error`.
5. THE Webhook_Controller SHALL apply domain changes inside the same transaction as the `processed_webhook_events` marker.
6. WHEN the same Stripe event is delivered more than once, THE Webhook_Controller SHALL change a Domain and notify admins at most once.
7. IF a domain handler throws, THEN THE Webhook_Controller SHALL roll back the marker and the changes and return HTTP 500.
8. WHEN `stripe:sync` sees a Domain subscription with Stripe status `canceled` or `incomplete_expired`, THE CRM SHALL clear that Domain's `stripe_subscription_id` and set Billing_State to `subscription_deleted`.
9. WHEN a subscription id matches a Service, THE Webhook_Controller SHALL keep the existing Service behaviour.

### Requirement 9: Domain screens

**User Story:** As an admin, I want to find and fix domains with billing problems from the domain screens.

#### Acceptance Criteria

1. THE domain index SHALL provide a "No subscription" filter whose count and rows both come from the billable scope.
2. THE domain index SHALL provide an "Awaiting eNom" filter and a "Not managed" filter, each with a count that equals the number of rows listed.
3. THE domain index SHALL show a billing badge per row with a text label and an accessible name for the states: Billed, No subscription, Awaiting eNom, Exempt, Not managed, Billing failed, Payment failed.
4. THE domain edit page SHALL provide a "Not managed by us" toggle.
5. THE domain edit page SHALL show the billing status, the `billing_error` when set, and the subscription id as a link to the Stripe dashboard.
6. WHILE Billing_State is `billing_failed`, `payment_failed` or `subscription_deleted`, THE domain edit page SHALL show a persistent warning with a "Retry billing" button.
7. WHEN an Admin turns on "Not managed by us", THE CRM SHALL exclude the Domain from the billable scope without changing `stripe_subscription_id`.
8. THE CRM SHALL protect every new domain route with the existing admin middleware group.

### Requirement 10: Fold duplicate domain services into domains

**User Story:** As an admin, I want existing production data cleaned so each domain owns its subscription, without touching Stripe.

#### Acceptance Criteria

1. THE Fold_Command SHALL run as a dry run by default, print every planned change, and write nothing.
2. WHEN the Fold_Command runs, THE Fold_Command SHALL read each affected Stripe subscription with read-only calls to verify its price and status before planning a move.
3. THE Fold_Command SHALL never call a Stripe write API.
4. WHEN a Service of type `Other` holds a subscription on the Domain_Price and resolves to a Domain through `domain_id` or a matching `domain_name`, THE Fold_Command SHALL plan to move the subscription id onto the Domain.
5. WHEN a Domain has no subscription and the Service holds an active one, THE Fold_Command SHALL clear the Service `stripe_subscription_id` before setting the Domain value, so the unique index holds.
6. WHEN a moved-from Service has no reference in any table with a foreign key to `services.service_id`, THE Fold_Command SHALL delete the Service.
7. WHEN a moved-from Service has a reference, THE Fold_Command SHALL set its status to Cancelled and append the admin note "Billing moved to domain #N".
8. THE Fold_Command SHALL find the tables that reference `services.service_id` from the schema at run time.
9. WHEN a Domain holds a subscription whose price is not the Domain_Price and whose metadata `service_id` names an existing Service with an empty `stripe_subscription_id`, THE Fold_Command SHALL plan to move the subscription to that Service.
10. THE Fold_Command SHALL carry a reviewed list of explicit fix-ups for domains 9, 35, 36 and services 142 and SHALL skip any fix-up whose recorded pre-conditions no longer hold, reporting the skip.
11. THE explicit Giant's Seat fix-up SHALL move `sub_1UZzJqKOtRMQ2ybupKDl6BAs` from domain 36 to service 142, set domain 35's subscription to `sub_1UZzIGKOtRMQ2ybuTljfxRoL`, point service 142 at domain 35 with `domain_name` `giantsseat.org.uk`, and soft delete domain 36.
12. THE explicit Greenmount fix-up SHALL set `managed_externally` on domain 9.
13. WHEN the Fold_Command runs with `--apply`, THE Fold_Command SHALL record every change in the Cleanup_Log under the migration tag `domain-billing-fold`, including a full-row snapshot for each deleted Service.
14. WHEN the Fold_Command runs with `--revert`, THE Fold_Command SHALL restore every logged change for its tag in reverse order.
15. WHEN the Fold_Command runs with `--apply` a second time, THE Fold_Command SHALL plan and write no change.
16. IF a Stripe read fails or returns an unexpected price or status, THEN THE Fold_Command SHALL skip that step, report it, and continue with independent steps.
17. THE Fold_Command SHALL apply each step group inside one database transaction.
18. THE CRM SHALL list `domains:fold-billing` in the admin Scheduled Tasks whitelist as a dry run only.

### Requirement 11: Orphaned subscription report

**User Story:** As an admin, I want to know about Stripe subscriptions the CRM has lost track of, so that nobody is billed for nothing.

#### Acceptance Criteria

1. THE Orphan_Report SHALL list active Stripe subscriptions on the Domain_Price whose id matches no `domains.stripe_subscription_id` and no `services.stripe_subscription_id`.
2. WHERE the option `--all` is given, THE Orphan_Report SHALL list active subscriptions on any price that match no Domain and no Service.
3. THE Orphan_Report SHALL make read-only Stripe calls only.
4. THE Orphan_Report SHALL record each run in the Task_Log with the orphan count and a list capped at 200 entries holding subscription id, customer, price id and creation date.
5. THE CRM SHALL schedule `stripe:orphan-report` weekly on Sunday at 05:00 through `Schedule::call(fn () => Artisan::call(...))`.
6. THE CRM SHALL list `stripe:orphan-report` in the admin Scheduled Tasks whitelist.
7. THE CRM SHALL provide an admin page that lists the orphans from the latest completed run.
8. IF the Orphan_Report fails, THEN THE Orphan_Report SHALL record a failed Task_Log entry with the error.

### Requirement 12: Order attention flag

**User Story:** As an admin, I want held shop orders flagged properly, so that I do not rely on free-text notes.

#### Acceptance Criteria

1. THE CRM SHALL add `orders.needs_attention` (boolean, default false) and `orders.attention_reason` (nullable string).
2. WHEN FulfilmentService holds an order because a domain is already in use, THE FulfilmentService SHALL set `needs_attention` true and store the reason.
3. WHEN OrderCancellationService fails to compensate a Stripe object, THE OrderCancellationService SHALL set `needs_attention` true and store the reason.
4. WHEN an Admin fulfils, cancels, or presses "Resolve" on an order, THE CRM SHALL clear `needs_attention` and `attention_reason`.
5. THE admin order list SHALL provide a "Needs attention" filter that uses the same model scope as the Attention_Service.
6. WHEN the migration runs, THE CRM SHALL set `needs_attention` for existing orders whose `admin_notes` contain a line starting "Held: domain".

### Requirement 13: Admin attention dashboard

**User Story:** As an admin, I want one panel that lists everything needing action, so that I find problems on login.

#### Acceptance Criteria

1. THE Attention_Panel SHALL show only items with a count above zero and SHALL be hidden when every count is zero.
2. THE Attention_Panel SHALL show these items: hosting provisioning failed, hosting provisioning stuck (running over 15 minutes), service billing failed, domains with no subscription, domains awaiting eNom, domains with billing failed or payment failed, domains missing from eNom, unassigned domains, domains expiring within 30 days without auto-renew, shop orders needing attention, orphaned Stripe subscriptions, and failed or overdue scheduled tasks.
3. THE Attention_Panel SHALL show for each item a text label, a count, an accessible name that includes label and count, and a link to the screen filtered to the same rows.
4. THE Attention_Service SHALL compute each count from the same model scope that the linked screen uses.
5. THE Attention_Service SHALL run a number of database queries that does not depend on the number of rows.
6. THE Attention_Service SHALL cache nothing.
7. THE CRM SHALL treat a scheduled task as failed WHEN its latest Task_Log entry for `enom:sync`, `stripe:sync`, `domains:ensure-billing` or `hosting:fail-stale` has status `failed`.
8. THE CRM SHALL treat a scheduled task as overdue WHEN its latest completed entry is older than its allowance: 36 hours for `enom:sync`, `stripe:sync` and `domains:ensure-billing`, 30 minutes for `hosting:fail-stale`, and 15 minutes for `heartbeat`.
9. THE CRM SHALL show the Attention_Panel only to an Admin.
10. THE Attention_Panel SHALL use semantic HTML with a heading and a list, and SHALL NOT rely on colour alone.

### Requirement 14: Operating workflow

**User Story:** As an admin, I want the order of operations documented and enforced, so that a new domain customer ends up billed.

#### Acceptance Criteria

1. WHEN an eNom sync brings in a Domain with no customer, THE CRM SHALL list it as unassigned and create no subscription.
2. WHEN a Web Hosting Service is created, THE CRM SHALL create or link its Domain through the existing DomainLinker, and SHALL create no second subscription for it.
3. WHILE a Domain has a customer and no expiry date, THE CRM SHALL show it as "Awaiting eNom" and SHALL NOT set a billing error.
4. WHEN Managed_Externally is set on a Domain created by hand, THE CRM SHALL list it as "Not managed" and SHALL NOT bill it.
5. THE design document SHALL describe the workflow: register in eNom, create the Web Hosting Service, background cPanel provisioning, eNom sync fills the expiry, `ensure()` creates the subscription.

## Correctness Properties for Testing

These properties are detailed in the design document and are implemented as seeded PHPUnit loops, matching the previous spec.

1. Billable set equals the definition in Requirement 1 for any generated data.
2. Exempt, externally managed, unassigned, trashed and expiry-less domains are never billed.
3. Repeated and concurrent `ensure()` never creates a second subscription.
4. `trial_end` equals the expiry date, or its latest in-window anniversary.
5. Domain webhook handling is exactly-once and atomic.
6. A deleted subscription is never recreated automatically.
7. The Fold_Command dry run writes nothing, apply is idempotent, and revert restores the original data.
8. Dashboard counts equal the filtered list counts, with a constant query count.
