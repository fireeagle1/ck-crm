# Requirements Document

## Introduction

This feature cleans up the data model and workflows around services, domains, web apps (CustomerApp), WHM/cPanel provisioning, eNom domain sync, and shop checkout and fulfilment in the Laravel CRM. It removes inconsistent status and type values, duplicate Service creation, double stock decrements, unsafe Stripe and webhook handling, duplicate WHM implementations, unnormalised domain storage, destructive sync and ingest behaviour, and weak validation. It also makes the admin UI usable for the resulting data, including a provisioning queue that shows shop-bought hosting.

Delivery is phased in this order: (1) data model, (2) shop and webhook, (3) WHM, (4) domains and eNom, (5) web apps, (6) admin UI.

Constraints:
- Production holds 19 hand-created services that already use the label values, with no duplicate domains or cpanel_username values, so unique indexes can be added safely.
- TLS verification is enabled for every WHM connection.
- A single WHM client exists after this work.
- Implementation follows existing Laravel conventions in the repository.

## Glossary

- **CRM**: The Laravel application that manages customers, services, domains, orders and web apps.
- **Service**: A record in the services table representing something delivered to a customer.
- **ServiceType**: The enum or constants set containing 'Technical Support', 'Web Hosting' and 'Other'.
- **ServiceStatus**: The enum or constants set containing 'Pending', 'Active', 'Suspended' and 'Cancelled'.
- **Domain**: A record in the domains table representing a registered domain name.
- **Domain_Normaliser**: The component that converts a domain input into canonical form.
- **Canonical_Domain**: A domain name that is trimmed, lowercased, stripped of a leading "www.", stripped of a trailing dot, and converted to punycode (ASCII) form.
- **CustomerApp**: A web app record linked one-to-one with a Service.
- **Checkout_Service**: The CheckoutService class that creates orders, Stripe Checkout Sessions and Subscriptions.
- **Fulfilment_Service**: The FulfilmentService class that fulfils paid orders.
- **Webhook_Controller**: The StripeWebhookController that receives Stripe webhook events.
- **Webhook_Marker**: A row in processed_webhook_events recording a processed Stripe event ID.
- **Cleanup_Command**: The CleanupAbandonedOrdersCommand artisan command.
- **WHM_Client**: The single WhmService class that communicates with WHM using encrypted Setting credentials.
- **Provisioning_Job**: The queued job that creates a cPanel account for a hosting Service through the WHM_Client.
- **Provisioning_Status**: The state of provisioning for a Service: 'queued', 'running', 'succeeded' or 'failed'.
- **Provisioning_Queue**: The admin screen listing hosting Services with their Provisioning_Status.
- **Enom_Sync**: The EnomSyncDomains artisan command that imports domain data from eNom.
- **Enom_Fields**: The Domain fields owned by Enom_Sync: expiry_date, auto_renew, registrar and enom_domain_name_id.
- **Admin_Edited_Field**: A Domain field whose value an admin has changed through the CRM.
- **Domain_Sync_Status**: The Domain status column with values including 'active' and 'missing_from_enom'.
- **Findings_Ingest_Service**: The FindingsIngestService that ingests app findings payloads.
- **Admin_UI**: The admin-facing screens and controllers of the CRM.
- **Admin_Service_API**: The Api/Admin ServiceController.
- **Admin_Service_Controller**: The Admin/ServiceController web controller.

## Requirements

### Requirement 1: Service type and status values (Phase 1: Data model)

**User Story:** As an admin, I want one consistent set of service type and status values, so that every service appears in the correct admin screens.

#### Acceptance Criteria

1. THE CRM SHALL define ServiceType with the values 'Technical Support', 'Web Hosting' and 'Other' in a single enum or constants class.
2. THE CRM SHALL define ServiceStatus with the values 'Pending', 'Active', 'Suspended' and 'Cancelled' in a single enum or constants class.
3. THE Admin_Service_Controller SHALL validate service_type and status against ServiceType and ServiceStatus on store and update.
4. THE Admin_Service_API SHALL validate service_type and status against ServiceType and ServiceStatus on create and update.
5. THE Admin_Service_API SHALL apply the same field validation rules as the Admin_Service_Controller.
6. THE Checkout_Service SHALL write 'Web Hosting' as service_type and 'Pending' as status for shop hosting Services.
7. WHEN the Webhook_Controller records a failed payment, THE Webhook_Controller SHALL set the Service status to 'Suspended'.
8. WHEN the Webhook_Controller records a cancelled subscription, THE Webhook_Controller SHALL set the Service status to 'Cancelled'.
9. WHEN the data migration runs, THE CRM SHALL convert existing service_type and status values that differ only by casing into the canonical ServiceType and ServiceStatus labels.
10. WHEN the data migration runs, THE CRM SHALL convert lowercase 'hosting' service_type values to 'Web Hosting' and lowercase 'pending' status values to 'Pending'.
11. IF the data migration finds a service_type or status value that matches no canonical label after normalising casing, THEN THE CRM SHALL leave the row unchanged and write the row ID and value to the application log.

