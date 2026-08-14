# Requirements Document

## Introduction

This document defines the requirements for an iOS admin application (MVP - Phase 1) that provides mobile access to the existing CK CRM system. The app enables admin users to manage tickets, customers, services, invoices, and view dashboard metrics from an iOS device. The backend extends the existing Laravel 13 application with a Sanctum-authenticated JSON API, and the iOS client is a native SwiftUI app targeting iOS 17+.

## Glossary

- **API**: The Laravel Sanctum-authenticated JSON REST interface exposed by the CRM backend under the `/api/admin` prefix.
- **iOS_App**: The native SwiftUI iOS application targeting iOS 17+ that consumes the API.
- **Admin_User**: A User record with `is_admin = true` who is authorized to access admin functionality.
- **Dashboard**: The iOS_App screen displaying aggregated KPI metrics and recent activity.
- **Customer**: A company record identified by `company_id` containing business contact details and related services, invoices, tickets, and domains.
- **Service**: A recurring product or hosting subscription attached to a Customer, with billing frequency and charge amount.
- **Ticket**: A support request record with subject, description, status, priority, assignment, and threaded replies.
- **Invoice**: A billing document with status, amount, due date, and optional Stripe integration.
- **Domain**: A registered domain name tracked with expiry date, registrar, and renewal status.
- **Sanctum_Token**: A Laravel Sanctum personal access token used to authenticate API requests from the iOS_App.
- **APNs**: Apple Push Notification service used to deliver real-time alerts to the iOS_App.
- **Device_Token**: A unique token issued by APNs identifying a specific iOS device for push notification delivery.
- **MRR**: Monthly Recurring Revenue — the sum of all active service charges normalized to monthly values.
- **ARR**: Annual Recurring Revenue — MRR multiplied by 12.

## Requirements

### Requirement 1: API Authentication — Login

**User Story:** As an admin user, I want to log in to the iOS app using my existing CRM credentials, so that I can securely access admin functionality from my phone.

#### Acceptance Criteria

1. WHEN an Admin_User submits valid email and password credentials to the login endpoint, THE API SHALL issue a Sanctum_Token and return it in the JSON response.
2. WHEN a user submits invalid credentials to the login endpoint, THE API SHALL return a 401 HTTP status with an error message indicating invalid credentials.
3. WHEN a locked user (is_locked = true or lock_until in the future) submits credentials to the login endpoint, THE API SHALL return a 403 HTTP status with an error message indicating the account is locked.
4. WHEN a non-admin user submits valid credentials to the login endpoint, THE API SHALL return a 403 HTTP status with an error message indicating insufficient permissions.
5. THE API SHALL require both email and password fields in the login request and return a 422 HTTP status with validation errors when either field is missing.

### Requirement 2: API Authentication — Token Management

**User Story:** As an admin user, I want my session to remain active and be able to log out securely, so that my access is both convenient and secure.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests a token refresh, THE API SHALL revoke the current Sanctum_Token and issue a new Sanctum_Token in the response.
2. WHEN an authenticated Admin_User requests logout, THE API SHALL revoke the current Sanctum_Token and return a success confirmation.
3. WHEN a request is made with an expired or revoked Sanctum_Token, THE API SHALL return a 401 HTTP status with an unauthenticated error message.
4. THE API SHALL scope all admin API routes behind Sanctum token authentication middleware and the EnsureIsAdmin middleware.

### Requirement 3: Dashboard — Ticket Statistics

**User Story:** As an admin user, I want to see ticket statistics on the dashboard, so that I can quickly assess the support workload.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the count of open tickets (status in Open, Pending, In Progress).
2. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the count of critical-priority open tickets.
3. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the count of high-priority open tickets.
4. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the count of overdue tickets (open tickets with due_at in the past).
5. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the average first-response time in minutes across all tickets that have a first_replied_at value.

### Requirement 4: Dashboard — Financial Metrics

**User Story:** As an admin user, I want to see revenue metrics on the dashboard, so that I can monitor the financial health of the business at a glance.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the MRR calculated by normalizing each active service charge to a monthly value based on billing frequency.
2. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the ARR as MRR multiplied by 12.
3. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the count of overdue invoices (status Unpaid, due_date in the past, excluding Void and Uncollectible statuses).
4. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the total amount of overdue invoices in the same currency precision as stored (2 decimal places).
5. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the total revenue collected this calendar month (invoices with status Paid and paid_date in the current month, excluding Void and Uncollectible statuses).

