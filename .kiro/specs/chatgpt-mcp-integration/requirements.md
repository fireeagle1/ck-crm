# Requirements Document

## Introduction

This document defines requirements for a secure Model Context Protocol (MCP) integration that allows ChatGPT (via OpenAI's custom MCP connector) to read data from the CK CRM and to manage support tickets (incidents). The integration exposes a remote MCP endpoint served by the existing Laravel application. ChatGPT is granted **read-only** access to five domains (Customers, Invoices, Projects, Services, Tickets) and **full write** access to Tickets only (create ticket, update ticket, reply to ticket).

Authentication uses OAuth 2.1 with the Laravel application acting as the authorization server. Access is least-privilege and scoped: a leaked or misused credential can only perform the explicitly granted read operations and ticket writes, and can never modify customers, invoices, projects, services, or any other part of the CRM. Security-sensitive fields are never exposed to ChatGPT.

## Glossary

- **CRM**: The existing CK Laravel 13 / PHP 8.3 application hosted on cPanel, authenticated today with Laravel Sanctum and an `is_admin` boolean.
- **MCP**: Model Context Protocol — the protocol OpenAI's custom connector uses to call remote tools over HTTPS.
- **MCP_Server**: The new remote MCP endpoint implemented as a route group inside the CRM (not a separate process), exposing MCP tools over HTTPS.
- **MCP_Tool**: A single callable operation exposed to ChatGPT (e.g. `search_tickets`, `create_ticket`).
- **MCP_Client**: OpenAI's custom MCP connector acting on behalf of ChatGPT.
- **Authorization_Server**: The CRM acting as an OAuth 2.1 authorization server (authorize + token endpoints, PKCE), implemented with Laravel Passport.
- **MCP_Scope**: An OAuth scope limiting what an access token may do. Scopes: `customers:read`, `invoices:read`, `projects:read`, `services:read`, `tickets:read`, `tickets:write`.
- **MCP_Identity**: The dedicated, least-privilege account/client the MCP_Client authenticates as; it is NOT a standard CRM admin user and inherits only granted MCP_Scopes.
- **Ticket**: A record in the `tickets` table (PK `ticket_id`); the "incident" entity. `ticket_type` is `Incident` or `Service Request`. Has replies (`ticket_replies`) and an audit log (`ticket_activities`).
- **Customer**: A record in the `customers` table (PK `company_id`); the central entity most domains are scoped to.
- **Invoice**: A record in the `invoices` table (PK `invoice_id`), Stripe-backed.
- **Project**: A record in the `projects` table (PK `id`); currently has no API endpoint.
- **Service**: A record in the `services` table (PK `service_id`).
- **Security_Field**: Any sensitive authentication/credential field that MUST never be returned to ChatGPT — including `password`, `remember_token`, password reset tokens, API tokens, and any MFA/session secret.
- **Ticket_Side_Effects**: The existing behaviours triggered by ticket writes — `TicketActivity` audit entries, customer email notifications, and admin push/notification (`NewTicketNotification`, `CriticalTicketNotification`).
- **MCP_Audit_Log**: A persistent record of every MCP_Tool invocation (identity, tool, scope, timestamp, outcome).

## Requirements

### Requirement 1: OAuth 2.1 Authorization for the MCP Connector

**User Story:** As a CRM owner, I want ChatGPT to authenticate via OAuth with revocable, scoped access, so that access can be granted, limited, and revoked without sharing admin credentials.

#### Acceptance Criteria

1. THE Authorization_Server SHALL expose OAuth 2.1 authorization and token endpoints compatible with OpenAI's custom MCP connector, using Laravel Passport.
2. THE Authorization_Server SHALL require PKCE for the authorization code flow.
3. THE Authorization_Server SHALL issue access tokens that carry only the explicitly granted MCP_Scopes from the set {`customers:read`, `invoices:read`, `projects:read`, `services:read`, `tickets:read`, `tickets:write`}.
4. WHEN an access token is presented without a scope required by a requested MCP_Tool, THE MCP_Server SHALL reject the call with an authorization error and SHALL NOT perform the operation.
5. THE Authorization_Server SHALL allow an administrator to revoke MCP_Client tokens, and WHEN a token is revoked, THE MCP_Server SHALL reject all subsequent calls using that token.
6. THE MCP_Server SHALL only accept requests over HTTPS.
7. THE Authorization_Server SHALL issue access tokens with a bounded lifetime and SHALL support refresh so that long-lived static credentials are not required.

### Requirement 2: Least-Privilege MCP Identity

**User Story:** As a CRM owner, I want ChatGPT to act as a dedicated limited identity rather than a full admin, so that it can never perform actions outside the agreed read and ticket-write scope.

#### Acceptance Criteria

1. THE MCP_Server SHALL authorize MCP_Tool calls based solely on the MCP_Scopes present on the access token, independent of the `is_admin` boolean.
2. THE MCP_Server SHALL NOT expose any operation that creates, updates, or deletes Customers, Invoices, Projects, or Services.
3. THE MCP_Server SHALL NOT expose any CRM operation outside the five read domains and the ticket write operations defined in this document (e.g. assets, shop orders, domains, billing actions, user management).
4. WHEN the MCP_Identity attempts an operation for which no MCP_Tool and scope exists, THE MCP_Server SHALL deny the request.
5. THE MCP_Identity SHALL be distinct from human CRM admin accounts and SHALL NOT be usable to log in to the CRM web or existing admin API.

### Requirement 3: Tool Discovery

**User Story:** As a ChatGPT user, I want the connector to list the available tools and their inputs, so that the model can call them correctly.

#### Acceptance Criteria

1. THE MCP_Server SHALL implement the MCP protocol handshake and SHALL return the list of available MCP_Tools with names, descriptions, and input schemas.
2. THE MCP_Server SHALL only advertise MCP_Tools permitted by the MCP_Scopes on the presenting access token.
3. THE MCP_Server SHALL describe each MCP_Tool clearly enough that the model can distinguish read tools from the ticket write tools.

### Requirement 4: Read — Customers

**User Story:** As a ChatGPT user, I want to look up customers, so that I can give support context and relate tickets to the right company.

#### Acceptance Criteria

1. WHEN the MCP_Client calls a customer read tool with `customers:read`, THE MCP_Server SHALL return matching Customer records including `company_id`, `company_name`, `customer_name`, and contact/location fields.
2. THE MCP_Server SHALL support looking up a single Customer by `company_id` and listing/searching Customers by name.
3. THE MCP_Server SHALL paginate customer list results and SHALL cap the maximum page size.
4. THE MCP_Server SHALL NOT return any Security_Field for any related user in customer responses.

### Requirement 5: Read — Invoices

**User Story:** As a ChatGPT user, I want to see a customer's invoices and their status, so that I can answer billing-status questions.

#### Acceptance Criteria

1. WHEN the MCP_Client calls an invoice read tool with `invoices:read`, THE MCP_Server SHALL return Invoice records including `invoice_id`, `company_id`, `invoice_status`, `invoice_amount`, `invoice_date`, and `due_date`.
2. THE MCP_Server SHALL support filtering invoices by `company_id` and by `invoice_status`.
3. THE MCP_Server SHALL paginate invoice results and SHALL cap the maximum page size.
4. THE MCP_Server SHALL NOT expose Stripe secret identifiers or payment credentials; where a Stripe reference is needed it SHALL be limited to non-secret, read-only references.

### Requirement 6: Read — Projects

**User Story:** As a ChatGPT user, I want to see a customer's projects and their status, so that I can report on delivery progress.

#### Acceptance Criteria

1. THE CRM SHALL provide a read-only Projects API/tool surface (none exists today) exposed through the MCP_Server under `projects:read`.
2. WHEN the MCP_Client calls a project read tool with `projects:read`, THE MCP_Server SHALL return Project records including `id`, `company_id`, `title`, `status`, and progress indicator.
3. THE MCP_Server SHALL support looking up a single Project by `id` and listing/filtering Projects by `company_id`.
4. THE MCP_Server SHALL paginate project results and SHALL cap the maximum page size.

### Requirement 7: Read — Services

**User Story:** As a ChatGPT user, I want to see the services a customer has, so that I can relate incidents to the right service.

#### Acceptance Criteria

1. WHEN the MCP_Client calls a service read tool with `services:read`, THE MCP_Server SHALL return Service records including `service_id`, `company_id`, `service_short`, `service_type`, and `status`.
2. THE MCP_Server SHALL support looking up a single Service by `service_id` and listing/filtering Services by `company_id`.
3. THE MCP_Server SHALL paginate service results and SHALL cap the maximum page size.
4. THE MCP_Server SHALL NOT expose service credential fields such as `cpanel_username` or any stored secret.

### Requirement 8: Read — Tickets

**User Story:** As a ChatGPT user, I want to search and read tickets with their replies and history, so that I can understand an incident before acting on it.

#### Acceptance Criteria

1. WHEN the MCP_Client calls a ticket search tool with `tickets:read`, THE MCP_Server SHALL return matching Ticket summaries including `ticket_id`, `subject`, `status`, `priority`, `ticket_type`, and `customer_name`.
2. THE MCP_Server SHALL support filtering tickets by status, priority, and `company_id`, and searching by subject/ticket id/customer name.
3. WHEN the MCP_Client requests a single ticket with `tickets:read`, THE MCP_Server SHALL return the ticket detail including description, related customer/service/asset references, non-internal and internal replies, and the activity history.
4. THE MCP_Server SHALL paginate ticket list results and SHALL cap the maximum page size.

### Requirement 9: Write — Create Ticket

**User Story:** As a ChatGPT user, I want to create a new incident ticket, so that I can log work coming in without using the web app.

#### Acceptance Criteria

1. WHEN the MCP_Client calls the create-ticket tool with `tickets:write`, THE MCP_Server SHALL validate inputs using the same rules as the existing ticket create path (required `company_id` existing, `subject`, `description`, `priority` in {Low,Normal,High,Critical}, `ticket_type` in {Incident, Service Request}; optional `user_id`, `asset_id`, `service_id`, `request_category`).
2. WHEN validation fails, THE MCP_Server SHALL return a structured error describing the invalid fields and SHALL NOT create a ticket.
3. WHEN a ticket is created, THE MCP_Server SHALL persist it with status `Open` and SHALL trigger the existing Ticket_Side_Effects, including notifying admins.
4. WHEN a ticket is created with customer notification requested, THE MCP_Server SHALL send the existing customer notification.
5. WHEN a ticket is created, THE MCP_Server SHALL return the created ticket's `ticket_id` and summary to the MCP_Client.

### Requirement 10: Write — Update Ticket

**User Story:** As a ChatGPT user, I want to update a ticket's status, priority, type, assignment, and due date, so that I can progress incidents.

#### Acceptance Criteria

1. WHEN the MCP_Client calls the update-ticket tool with `tickets:write`, THE MCP_Server SHALL validate inputs using the same rules as the existing ticket update path (status in {Open,Pending,In Progress,Closed}; priority in {Low,Normal,High,Critical}; ticket_type in {Incident, Service Request}; optional `asset_id`, `service_id`, `user_id`, `due_at`).
2. WHEN a tracked field changes, THE MCP_Server SHALL write a TicketActivity audit entry attributed to the MCP_Identity.
3. WHEN a ticket status changes, THE MCP_Server SHALL send the existing customer status-change notification.
4. WHEN a ticket priority is set to Critical, THE MCP_Server SHALL send the existing CriticalTicketNotification to admins.
5. WHEN validation fails or the ticket does not exist, THE MCP_Server SHALL return a structured error and SHALL NOT modify the ticket.

### Requirement 11: Write — Reply to Ticket

**User Story:** As a ChatGPT user, I want to add a reply or internal note to a ticket, so that I can communicate on the incident.

#### Acceptance Criteria

1. WHEN the MCP_Client calls the reply tool with `tickets:write`, THE MCP_Server SHALL validate a required `body` and an optional `is_internal` flag.
2. WHEN a non-internal reply is added, THE MCP_Server SHALL send the existing customer reply notification; WHEN the reply is internal, THE MCP_Server SHALL NOT notify the customer.
3. WHEN a reply is added to a Closed ticket, THE MCP_Server SHALL re-open the ticket and record a status-change activity, matching existing behaviour.
4. WHEN the first non-internal reply is added, THE MCP_Server SHALL set the ticket `first_replied_at` timestamp if not already set.
5. THE MCP_Server SHALL attribute every reply and resulting activity to the MCP_Identity.

### Requirement 12: Security Field Exclusion & Data Minimisation

**User Story:** As a CRM owner, I want sensitive fields never exposed to ChatGPT, so that credentials and secrets cannot leak through the integration.

#### Acceptance Criteria

1. THE MCP_Server SHALL NOT return any Security_Field — including `password`, `remember_token`, password reset tokens, API tokens, and MFA/session secrets — in any response.
2. THE MCP_Server SHALL return only the fields explicitly defined for each MCP_Tool and SHALL NOT serialise full models that could include sensitive attributes.
3. THE MCP_Server SHALL NOT expose stored third-party credentials or secrets (e.g. WHM API tokens, cPanel credentials, Stripe secrets) through any tool.

### Requirement 13: Auditing, Rate Limiting, and Abuse Protection

**User Story:** As a CRM owner, I want every MCP action logged and rate limited, so that I can monitor usage and contain abuse.

#### Acceptance Criteria

1. THE MCP_Server SHALL write an MCP_Audit_Log entry for every MCP_Tool call recording identity/token reference, tool name, scope used, timestamp, and success/failure.
2. THE MCP_Server SHALL apply rate limiting to MCP endpoints and SHALL reject requests exceeding the configured limit with a standard rate-limit response.
3. THE MCP_Server SHALL NOT record Security_Fields or full request payloads containing sensitive data in the MCP_Audit_Log.
4. WHEN an MCP_Tool call fails authorization, THE MCP_Server SHALL record the denied attempt in the MCP_Audit_Log.

### Requirement 14: Non-Disruption of Existing Systems

**User Story:** As a CRM owner, I want the MCP integration to leave the existing admin app and API working unchanged, so that current operations are not affected.

#### Acceptance Criteria

1. THE MCP_Server SHALL be additive and SHALL NOT alter the behaviour of existing `/api/admin/*` endpoints or the Sanctum mobile-app token flow.
2. WHERE ticket write logic is shared between the MCP_Server and the existing admin API, refactoring SHALL preserve the existing admin API's observable behaviour (same validation, same side effects).
3. THE MCP_Server SHALL be deployable within the existing cPanel (no-SSH, cron-based) hosting model without requiring a separate long-lived process.
4. WHERE notification side effects run, THE MCP_Server SHALL honour the existing queue configuration (sync or cron worker) without blocking or breaking ticket creation.