### Requirement 2: Database integrity for services (Phase 1: Data model)

**User Story:** As an admin, I want the database to reject duplicate cPanel usernames and duplicate service domains, so that data stays consistent under concurrent writes.

#### Acceptance Criteria

1. THE CRM SHALL add a unique index on services.cpanel_username that permits multiple NULL values.
2. THE CRM SHALL add a unique index on services.domain_name that permits multiple NULL values.
3. WHEN the migration adds a unique index, THE CRM SHALL store Canonical_Domain values in services.domain_name before the index is created.
4. IF the migration detects duplicate values that would violate a unique index, THEN THE CRM SHALL abort the migration and write the duplicate values to the application log.
5. THE CRM SHALL add a provisioning_status column and a provisioning_error column to the services table.
6. THE CRM SHALL add nullable domain_id foreign key columns to the services table and to the order_items table referencing domains.id.

### Requirement 3: Idempotent Service creation from orders (Phase 2: Shop and webhook)

**User Story:** As an admin, I want exactly one Service per hosting order item, so that customers are not billed or provisioned twice.

#### Acceptance Criteria

1. THE CRM SHALL record the originating order_item_id on each Service created from an order.
2. THE CRM SHALL enforce a unique index on services.order_item_id that permits multiple NULL values.
3. WHEN the Checkout_Service or the Fulfilment_Service creates a hosting Service for an order item, THE CRM SHALL create that Service only if no Service exists for that order_item_id.
4. WHEN the Webhook_Controller delivers the same paid order twice, THE Fulfilment_Service SHALL leave exactly one Service per hosting order item.
5. THE CRM SHALL create hosting Services from a single code path shared by the Checkout_Service and the Fulfilment_Service.
6. WHEN a hosting Service is created by the shop, THE CRM SHALL link the Service to the order item and to the matching Domain by Canonical_Domain.

### Requirement 4: Single stock decrement and restock (Phase 2: Shop and webhook)

**User Story:** As a shop owner, I want stock decremented once per purchased unit, so that inventory counts are correct.

#### Acceptance Criteria

1. THE Checkout_Service SHALL decrement stock for a one-off item exactly once per order item, using the ordered quantity.
2. THE Fulfilment_Service SHALL perform no stock decrement for an item whose stock the Checkout_Service already decremented.
3. WHEN the Fulfilment_Service processes an order item with quantity greater than 1, THE CRM SHALL have decremented stock by that quantity in total.
4. WHEN the Cleanup_Command cancels an abandoned order, THE Cleanup_Command SHALL restore the stock decremented for each one-off item on that order.
5. WHEN the Cleanup_Command restocks an order, THE Cleanup_Command SHALL restock that order only once, including when the command runs again.
6. THE CRM SHALL perform each stock change inside a database transaction with a row lock on the product.

### Requirement 5: Checkout and Stripe call ordering (Phase 2: Shop and webhook)

**User Story:** As a developer, I want Stripe objects created outside the database transaction, so that a rollback never leaves orphaned Stripe objects.

#### Acceptance Criteria

1. THE Checkout_Service SHALL create the order, order items and stock changes inside a database transaction before calling Stripe.
2. THE Checkout_Service SHALL create the Stripe Checkout Session and Stripe Subscription after the database transaction commits.
3. IF a Stripe call fails after the order is committed, THEN THE Checkout_Service SHALL mark the order as failed, restock its one-off items, and return an error to the caller.
4. IF a Stripe object was created and a later step fails, THEN THE Checkout_Service SHALL cancel or expire that Stripe object.
5. THE CRM SHALL remove the legacy duplicate checkout methods from the Checkout_Service and keep a single checkout code path.
6. THE CRM SHALL read the currency code from a single configuration value with default 'gbp' instead of hardcoded literals.

### Requirement 6: Reliable webhook processing (Phase 2: Shop and webhook)