### Requirement 5: Dashboard — Activity and Alerts

**User Story:** As an admin user, I want to see recent activity and upcoming expirations on the dashboard, so that I can stay informed of important events.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the 5 most recently created tickets with their subject, customer name, assigned user name, status, and priority.
2. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return the 5 most recent admin user logins with user name and login timestamp.
3. WHEN an authenticated Admin_User requests the dashboard endpoint, THE API SHALL return up to 5 domains expiring within the next 30 days, ordered by expiry_date ascending, with domain name, customer name, and expiry date.

### Requirement 6: Customer Management — List and Search

**User Story:** As an admin user, I want to browse and search customers from my phone, so that I can quickly find customer information on the go.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests the customers list endpoint, THE API SHALL return a paginated list of Customer records with company_name, customer_name, and phone_number.
2. WHEN an authenticated Admin_User provides a search query parameter, THE API SHALL filter the Customer list by matching against company_name, customer_name, or phone_number fields.
3. THE API SHALL default to 15 records per page and accept a per_page parameter with a maximum of 100 records per page.
4. THE API SHALL include pagination metadata (current_page, last_page, total, per_page) in the response.

### Requirement 7: Customer Management — CRUD Operations

**User Story:** As an admin user, I want to create, view, edit, and delete customers from the iOS app, so that I can manage customer records without needing a desktop.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests a single Customer by company_id, THE API SHALL return all Customer fields including related counts (services, tickets, invoices, domains).
2. WHEN an authenticated Admin_User submits a valid create request with company_name, THE API SHALL create a new Customer record and return the created Customer with a 201 HTTP status.
3. WHEN an authenticated Admin_User submits an update request with valid fields for an existing Customer, THE API SHALL update the Customer record and return the updated Customer.
4. WHEN an authenticated Admin_User submits a delete request for an existing Customer, THE API SHALL delete the Customer record and return a 204 HTTP status.
5. IF an authenticated Admin_User submits a create or update request with invalid data, THEN THE API SHALL return a 422 HTTP status with field-level validation error messages.
6. IF an authenticated Admin_User requests a Customer that does not exist, THEN THE API SHALL return a 404 HTTP status with a not-found error message.

### Requirement 8: Service Management — List and Filter

**User Story:** As an admin user, I want to browse and filter services from my phone, so that I can review active and inactive subscriptions on the go.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests the services list endpoint, THE API SHALL return a paginated list of Service records with service_short, service_type, domain_name, status, service_monthly_charge, and customer name.
2. WHEN an authenticated Admin_User provides a status filter parameter, THE API SHALL return only Service records matching the specified status value.
3. THE API SHALL default to 15 records per page and accept a per_page parameter with a maximum of 100 records per page.
4. THE API SHALL include pagination metadata (current_page, last_page, total, per_page) in the response.

### Requirement 9: Service Management — CRUD Operations

**User Story:** As an admin user, I want to create, view, edit, and delete services from the iOS app, so that I can manage subscriptions without needing a desktop.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests a single Service by service_id, THE API SHALL return all Service fields including the associated customer name.
2. WHEN an authenticated Admin_User submits a valid create request with company_id, service_short, and status, THE API SHALL create a new Service record and return the created Service with a 201 HTTP status.
3. WHEN an authenticated Admin_User submits an update request with valid fields for an existing Service, THE API SHALL update the Service record and return the updated Service.
4. WHEN an authenticated Admin_User submits a delete request for an existing Service, THE API SHALL delete the Service record and return a 204 HTTP status.
5. IF an authenticated Admin_User submits a create or update request with invalid data, THEN THE API SHALL return a 422 HTTP status with field-level validation error messages.
6. IF an authenticated Admin_User requests a Service that does not exist, THEN THE API SHALL return a 404 HTTP status with a not-found error message.

### Requirement 10: Ticket Management — List and Filter

