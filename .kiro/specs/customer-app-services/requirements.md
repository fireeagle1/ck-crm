# Requirements Document

## Introduction

This feature introduces managed customer applications ("apps") into the CK CRM. An app is a bespoke piece of software CK Enterprises builds, hosts, and maintains for a customer. Each app is represented to the customer and to billing as an existing `App\Models\Service` record (free, or connected to a Stripe product via `stripe_subscription_id` / `stripe_price_id`). A new `customer_apps` table and `App\Models\CustomerApp` model, linked one-to-one to `Service` on `service_id`, holds the technical metadata that must never reach the customer: git repository URL, hosting/storage location, installed packages, codebase information, and security check results.

This spec builds the data model, the admin technical view, the customer-facing summary view, and a receiving endpoint/command **stub** that accepts security findings (packages and vulnerabilities) for an app. The findings/packages storage is shaped so that a future daily sync (a ChatGPT-to-GitHub scan, tracked separately) can populate it. That daily sync, and any live wiring to GitHub or ChatGPT, is explicitly **out of scope** for this spec — only the stub and its storage are built here.

The customer boundary reuses the data-minimisation and allow-list discipline established by the `chatgpt-mcp-integration` spec: customer-facing surfaces expose only hand-built, allow-listed fields and a high-level security summary, never raw technical metadata. The receiving endpoint is designed to align with the MCP integration point (Passport-secured, scope-gated, data-minimised) so the later sync can authenticate the same way.

## Glossary

- **CRM**: The CK CRM Laravel application (Laravel 13 / PHP 8.3) hosted on cPanel.
- **Service**: The existing `App\Models\Service` Eloquent model (primary key `service_id`, owned by a customer via `company_id`). The customer-facing and billing record for a product or app.
- **Customer_App**: The new `App\Models\CustomerApp` Eloquent model backed by the `customer_apps` table, holding technical metadata for one app. Linked one-to-one to a Service on `service_id`.
- **App_Package**: A stored record of a software dependency installed in an app's codebase (name and version).
- **Security_Finding**: A stored record of a vulnerability or security issue associated with an app, including severity and status.
- **Security_Summary**: A high-level, customer-safe statement derived from an app's Security_Findings (for example "Security checks passing" or "N issues being addressed"), containing no package names, repository URLs, or vulnerability details.
- **Technical_Metadata**: The git repository URL, hosting/storage location, codebase information, App_Packages, and Security_Findings held by a Customer_App.
- **Admin_User**: An authenticated CRM user with administrative access (the existing `EnsureIsAdmin` / admin-guarded surfaces).
- **Customer_User**: An authenticated portal user scoped to a single customer by `company_id` (the existing `Portal` surfaces).
- **Findings_Endpoint**: The receiving endpoint and/or Artisan command stub that accepts security findings (packages and vulnerabilities) for an app in this spec.
- **Daily_Sync**: The future scheduled ChatGPT-to-GitHub scan that will populate App_Packages and Security_Findings. Out of scope for this spec.
- **MCP_Integration**: The `chatgpt-mcp-integration` spec providing Passport-based OAuth 2.1, scope-gating, data-minimisation, and audit logging conventions.

## Requirements

### Requirement 1: Customer App data model

**User Story:** As an Admin_User, I want each managed app stored as a dedicated technical record linked to its billing Service, so that technical metadata is kept separate from the customer-facing service record.

#### Acceptance Criteria

1. THE CRM SHALL provide a `customer_apps` table backing an `App\Models\CustomerApp` model whose primary technical attributes are a git repository URL, a hosting or storage location, and codebase information.
2. THE CustomerApp SHALL reference exactly one Service through a `service_id` foreign key that maps to the Service primary key `service_id`.
3. THE Service SHALL expose a one-to-one relationship to at most one CustomerApp.
4. WHEN an Admin_User creates a CustomerApp for a Service that already has a CustomerApp, THE CRM SHALL reject the creation and report that the Service already has a linked app.
5. WHERE a Service is linked to a Stripe product through `stripe_subscription_id` or `stripe_price_id`, THE CustomerApp SHALL rely on the Service record for all billing attributes and SHALL NOT duplicate billing fields.
6. WHEN a Service that has a linked CustomerApp is deleted, THE CRM SHALL remove the associated CustomerApp, App_Packages, and Security_Findings.

### Requirement 2: Package and security finding storage

**User Story:** As an Admin_User, I want installed packages and security findings stored against each app in a structured form, so that a future daily sync can populate them and the admin view can display them.

#### Acceptance Criteria