**User Story:** As a developer, I want each Stripe event processed exactly once, so that retries and concurrent deliveries are safe.

#### Acceptance Criteria

1. THE CRM SHALL enforce a unique index on the event ID column of processed_webhook_events.
2. WHEN the Webhook_Controller receives an event, THE Webhook_Controller SHALL insert the Webhook_Marker inside the same database transaction as the event's database changes.
3. IF the Webhook_Marker insert violates the unique index, THEN THE Webhook_Controller SHALL return HTTP 200 and skip processing for that event.
4. IF event processing throws an exception, THEN THE Webhook_Controller SHALL roll back the transaction including the Webhook_Marker and return a non-2xx HTTP status so Stripe retries.
5. THE Webhook_Controller SHALL dispatch payment-failed emails after the transaction commits.
6. WHEN the same payment-failed event is delivered more than once, THE Webhook_Controller SHALL send each payment-failed email once.

### Requirement 7: Single WHM client (Phase 3: WHM)

**User Story:** As a developer, I want one WHM client with verified TLS, so that credentials and behaviour are consistent and secure.

#### Acceptance Criteria

1. THE CRM SHALL provide the WHM_Client as the only class that issues WHM API requests.
2. THE WHM_Client SHALL read WHM host, username and token from encrypted Setting records.
3. THE WHM_Client SHALL verify TLS certificates on every WHM request.
4. THE Admin_Service_Controller SHALL call the WHM_Client in place of the provisionWhmAccount and fetchWhmAccountInfo methods.
5. THE SsoController SHALL call the WHM_Client in place of config('services.whm.*') values.
6. THE CRM SHALL remove WHM credential reads from .env-based configuration.
7. IF the WHM_Client cannot connect or receives an error response, THEN THE WHM_Client SHALL throw WhmConnectionException with the failure reason.

### Requirement 8: WHM provisioning configuration (Phase 3: WHM)

**User Story:** As an admin, I want package, nameservers and contact email to come from product and settings data, so that accounts are provisioned correctly.

#### Acceptance Criteria

1. WHEN the Provisioning_Job creates an account, THE WHM_Client SHALL use the products.whm_package value of the purchased product as the package name.
2. IF the purchased product has no whm_package value, THEN THE Provisioning_Job SHALL fail with a descriptive error and create no WHM account.
3. WHEN the Provisioning_Job creates an account, THE WHM_Client SHALL use the customer's email address as the contact email.
4. IF the customer has no email address, THEN THE Provisioning_Job SHALL fail with a descriptive error and create no WHM account.
5. THE CRM SHALL read nameservers from a single Setting value used by every screen and email that displays nameservers.
6. WHEN the Provisioning_Job generates a cPanel username, THE WHM_Client SHALL derive a candidate from the domain and append a numeric suffix until the candidate has no match in services.cpanel_username and no existing WHM account.
7. THE WHM_Client SHALL generate usernames that satisfy the cPanel username length and character rules.
8. WHEN the Provisioning_Job starts, THE WHM_Client SHALL check whether a WHM account already exists for the Service domain, and the Provisioning_Job SHALL link the existing account to the Service without creating a duplicate.
9. WHEN an admin maps a cPanel account in the CpanelMappingController, THE CpanelMappingController SHALL reject a cpanel_username already assigned to another Service.

### Requirement 9: Queued, idempotent provisioning (Phase 3: WHM)

**User Story:** As an admin, I want hosting provisioned automatically after payment with visible status and retry, so that customers receive accounts without manual work.

#### Acceptance Criteria