**User Story:** As an admin user, I want to browse and filter tickets from my phone, so that I can triage support requests while away from my desk.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests the tickets list endpoint, THE API SHALL return a paginated list of Ticket records with subject, status, priority, customer name, assigned user name, and created_at.
2. WHEN an authenticated Admin_User provides a status filter parameter, THE API SHALL return only Ticket records matching the specified status value.
3. WHEN an authenticated Admin_User provides a priority filter parameter, THE API SHALL return only Ticket records matching the specified priority value.
4. WHEN an authenticated Admin_User provides both status and priority filter parameters, THE API SHALL return only Ticket records matching both filter values.
5. THE API SHALL default to 15 records per page and accept a per_page parameter with a maximum of 100 records per page.
6. THE API SHALL order tickets by created_at descending by default.

### Requirement 11: Ticket Management — View Detail and Thread

**User Story:** As an admin user, I want to view a ticket's full detail and conversation thread, so that I can understand the issue context before responding.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests a single Ticket by ticket_id, THE API SHALL return all Ticket fields including description, customer name, assigned user name, asset details, and service details.
2. WHEN an authenticated Admin_User requests a single Ticket by ticket_id, THE API SHALL return all associated TicketReply records ordered by created_at ascending, each containing the reply body, author name, and timestamp.
3. WHEN an authenticated Admin_User requests a single Ticket by ticket_id, THE API SHALL return all associated TicketActivity records ordered by created_at ascending.
4. IF an authenticated Admin_User requests a Ticket that does not exist, THEN THE API SHALL return a 404 HTTP status with a not-found error message.

### Requirement 12: Ticket Management — Create, Edit, and Reply

**User Story:** As an admin user, I want to create tickets, update their status and priority, and reply to them from my phone, so that I can manage support workflows on the go.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User submits a valid create request with company_id, subject, and description, THE API SHALL create a new Ticket record with status defaulting to Open and return the created Ticket with a 201 HTTP status.
2. WHEN an authenticated Admin_User submits an update request with status, priority, or user_id fields for an existing Ticket, THE API SHALL update the Ticket record and return the updated Ticket.
3. WHEN an authenticated Admin_User submits a reply with a body to an existing Ticket, THE API SHALL create a new TicketReply record associated with the Ticket and the authenticated Admin_User, and return the created reply with a 201 HTTP status.
4. WHEN an authenticated Admin_User submits the first reply to a Ticket that has no prior replies, THE API SHALL set the first_replied_at timestamp on the Ticket to the current time.
5. IF an authenticated Admin_User submits a create, update, or reply request with invalid data, THEN THE API SHALL return a 422 HTTP status with field-level validation error messages.

### Requirement 13: Invoice Management — List and Filter

**User Story:** As an admin user, I want to browse and filter invoices from my phone, so that I can monitor billing status on the go.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User requests the invoices list endpoint, THE API SHALL return a paginated list of Invoice records with invoice_status, invoice_amount, invoice_date, due_date, paid_date, and customer name.
2. WHEN an authenticated Admin_User provides a status filter parameter, THE API SHALL return only Invoice records matching the specified invoice_status value.
3. THE API SHALL default to 15 records per page and accept a per_page parameter with a maximum of 100 records per page.
4. THE API SHALL exclude invoices with Void or Uncollectible status from the list by default.
5. THE API SHALL order invoices by invoice_date descending by default.

### Requirement 14: Invoice Management — Create One-Off Invoice

**User Story:** As an admin user, I want to create one-off invoices from my phone, so that I can bill customers for ad-hoc work without needing a desktop.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User submits a valid create request with company_id, invoice_amount, invoice_items, and due_date, THE API SHALL create a new Invoice record with invoice_status defaulting to Unpaid and invoice_date defaulting to today, and return the created Invoice with a 201 HTTP status.
2. IF an authenticated Admin_User submits a create request with invalid data (missing required fields, non-existent company_id, negative amount), THEN THE API SHALL return a 422 HTTP status with field-level validation error messages.
3. THE API SHALL validate that invoice_amount is a positive decimal value with up to 2 decimal places.

### Requirement 15: Invoice Management — Send Payment Reminder

**User Story:** As an admin user, I want to send payment reminders for overdue invoices from my phone, so that I can chase payments without needing a desktop.

#### Acceptance Criteria

1. WHEN an authenticated Admin_User submits a remind request for an unpaid Invoice, THE API SHALL dispatch a payment reminder notification to the customer associated with the Invoice and return a success confirmation.
2. IF an authenticated Admin_User submits a remind request for an Invoice that is already paid, THEN THE API SHALL return a 422 HTTP status with an error message indicating the invoice is already paid.
3. IF an authenticated Admin_User submits a remind request for an Invoice that does not exist, THEN THE API SHALL return a 404 HTTP status with a not-found error message.