1. THE CRM SHALL store App_Package records associated with a CustomerApp, each recording a package name and a package version.
2. THE CRM SHALL store Security_Finding records associated with a CustomerApp, each recording a severity, a human-readable description, and a status drawn from a defined set of values.
3. THE CRM SHALL record a timestamp on each App_Package and each Security_Finding indicating when the record was last updated by the Findings_Endpoint.
4. THE CRM SHALL allow a CustomerApp to have zero or more App_Package records and zero or more Security_Finding records.
5. WHERE a Daily_Sync is introduced later, THE App_Package and Security_Finding schema SHALL support bulk replacement of an app's records in a single operation without schema changes.

### Requirement 3: Findings receiving endpoint stub

**User Story:** As an Admin_User, I want a receiving endpoint and command stub that accepts security findings for an app, so that a later daily sync has a defined target without building the sync itself in this spec.

#### Acceptance Criteria

1. THE CRM SHALL provide a Findings_Endpoint that accepts a payload of App_Packages and Security_Findings for one identified app.
2. WHEN the Findings_Endpoint receives a valid payload for an existing CustomerApp, THE CRM SHALL persist the submitted App_Packages and Security_Findings against that CustomerApp.
3. IF the Findings_Endpoint receives a payload for an app identifier that does not match an existing CustomerApp, THEN THE CRM SHALL reject the payload and return an error response that identifies the unknown app.
4. IF the Findings_Endpoint receives a payload that fails validation, THEN THE CRM SHALL reject the payload and return a validation error response without persisting any records.
5. THE Findings_Endpoint SHALL authenticate callers using the Passport-based, scope-gated scheme defined by the MCP_Integration, and SHALL reject unauthenticated or insufficiently scoped requests.
6. THE Findings_Endpoint SHALL NOT invoke, schedule, or depend on the Daily_Sync, and THE CRM SHALL NOT include GitHub or ChatGPT scanning logic in this spec.
7. WHEN the Findings_Endpoint accepts or rejects a payload, THE CRM SHALL record an audit entry consistent with the MCP_Integration audit conventions.

### Requirement 4: Admin technical view

**User Story:** As an Admin_User, I want a full technical view of a customer app, so that I can inspect its repository, hosting, packages, and security findings.

#### Acceptance Criteria

1. WHEN an Admin_User opens a CustomerApp, THE CRM SHALL display the git repository URL, hosting or storage location, codebase information, all App_Packages, and all Security_Findings for that app.
2. THE CRM SHALL allow an Admin_User to create a CustomerApp against an existing Service and to edit the git repository URL, hosting or storage location, and codebase information.
3. WHEN an Admin_User views a CustomerApp, THE CRM SHALL display the linked Service name, status, and billing information from the Service record.
4. THE admin CustomerApp view SHALL reuse the existing admin Service controller patterns (`App\Http\Controllers\Admin\ServiceController` and `App\Http\Controllers\Api\Admin\ServiceController`).

### Requirement 5: Customer portal view and data minimisation

**User Story:** As a Customer_User, I want to see my app as a service with a high-level security summary, so that I understand its status without being exposed to technical or security details.

#### Acceptance Criteria

1. WHEN a Customer_User views an app, THE CRM SHALL display the app name, the status, and the billing information (free, or the Stripe charge) drawn from the linked Service.
2. WHEN a Customer_User views an app, THE CRM SHALL display a Security_Summary derived from the app's Security_Findings that states either that security checks are passing or that a count of issues are being addressed.
3. THE CRM SHALL NOT expose package names, git repository URLs, hosting or storage locations, codebase information, or individual vulnerability details to any Customer_User.
4. THE customer-facing app response SHALL be built from an explicit allow-list of fields following the MCP_Integration data-minimisation discipline, and SHALL NOT serialise a full CustomerApp or Service model.
5. IF a Customer_User requests an app that does not belong to the requesting user's `company_id`, THEN THE CRM SHALL deny access.
6. THE customer app view SHALL reuse the existing portal Service controller pattern (`App\Http\Controllers\Portal\ServiceController`).

### Requirement 6: Security summary derivation

**User Story:** As a Customer_User, I want the security summary to reflect the real state of my app's findings, so that the statement I see is accurate and safe.

#### Acceptance Criteria

1. WHILE a CustomerApp has no open Security_Finding, THE CRM SHALL present a Security_Summary that states security checks are passing.
2. WHILE a CustomerApp has one or more open Security_Findings, THE CRM SHALL present a Security_Summary that states the count of issues being addressed without naming any finding.
3. THE Security_Summary SHALL derive the open-issue count solely from Security_Finding status values and SHALL NOT include severity labels, descriptions, or package references.