1. WHEN payment is confirmed for an order containing a Web Hosting item, THE Fulfilment_Service SHALL dispatch one Provisioning_Job for the resulting Service.
2. WHEN the Fulfilment_Service dispatches a Provisioning_Job, THE CRM SHALL set the Service provisioning_status to 'queued'.
3. THE Provisioning_Job SHALL acquire a lock keyed by Service ID before calling WHM.
4. WHILE a Provisioning_Job holds the lock for a Service, THE CRM SHALL prevent a second Provisioning_Job from running for that Service.
5. WHEN the Provisioning_Job starts, THE CRM SHALL set provisioning_status to 'running'.
6. WHEN the Provisioning_Job creates the account successfully, THE CRM SHALL save cpanel_username, set provisioning_status to 'succeeded', and set the Service status to 'Active' in a single database transaction.
7. IF the Provisioning_Job fails, THEN THE CRM SHALL set provisioning_status to 'failed', store the error message in provisioning_error, and leave no partial WHM-related fields on the Service.
8. WHEN the Provisioning_Job succeeds, THE CRM SHALL send the hosting provisioned notification after the database transaction commits.
9. IF the hosting provisioned notification fails to send, THEN THE CRM SHALL log the failure and keep provisioning_status as 'succeeded'.
10. WHEN the Provisioning_Job runs for a Service whose provisioning_status is 'succeeded', THE Provisioning_Job SHALL exit without calling WHM.
11. WHEN an admin triggers a retry on a Service with provisioning_status 'failed', THE CRM SHALL dispatch a new Provisioning_Job for that Service.
12. IF an admin triggers a retry on a Service whose provisioning_status is not 'failed', THEN THE CRM SHALL reject the request and leave the Service unchanged.
13. THE HostingProvisionController SHALL dispatch the Provisioning_Job in place of running provisioning synchronously.

### Requirement 10: Admin service detail without live WHM calls (Phase 3: WHM)

**User Story:** As an admin, I want service pages to load without waiting on WHM, so that the pages stay fast when WHM is slow or down.

#### Acceptance Criteria

1. WHEN an admin opens a Service show page, THE Admin_Service_Controller SHALL render the page without calling WHM.
2. WHEN an admin requests a WHM account info refresh, THE Admin_Service_Controller SHALL call the WHM_Client and display the result.
3. IF the WHM_Client throws WhmConnectionException during a refresh, THEN THE Admin_Service_Controller SHALL display the failure message and render the rest of the page.

### Requirement 11: Domain normalisation (Phase 4: Domains and eNom)

**User Story:** As an admin, I want domain names stored in one canonical form, so that duplicates are detected reliably.

#### Acceptance Criteria

1. THE Domain_Normaliser SHALL convert an input domain into its Canonical_Domain.
2. WHEN the Domain_Normaliser receives a value already in canonical form, THE Domain_Normaliser SHALL return the same value.
3. IF the input is not a syntactically valid domain name after normalisation, THEN THE Domain_Normaliser SHALL reject the input with a validation error.
4. THE Admin DomainController SHALL normalise domain_name before running the unique check and before saving.
5. THE Admin_Service_Controller SHALL normalise domain_name before running the duplicate check and before saving.
6. THE Admin_Service_API SHALL normalise domain_name before running the duplicate check and before saving.
7. THE CartService SHALL normalise and validate domains using the Domain_Normaliser.
8. THE CRM SHALL normalise domain_name values on checkout order items, and on Stripe metadata, using the Domain_Normaliser.
9. WHEN the data migration runs, THE CRM SHALL convert existing domains.domain_name, services.domain_name and order_items.domain_name values to Canonical_Domain.

### Requirement 12: Domain and service linking (Phase 4: Domains and eNom)

**User Story:** As an admin, I want services linked to their domains by foreign key, so that a domain has one source of truth.

#### Acceptance Criteria

1. WHEN the data migration runs, THE CRM SHALL set services.domain_id to the id of the Domain whose domain_name equals the Canonical_Domain of the Service.
2. WHEN the CRM creates a Service with a domain name that has no matching Domain, THE CRM SHALL create the Domain and link it through domain_id.
3. THE Admin_Service_Controller SHALL perform the duplicate-domain check and Service creation inside one database transaction.
4. IF a unique index violation occurs during Service creation, THEN THE Admin_Service_Controller SHALL return a validation error naming the duplicate domain.
5. THE CRM SHALL read the domain name of a Service through the linked Domain when domain_id is set.

### Requirement 13: eNom sync ownership and safety (Phase 4: Domains and eNom)

**User Story:** As an admin, I want the eNom sync to update only registrar-owned fields, so that my edits are never overwritten.

#### Acceptance Criteria

