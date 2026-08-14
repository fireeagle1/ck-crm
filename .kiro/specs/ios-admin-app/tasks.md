# Implementation Plan: iOS Admin App (MVP — Phase 1)

## Overview

This plan implements a Sanctum-authenticated JSON API on the existing Laravel 13 CRM backend and a native SwiftUI iOS client. The backend work adds API controllers, service classes, a device_tokens migration, and push notification infrastructure. The iOS app provides a complete mobile admin experience with dashboard, CRUD screens, and push notifications. Backend uses PHP/Laravel; iOS uses Swift/SwiftUI targeting iOS 17+.

## Tasks

- [x] 1. Backend infrastructure setup
  - [x] 1.1 Install Laravel Sanctum and configure token authentication
    - Run `composer require laravel/sanctum` (if not already bundled with Laravel 13)
    - Publish Sanctum config: `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
    - Set token expiration to 30 days in `config/sanctum.php`
    - Add `HasApiTokens` trait to `app/Models/User.php`
    - _Requirements: 2.4_

  - [x] 1.2 Create DeviceToken migration and model
    - Create migration `create_device_tokens_table` with columns: id, user_id (foreign key, cascadeOnDelete), token (unique string), platform (string, default 'ios'), timestamps
    - Add composite index on `[user_id, platform]`
    - Create `app/Models/DeviceToken.php` with fillable fields and `user()` BelongsTo relationship
    - Add `deviceTokens()` HasMany relationship to User model
    - _Requirements: 16.2, 16.3_

  - [x] 1.3 Create `routes/api.php` with admin route group and middleware stack
    - Create `routes/api.php` with `/api/admin` prefix group
    - Register login route outside auth middleware
    - Apply `auth:sanctum` and `EnsureIsAdmin` middleware to all other admin routes
    - Register the API route file in `bootstrap/app.php` (or route service provider)
    - Define all endpoint routes per the design document endpoint table
    - _Requirements: 2.4_

  - [x] 1.4 Update EnsureIsAdmin middleware to return JSON for API requests
    - Modify `app/Http/Middleware/EnsureIsAdmin.php` to detect `expectsJson()` or path starting with `api/`
    - Return `{"message": "Insufficient permissions."}` with 403 status for API requests
    - Preserve existing Blade/HTML abort behavior for web requests
    - _Requirements: 2.4, 1.4_

- [x] 2. Authentication API
  - [x] 2.1 Implement AuthController with login, refresh, and logout endpoints
    - Create `app/Http/Controllers/Api/Admin/AuthController.php`
    - `login()`: validate email+password (422 if missing), verify credentials (401 if invalid), check is_admin (403 if not), check is_locked/lock_until (403 if locked), create Sanctum token, return token + user JSON
    - `refresh()`: revoke current token, create new token, return new token
    - `logout()`: revoke current token, delete associated device tokens for user, return 204
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 16.4_

  - [x]* 2.2 Write property tests for authentication (Properties 1–4)
    - **Property 1: Login grants token only to valid unlocked admins**
    - **Property 2: Token refresh invalidates old token**
    - **Property 3: Logout invalidates token**
    - **Property 4: Unauthenticated requests are rejected**
    - **Validates: Requirements 1.1–1.5, 2.1–2.4**

- [x] 3. Dashboard API
  - [x] 3.1 Extract MrrCalculator service class
    - Create `app/Services/MrrCalculator.php`
    - Implement `calculate(): float` that queries active services with service_monthly_charge > 0
    - Normalize charges by billing frequency: Weekly=0.25, Monthly=1, Quarterly=3, Biannually=6, Annually=12, Biennially=24
    - Return raw charge for services without stripe_subscription_id, normalized for those with one
    - _Requirements: 4.1, 4.2_

  - [x] 3.2 Create DashboardService with metrics aggregation
    - Create `app/Services/DashboardService.php`
    - Inject `MrrCalculator` via constructor
    - Implement `getMetrics(): array` returning all dashboard data:
    - Ticket stats: open_count (status in Open/Pending/In Progress), critical_count, high_count, overdue_count (due_at < now), avg_response_time_minutes (from first_replied_at - created_at)
    - Financials: mrr, arr (mrr×12), overdue_invoices_count (Unpaid + past due, excluding Void/Uncollectible), overdue_invoices_amount (2dp precision), revenue_this_month (Paid in current month, excluding Void/Uncollectible)
    - Recent tickets: 5 most recent by created_at desc with subject, customer_name, assigned_user_name, status, priority
    - Recent logins: 5 admin users by last_login desc with user_name, last_login
    - Expiring domains: up to 5 with expiry_date within 30 days, ordered by expiry_date asc, with domain_name, customer_name, expiry_date, days_until_expiry
    - _Requirements: 3.1–3.5, 4.1–4.5, 5.1–5.3_

  - [x] 3.3 Implement DashboardController API endpoint
    - Create `app/Http/Controllers/Api/Admin/DashboardController.php`
    - `index()`: inject DashboardService, return `getMetrics()` as JSON matching the design response schema
    - _Requirements: 3.1–3.5, 4.1–4.5, 5.1–5.3_

  - [x]* 3.4 Write property tests for dashboard metrics (Properties 5–8)
    - **Property 5: Dashboard ticket statistics match database state**
    - **Property 6: MRR normalization is correct**
    - **Property 7: Financial aggregations exclude Void and Uncollectible**
    - **Property 8: Dashboard recent items are correctly bounded and ordered**
    - **Validates: Requirements 3.1–3.5, 4.1–4.5, 5.1–5.3**

- [x] 4. Checkpoint — Backend infrastructure and dashboard
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Customer CRUD API
  - [x] 5.1 Implement CustomerController with index, store, show, update, destroy
    - Create `app/Http/Controllers/Api/Admin/CustomerController.php`
    - `index()`: paginated list (default 15, max 100), search by company_name/customer_name/phone_number via `?search=` param, return company_id, company_name, customer_name, phone_number + pagination meta (current_page, last_page, total, per_page)
    - `store()`: validate company_name required, create Customer, return 201 with created record
    - `show()`: return all Customer fields + withCount for services, tickets, invoices, domains
    - `update()`: validate fields, update record, return updated Customer
    - `destroy()`: delete record, return 204
    - Return 404 for non-existent customers, 422 for validation errors with field-level messages
    - _Requirements: 6.1–6.4, 7.1–7.6_

  - [x]* 5.2 Write property tests for customer management (Properties 9, 10, 12–14, 19)
    - **Property 9: List filtering returns only matching records (customer search)**
    - **Property 10: Pagination respects bounds and includes metadata**
    - **Property 12: CRUD create returns 201 with correct defaults**
    - **Property 13: CRUD update reflects submitted changes**
    - **Property 14: Validation rejects invalid payloads with 422**
    - **Property 19: Show endpoint returns related counts for Customer**
    - **Validates: Requirements 6.1–6.4, 7.1–7.6**

- [x] 6. Service CRUD API
  - [x] 6.1 Implement ServiceController with index, store, show, update, destroy
    - Create `app/Http/Controllers/Api/Admin/ServiceController.php`
    - `index()`: paginated list (default 15, max 100), filter by `?status=` param, return service_short, service_type, domain_name, status, service_monthly_charge, customer name + pagination meta
    - `store()`: validate company_id exists, service_short, status required, create Service, return 201
    - `show()`: return all Service fields including associated customer name
    - `update()`: validate fields, update record, return updated Service
    - `destroy()`: delete record, return 204
    - Return 404 for non-existent services, 422 for validation errors
    - _Requirements: 8.1–8.4, 9.1–9.6_

  - [x]* 6.2 Write property tests for service management (Properties 9, 10, 12–14)
    - **Property 9: List filtering returns only matching records (service status filter)**
    - **Property 10: Pagination respects bounds and includes metadata**
    - **Property 12: CRUD create returns 201 with correct defaults**
    - **Property 13: CRUD update reflects submitted changes**
    - **Property 14: Validation rejects invalid payloads with 422**
    - **Validates: Requirements 8.1–8.4, 9.1–9.6**

- [x] 7. Ticket Management API
  - [x] 7.1 Implement TicketController with index, store, show, update, and reply
    - Create `app/Http/Controllers/Api/Admin/TicketController.php`
    - `index()`: paginated (default 15, max 100), filter by `?status=` and/or `?priority=`, order by created_at desc, return subject, status, priority, customer_name, assigned_user_name, created_at + pagination meta
    - `store()`: validate company_id, subject, description required, create Ticket with status='Open', return 201
    - `show()`: return all Ticket fields (description, customer_name, assigned_user_name, asset, service details) + replies ordered by created_at asc + activities ordered by created_at asc
    - `update()`: validate status/priority/user_id fields, update Ticket, return updated record
    - `reply()`: validate body required, create TicketReply linked to ticket and authenticated user, set first_replied_at if this is the first reply (first_replied_at IS NULL), return 201
    - Return 404 for non-existent tickets, 422 for validation errors
    - _Requirements: 10.1–10.6, 11.1–11.4, 12.1–12.5_

  - [x]* 7.2 Write property tests for ticket management (Properties 9, 10, 12–16)
    - **Property 9: List filtering returns only matching records (ticket status+priority)**
    - **Property 10: Pagination respects bounds and includes metadata**
    - **Property 12: CRUD create returns 201 with status defaulting to Open**
    - **Property 13: CRUD update reflects submitted changes**
    - **Property 14: Validation rejects invalid payloads with 422**
    - **Property 15: First reply sets first_replied_at exactly once**
    - **Property 16: Ticket show returns replies and activities in chronological order**
    - **Validates: Requirements 10.1–10.6, 11.1–11.4, 12.1–12.5**

- [x] 8. Invoice Management API
  - [x] 8.1 Implement InvoiceController with index, store, and remind
    - Create `app/Http/Controllers/Api/Admin/InvoiceController.php`
    - `index()`: paginated (default 15, max 100), filter by `?status=`, exclude Void/Uncollectible by default, order by invoice_date desc, return invoice_status, invoice_amount, invoice_date, due_date, paid_date, customer name + pagination meta
    - `store()`: validate company_id exists, invoice_amount (positive decimal, max 2dp), invoice_items (required), due_date required; create Invoice with invoice_status='Unpaid', invoice_date=today; return 201
    - `remind()`: check invoice exists (404 if not), check invoice_status is not Paid (422 if paid), dispatch payment reminder notification, return success confirmation
    - _Requirements: 13.1–13.5, 14.1–14.3, 15.1–15.3_

  - [x]* 8.2 Write property tests for invoice management (Properties 9–12, 14)
    - **Property 9: List filtering returns only matching records (invoice status)**
    - **Property 10: Pagination respects bounds and includes metadata**
    - **Property 11: Invoice list excludes Void and Uncollectible by default**
    - **Property 12: CRUD create returns 201 with status=Unpaid, date=today**
    - **Property 14: Validation rejects invalid payloads with 422 (negative amount, missing fields)**
    - **Validates: Requirements 13.1–13.5, 14.1–14.3, 15.1–15.3**

- [x] 9. Checkpoint — All CRUD API endpoints complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Push Notifications Backend
  - [x] 10.1 Implement DeviceTokenController with store and destroy
    - Create `app/Http/Controllers/Api/Admin/DeviceTokenController.php`
    - `store()`: validate token string required, upsert device token for authenticated user (`updateOrCreate` on user_id+token), touch updated_at, return 201
    - `destroy()`: delete device token for authenticated user matching provided token, return 204
    - _Requirements: 16.2, 16.3, 16.4_

  - [x] 10.2 Create APNs notification channel and notification classes
    - Create `app/Notifications/Channels/ApnChannel.php` — custom channel that sends push via APNs HTTP/2 using stored device tokens
    - Create `app/Notifications/NewTicketNotification.php` — alert with ticket subject + customer name
    - Create `app/Notifications/InvoiceOverdueNotification.php` — alert with invoice amount + customer name
    - Create `app/Notifications/CriticalTicketNotification.php` — alert with ticket subject + critical indicator
    - Each notification implements `via()` returning ApnChannel and `toApn()` returning payload with alert title, body, badge, and sound
    - _Requirements: 17.1, 17.2, 17.3, 17.4_

  - [x] 10.3 Wire notification dispatching into ticket and invoice events
    - Dispatch `NewTicketNotification` to all admin device tokens when a ticket is created (in TicketController@store)
    - Dispatch `CriticalTicketNotification` when a ticket's priority is changed to Critical (in TicketController@update)
    - Dispatch `InvoiceOverdueNotification` via a scheduled Artisan command that checks for newly overdue invoices (due_date passed today, status=Unpaid)
    - Handle invalid device token cleanup: remove tokens that APNs reports as invalid (in ApnChannel error handling)
    - _Requirements: 17.1, 17.2, 17.3, 17.5_

  - [x]* 10.4 Write property tests for device token management (Properties 17, 18)
    - **Property 17: Device token registration is idempotent**
    - **Property 18: Logout removes associated device token**
    - **Validates: Requirements 16.2, 16.3, 16.4**

- [x] 11. Checkpoint — Backend API complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 12. iOS app scaffolding and networking layer
  - [x] 12.1 Create Xcode project structure and base app entry point
    - Create `ios/CKAdmin/` directory with Xcode project structure (CKAdmin.xcodeproj)
    - Create `ios/CKAdmin/CKAdmin/CKAdminApp.swift` as SwiftUI `@main` entry point
    - Set minimum deployment target to iOS 17.0
    - Create directory structure: Services/, Views/Auth/, Views/Dashboard/, Views/Customers/, Views/Services/, Views/Tickets/, Views/Invoices/, Models/
    - No third-party dependencies — URLSession with async/await only
    - _Requirements: 20.5_

  - [x] 12.2 Implement AuthManager with Keychain storage and login/logout flows
    - Create `ios/CKAdmin/CKAdmin/Services/AuthManager.swift`
    - Use `@Observable` macro (iOS 17+)
    - Store/retrieve Sanctum token from iOS Keychain (using Security framework)
    - `login(email:password:) async throws` → POST /api/admin/auth/login, store token on success
    - `logout() async` → POST /api/admin/auth/logout, clear Keychain token
    - `handleUnauthorized()` → clear token, reset auth state (triggers navigation to login)
    - Expose `isAuthenticated: Bool` computed property (true when token is non-nil)
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5_

  - [x] 12.3 Implement APIClient with token-bearing requests and error mapping
    - Create `ios/CKAdmin/CKAdmin/Services/APIClient.swift`
    - Define `Endpoint` struct with method, path, query parameters, and optional body
    - Implement generic `request<T: Decodable>(_ endpoint: Endpoint) async throws -> T`
    - Attach Bearer token from AuthManager to Authorization header on every request
    - Map HTTP status codes to typed `APIError` enum: 401→unauthenticated, 403→forbidden(String), 404→notFound, 422→validationFailed([String:[String]]), network→networkError(Error), 5xx→serverError
    - Auto-trigger `authManager.handleUnauthorized()` on 401 responses
    - _Requirements: 18.3, 18.4, 20.2, 20.3_

- [x] 13. iOS Authentication UI
  - [x] 13.1 Create LoginView with email/password fields and auth state routing
    - Create `ios/CKAdmin/CKAdmin/Views/Auth/LoginView.swift`
    - Email and password SecureField inputs
    - Login button calling `authManager.login(email:password:)`
    - Display error messages for 401 (invalid credentials) and 403 (locked/non-admin)
    - Show ProgressView during login request
    - Update `CKAdminApp.swift` to conditionally show LoginView or main ContentView based on `authManager.isAuthenticated`
    - _Requirements: 18.1, 18.4, 20.2_

- [x] 14. iOS Dashboard and Navigation
  - [x] 14.1 Implement tab-based navigation with main ContentView
    - Create `ios/CKAdmin/CKAdmin/Views/ContentView.swift` with TabView
    - Five tabs: Dashboard, Customers, Services, Tickets, Invoices (each with SF Symbol icon)
    - Each tab wraps its content in a NavigationStack
    - Add logout action accessible from toolbar/settings
    - _Requirements: 20.1, 18.5_

  - [x] 14.2 Implement DashboardView with KPI metrics and recent activity
    - Create `ios/CKAdmin/CKAdmin/Views/Dashboard/DashboardView.swift`
    - Create `DashboardViewModel` (`@Observable`) that fetches GET /api/admin/dashboard via APIClient
    - Ticket stats section: open, critical, high, overdue counts + avg response time
    - Financial metrics section: MRR, ARR formatted as currency, overdue invoice count/amount, revenue this month
    - Expiring domains section: list with domain name, customer name, days until expiry
    - Recent tickets section: list with subject, customer name, status badge
    - Recent logins section: list with name and relative time
    - Pull-to-refresh via `.refreshable` modifier
    - Loading state (ProgressView) and error state with retry button
    - _Requirements: 19.1–19.6, 20.2, 20.3_

- [x] 15. iOS Customer Screens
  - [x] 15.1 Implement CustomerListView with search and pagination
    - Create `ios/CKAdmin/CKAdmin/Views/Customers/CustomerListView.swift`
    - Create `CustomerListViewModel` (`@Observable`) with paginated loading (infinite scroll via `.onAppear` on last item)
    - Display company_name, customer_name, phone_number per row
    - Searchable modifier with search query sent as API `?search=` parameter
    - Loading indicator and error state with retry
    - _Requirements: 6.1–6.4, 20.2, 20.3, 20.4_

  - [x] 15.2 Implement CustomerDetailView and CustomerFormView for CRUD
    - Create `ios/CKAdmin/CKAdmin/Views/Customers/CustomerDetailView.swift` — displays all customer fields + services_count, tickets_count, invoices_count, domains_count
    - Create `ios/CKAdmin/CKAdmin/Views/Customers/CustomerFormView.swift` — reusable form for create and edit modes
    - Navigation: list row tap → detail, detail toolbar → edit (form in edit mode), list toolbar → create (form in create mode)
    - Delete action with confirmation alert in detail view
    - Display field-level validation errors from 422 responses
    - _Requirements: 7.1–7.6, 20.2, 20.3_

- [x] 16. iOS Service Screens
  - [x] 16.1 Implement ServiceListView with status filter and pagination
    - Create `ios/CKAdmin/CKAdmin/Views/Services/ServiceListView.swift`
    - Create `ServiceListViewModel` (`@Observable`) with paginated loading and status filter
    - Display service_short, service_type, domain_name, status, service_monthly_charge, customer name per row
    - Picker or segmented control for status filter
    - Infinite scroll pagination, loading/error states
    - _Requirements: 8.1–8.4, 20.2, 20.3, 20.4_

  - [x] 16.2 Implement ServiceDetailView and ServiceFormView for CRUD
    - Create `ios/CKAdmin/CKAdmin/Views/Services/ServiceDetailView.swift` — all fields + customer name
    - Create `ios/CKAdmin/CKAdmin/Views/Services/ServiceFormView.swift` — reusable form for create/edit with customer picker
    - Navigation: list → detail → edit, list → create
    - Delete action with confirmation, validation error display
    - _Requirements: 9.1–9.6, 20.2, 20.3_

- [x] 17. iOS Ticket Screens
  - [x] 17.1 Implement TicketListView with status/priority filters and pagination
    - Create `ios/CKAdmin/CKAdmin/Views/Tickets/TicketListView.swift`
    - Create `TicketListViewModel` (`@Observable`) with paginated loading, status filter, priority filter
    - Display subject, status badge, priority badge, customer name, assigned user, created_at per row
    - Filter pickers for status and priority (can combine)
    - Infinite scroll, loading/error states
    - _Requirements: 10.1–10.6, 20.2, 20.3, 20.4_

  - [x] 17.2 Implement TicketDetailView with thread and reply functionality
    - Create `ios/CKAdmin/CKAdmin/Views/Tickets/TicketDetailView.swift`
    - Header section: full ticket details (description, customer, assignee, asset, service info)
    - Conversation thread: replies ordered chronologically with author name and timestamp
    - Activity log section: activities ordered chronologically
    - Reply composer: TextEditor + send button, POST /api/admin/tickets/{id}/replies
    - Action sheet/toolbar: update status, priority, assignee via picker
    - Loading/error states
    - _Requirements: 11.1–11.4, 12.2, 12.3, 12.4, 20.2, 20.3_

  - [x] 17.3 Implement TicketCreateView for new ticket creation
    - Create `ios/CKAdmin/CKAdmin/Views/Tickets/TicketCreateView.swift`
    - Form fields: customer picker (company_id), subject TextField, description TextEditor
    - Submit via POST /api/admin/tickets, dismiss on success
    - Display validation errors from 422 responses
    - _Requirements: 12.1, 12.5, 20.2_

- [x] 18. iOS Invoice Screens
  - [x] 18.1 Implement InvoiceListView with status filter and pagination
    - Create `ios/CKAdmin/CKAdmin/Views/Invoices/InvoiceListView.swift`
    - Create `InvoiceListViewModel` (`@Observable`) with paginated loading and optional status filter
    - Display invoice_status badge, invoice_amount (currency formatted), invoice_date, due_date, paid_date, customer name per row
    - Void/Uncollectible excluded by default (API handles this)
    - Filter picker for status
    - "Send Reminder" swipe action on overdue invoices (calls POST /api/admin/invoices/{id}/remind)
    - _Requirements: 13.1–13.5, 15.1–15.2, 20.2, 20.3, 20.4_

  - [x] 18.2 Implement InvoiceCreateView for one-off invoice creation
    - Create `ios/CKAdmin/CKAdmin/Views/Invoices/InvoiceCreateView.swift`
    - Form fields: customer picker (company_id), amount (decimal input with 2dp validation), line items (dynamic list), due date (DatePicker)
    - Submit via POST /api/admin/invoices, dismiss on success
    - Display validation errors from 422 responses (negative amount, missing fields)
    - _Requirements: 14.1–14.3, 20.2, 20.3_

- [x] 19. Checkpoint — iOS CRUD screens complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 20. iOS Push Notifications
  - [x] 20.1 Implement PushManager for APNs registration and token forwarding
    - Create `ios/CKAdmin/CKAdmin/Services/PushManager.swift`
    - Request notification authorization via `UNUserNotificationCenter.requestAuthorization(options: [.alert, .badge, .sound])`
    - Call `UIApplication.shared.registerForRemoteNotifications()` on grant
    - In AppDelegate or via notification center delegate: on `didRegisterForRemoteNotificationsWithDeviceToken`, convert Data to hex string, POST to /api/admin/device-tokens
    - On logout: call DELETE /api/admin/device-tokens to unregister device
    - Handle notification tap via `userNotificationCenter(_:didReceive:)` to navigate to relevant screen
    - _Requirements: 16.1, 16.2, 16.4, 17.4_

- [x] 21. Final checkpoint — Full integration
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- The iOS app uses `@Observable` (iOS 17+) instead of `ObservableObject` for cleaner state management
- No third-party networking dependencies — URLSession with async/await is sufficient for the iOS client
- Backend API controllers live in `app/Http/Controllers/Api/Admin/` — separate from existing Blade controllers in `app/Http/Controllers/Admin/`
- The existing `DashboardController` logic should be referenced when building `DashboardService` to ensure metric consistency
- `routes/api.php` does not currently exist and must be created and registered

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "1.4"] },
    { "id": 2, "tasks": ["2.1", "3.1", "12.1"] },
    { "id": 3, "tasks": ["2.2", "3.2", "12.2"] },
    { "id": 4, "tasks": ["3.3", "12.3", "5.1"] },
    { "id": 5, "tasks": ["3.4", "5.2", "6.1", "13.1"] },
    { "id": 6, "tasks": ["6.2", "7.1", "8.1", "14.1"] },
    { "id": 7, "tasks": ["7.2", "8.2", "10.1", "14.2"] },
    { "id": 8, "tasks": ["10.2", "10.4", "15.1"] },
    { "id": 9, "tasks": ["10.3", "15.2", "16.1"] },
    { "id": 10, "tasks": ["16.2", "17.1", "18.1"] },
    { "id": 11, "tasks": ["17.2", "17.3", "18.2"] },
    { "id": 12, "tasks": ["20.1"] }
  ]
}
```