### Requirement 16: Push Notifications — Device Registration

**User Story:** As an admin user, I want my device to receive push notifications, so that I am alerted to critical events without needing to open the app.

#### Acceptance Criteria

1. WHEN the iOS_App obtains a Device_Token from APNs, THE iOS_App SHALL send the Device_Token to the API registration endpoint.
2. WHEN an authenticated Admin_User submits a Device_Token to the registration endpoint, THE API SHALL store the Device_Token associated with the authenticated Admin_User.
3. WHEN an authenticated Admin_User submits a Device_Token that already exists for the same user, THE API SHALL update the existing record timestamp without creating a duplicate.
4. WHEN an authenticated Admin_User logs out, THE API SHALL remove the Device_Token associated with the current session.

### Requirement 17: Push Notifications — Notification Triggers

**User Story:** As an admin user, I want to receive push notifications for critical events, so that I can respond quickly to issues that need attention.

#### Acceptance Criteria

1. WHEN a new Ticket is created (by a customer or system), THE API SHALL send a push notification to all registered Admin_User Device_Tokens containing the ticket subject and customer name.
2. WHEN an Invoice becomes overdue (due_date passes while invoice_status is Unpaid), THE API SHALL send a push notification to all registered Admin_User Device_Tokens containing the invoice amount and customer name.
3. WHEN a Ticket priority is set to Critical, THE API SHALL send a push notification to all registered Admin_User Device_Tokens containing the ticket subject and a critical alert indicator.
4. THE API SHALL deliver push notifications via APNs using the registered Device_Tokens.
5. IF a Device_Token is reported as invalid by APNs, THEN THE API SHALL remove the invalid Device_Token from the database.

### Requirement 18: iOS App — Authentication Flow

**User Story:** As an admin user, I want a smooth login and session experience on my phone, so that access is fast and secure.

#### Acceptance Criteria

1. THE iOS_App SHALL present a login screen with email and password fields when no valid Sanctum_Token is stored.
2. WHEN the iOS_App receives a Sanctum_Token from the login response, THE iOS_App SHALL store the token securely in the iOS Keychain.
3. THE iOS_App SHALL include the Sanctum_Token as a Bearer token in the Authorization header of all authenticated API requests.
4. WHEN the iOS_App receives a 401 response from any API request, THE iOS_App SHALL clear the stored token and navigate the user to the login screen.
5. THE iOS_App SHALL provide a logout action that calls the API logout endpoint and clears the locally stored token.

### Requirement 19: iOS App — Dashboard Display

**User Story:** As an admin user, I want a clear dashboard screen showing key metrics, so that I can assess business health at a glance.

#### Acceptance Criteria

1. WHEN the Dashboard screen loads, THE iOS_App SHALL display all ticket statistics (open, critical, high, overdue counts and average response time).
2. WHEN the Dashboard screen loads, THE iOS_App SHALL display financial metrics (MRR, ARR, overdue invoice count and amount, revenue this month).
3. WHEN the Dashboard screen loads, THE iOS_App SHALL display the list of expiring domains with domain name, customer name, and days until expiry.
4. WHEN the Dashboard screen loads, THE iOS_App SHALL display the 5 most recent tickets with subject, customer name, and status.
5. WHEN the Dashboard screen loads, THE iOS_App SHALL display the 5 most recent admin logins with user name and time.
6. THE iOS_App SHALL support pull-to-refresh on the Dashboard screen to reload all metrics.

### Requirement 20: iOS App — Navigation and Data Display

**User Story:** As an admin user, I want consistent navigation and data patterns across all screens, so that the app is intuitive to use.

#### Acceptance Criteria

1. THE iOS_App SHALL provide tab-based navigation for Dashboard, Customers, Services, Tickets, and Invoices sections.
2. THE iOS_App SHALL display loading indicators while API requests are in progress.
3. IF an API request fails due to a network error, THEN THE iOS_App SHALL display an error message with a retry option.
4. THE iOS_App SHALL support infinite scroll or pagination controls on all list screens to load additional pages.
5. THE iOS_App SHALL require iOS 17.0 or later as the minimum deployment target.