1. THE CRM SHALL add a status column, an enom_domain_name_id column, a last_synced_at column and an admin-edited field tracking column to the domains table.
2. WHEN the data migration runs, THE CRM SHALL copy each DomainNameID found in enom_response JSON into enom_domain_name_id.
3. WHEN an admin edits a Domain field, THE CRM SHALL record that field as an Admin_Edited_Field.
4. WHEN Enom_Sync updates an existing Domain, THE Enom_Sync SHALL update only Enom_Fields that are not Admin_Edited_Fields.
5. WHEN Enom_Sync updates an existing Domain, THE Enom_Sync SHALL leave all other Domain fields unchanged.
6. IF Enom_Sync cannot parse the expiry date for a domain, THEN THE Enom_Sync SHALL keep the existing expiry_date value and log the domain name.
7. WHEN Enom_Sync finds a domain in eNom with no matching Domain, THE Enom_Sync SHALL create the Domain with a null company_id and Domain_Sync_Status 'active'.
8. WHEN Enom_Sync completes a full successful run, THE Enom_Sync SHALL set Domain_Sync_Status to 'missing_from_enom' for every eNom-sourced Domain absent from the eNom results.
9. WHEN Enom_Sync finds a Domain with Domain_Sync_Status 'missing_from_enom' present in eNom, THE Enom_Sync SHALL set Domain_Sync_Status to 'active'.
10. THE Enom_Sync SHALL retain every Domain row including those missing from eNom.
11. IF Enom_Sync fails to retrieve any page of results, THEN THE Enom_Sync SHALL abort without flagging any Domain as 'missing_from_enom'.

### Requirement 14: eNom API client behaviour (Phase 4: Domains and eNom)

**User Story:** As a developer, I want a robust eNom client, so that large accounts sync completely and credentials stay out of URLs.

#### Acceptance Criteria

1. THE Enom_Sync SHALL request every page of eNom results until the number of retrieved domains equals the total reported by eNom.
2. THE Enom_Sync SHALL send eNom credentials in the request body of a POST request.
3. IF an eNom request fails with a connection error or HTTP 5xx status, THEN THE Enom_Sync SHALL retry the request up to 3 times with increasing delay.
4. IF an eNom request still fails after 3 retries, THEN THE Enom_Sync SHALL abort and log the failure without exposing credentials.
5. THE Enom_Sync SHALL normalise each retrieved domain name using the Domain_Normaliser before matching.

### Requirement 15: Domain admin behaviour (Phase 4: Domains and eNom)

**User Story:** As an admin, I want accurate domain counts and safe deletion, so that the domain list can be trusted.

#### Acceptance Criteria

1. THE Admin DomainController index SHALL compute all displayed counts from a single shared query scope.
2. THE Admin DomainController SHALL load customer dropdown options through a searchable endpoint that returns at most 20 results per request.
3. THE Domain model SHALL use soft deletes.
4. WHEN an admin deletes a Domain, THE Admin DomainController SHALL soft delete the Domain.
5. IF a Domain is linked to a Service, THEN THE Admin DomainController SHALL refuse deletion and display the linked Service.
6. THE CleanupController SHALL load Domains and related Services with eager loading so the number of queries does not grow with the number of rows.
7. THE ReviewWizardController moveToDomain action SHALL link the Service to the Domain through domain_id and remove duplicated domain data.

### Requirement 16: Web app creation and linking (Phase 5: Web apps)

**User Story:** As an admin, I want web apps created and linked to services and domains consistently, so that app records match hosting data.

#### Acceptance Criteria

1. THE CRM SHALL add a nullable domain_id foreign key to the customer_apps table referencing domains.id.
2. THE CRM SHALL enforce a unique index on customer_apps.service_id.
3. WHEN an admin creates a CustomerApp, THE Admin CustomerAppController SHALL create the record inside a database transaction relying on the unique index instead of a check-then-insert.
4. IF a CustomerApp already exists for the Service, THEN THE Admin CustomerAppController SHALL return a validation error and create no record.
5. WHEN an admin selects a Domain for a CustomerApp, THE Admin CustomerAppController SHALL default install_url to the https URL of that Domain.
6. THE CRM SHALL route every CustomerApp creation screen through one shared creation action.
7. THE Admin CustomerAppController SHALL document the update method according to the fields the method actually updates.

### Requirement 17: Findings ingest (Phase 5: Web apps)

**User Story:** As an admin, I want findings ingest to preserve human decisions, so that resolved and dismissed findings are not lost.

#### Acceptance Criteria

1. THE Findings_Ingest_Service SHALL look up the CustomerApp by its public app identifier, not its primary key.
2. WHEN the Findings_Ingest_Service ingests a finding that matches an existing finding by fingerprint, THE Findings_Ingest_Service SHALL update the existing row and keep its resolved and dismissed states.
3. WHEN the Findings_Ingest_Service ingests a finding with no matching fingerprint, THE Findings_Ingest_Service SHALL insert a new finding.
4. THE Findings_Ingest_Service SHALL perform no deletion of findings that are missing from the payload; the Findings_Ingest_Service SHALL mark those findings as 'not_reported' instead.
5. IF a payload exceeds 1 MB or contains more than 1000 findings, THEN THE Findings_Ingest_Service SHALL reject the payload with InvalidFindingsPayloadException.
6. IF the app identifier matches no CustomerApp, THEN THE Findings_Ingest_Service SHALL throw UnknownCustomerAppException.
7. THE Findings_Ingest_Service SHALL apply each ingest inside a single database transaction.

### Requirement 18: Service creation and update validation (Phase 5: Web apps, Services)

**User Story:** As an admin, I want service creation to be atomic and failures to be visible, so that no half-created services exist.

#### Acceptance Criteria

1. WHEN an admin creates a Service with Stripe billing, THE Admin_Service_Controller SHALL create the Stripe objects after the Service transaction commits.
2. IF the Stripe call fails after the Service is created, THEN THE Admin_Service_Controller SHALL set the Service billing state to 'billing_failed', store the error, and show a persistent warning on the Service page.
3. THE Admin_Service_Controller SHALL share validation rules with the Admin_Service_API through a single form request class.
4. WHEN an admin retries billing setup for a Service in 'billing_failed' state, THE Admin_Service_Controller SHALL attempt the Stripe call again.

### Requirement 19: Provisioning queue and service screens (Phase 6: Admin UI)

**User Story:** As an admin, I want a provisioning queue showing all hosting services with status and failures, so that I can see and fix problems.

#### Acceptance Criteria

1. THE Provisioning_Queue SHALL list every Service with service_type 'Web Hosting', including shop-bought Services.
2. THE Provisioning_Queue SHALL display the Provisioning_Status, the provisioning_error, the customer and the domain for each Service.
3. WHEN an admin selects a Provisioning_Status filter, THE Provisioning_Queue SHALL list only Services with that Provisioning_Status.
4. WHILE a Service has provisioning_status 'failed', THE Provisioning_Queue SHALL display a retry action for that Service.
5. WHEN an admin activates the retry action, THE Provisioning_Queue SHALL trigger the retry defined in Requirement 9 and show the updated status.
6. THE Admin_UI SHALL render service type and status selectors from ServiceType and ServiceStatus.
7. THE Admin Service show page SHALL display the Provisioning_Status and provisioning_error.

### Requirement 20: Domain screens (Phase 6: Admin UI)

**User Story:** As an admin, I want to find and fix unassigned or missing domains, so that every domain has an owner and a correct status.

#### Acceptance Criteria

1. THE Admin domain index SHALL provide an 'Unassigned' filter listing Domains with a null company_id.
2. THE Admin domain index SHALL provide a 'Missing from eNom' filter listing Domains with Domain_Sync_Status 'missing_from_enom'.
3. WHEN an admin assigns a customer to a Domain, THE Admin DomainController SHALL save the company_id and keep Enom_Fields unchanged.
4. THE Admin domain index SHALL support assigning a customer to an unassigned Domain from the list.
5. THE Admin domain show page SHALL display the Domain_Sync_Status, last_synced_at and linked Service.
6. WHEN an admin links a Domain to a Service, THE Admin_UI SHALL set services.domain_id and reject a Domain already linked to another Service.

### Requirement 21: Web app screens (Phase 6: Admin UI)

**User Story:** As an admin, I want to create a web app directly from a service page, so that I do not need a separate workflow.

#### Acceptance Criteria

1. THE Admin Service show page SHALL display a 'Create web app' action WHILE the Service has no CustomerApp.
2. WHEN an admin activates the 'Create web app' action, THE Admin_UI SHALL open the shared creation form prefilled with the Service and its linked Domain.
3. WHILE the Service has a CustomerApp, THE Admin Service show page SHALL display a link to that CustomerApp.
4. THE Admin CustomerApp screens SHALL display the linked Domain and Service as links.

### Requirement 22: Consistency and access (Phase 6: Admin UI)

**User Story:** As an admin, I want service, domain and app screens to behave the same way, so that the CRM is predictable.

#### Acceptance Criteria

1. THE Admin_UI SHALL use the same layout, flash message pattern and validation error display across the service, domain and CustomerApp screens.
2. THE Admin_UI SHALL restrict the provisioning retry, domain assignment and domain linking actions to admin users through the EnsureIsAdmin middleware.
3. THE Admin_UI SHALL provide text labels and accessible names for all status badges, filters and action buttons.
