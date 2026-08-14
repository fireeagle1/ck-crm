# Design Document: iOS Admin App (MVP — Phase 1)

## Introduction

This document describes the technical architecture for extending the CK CRM Laravel application with a Sanctum-authenticated JSON API and a native SwiftUI iOS client. The API layer lives in the same Laravel 13 project under `routes/api.php`, while the iOS app is a separate SwiftUI package in `ios/CKAdmin/`.

---

## Architecture Overview

```
┌──────────────────┐         HTTPS/JSON          ┌──────────────────────────────┐
│   iOS App        │ ◀───────────────────────────▶│  Laravel API (routes/api.php)│
│   (SwiftUI)      │   Bearer Token (Sanctum)     │  /api/admin/*                │
│   iOS 17+        │                              │                              │
└──────────────────┘                              │  Middleware Stack:           │
        │                                         │   auth:sanctum               │
        │ APNs                                    │   EnsureIsAdmin              │
        ▼                                         └──────────────────────────────┘
┌──────────────────┐                                         │
│  Apple Push      │                                         │
│  Notification    │◀────────────────────────────────────────┘
│  Service         │         (server-side push via APNs)
└──────────────────┘
```

**Key architectural decisions:**

1. **Separate API controllers** — New controllers in `app/Http/Controllers/Api/Admin/` avoid conflating Blade-returning logic with JSON responses.
2. **Sanctum token auth** — Pure personal access token flow (no SPA cookie mode) for mobile compatibility.
3. **Shared models** — API controllers reuse existing Eloquent models (Customer, Service, Ticket, Invoice, Domain) and their relationships.
4. **Extracted service classes** — Complex business logic (MRR calculation, dashboard aggregation) is extracted into service classes reusable by both Blade and API controllers.
5. **Push via Laravel Notifications** — A custom `ApnChannel` dispatches notifications through APNs using device tokens stored in a new `device_tokens` table.

---

## Components

### Backend (Laravel)

| Component | Location | Responsibility |
|-----------|----------|----------------|
| API Routes | `routes/api.php` | Route definitions under `/api/admin` prefix |
| AuthController | `app/Http/Controllers/Api/Admin/AuthController.php` | Login, logout, token refresh |
| DashboardController | `app/Http/Controllers/Api/Admin/DashboardController.php` | Dashboard KPI aggregation |
| CustomerController | `app/Http/Controllers/Api/Admin/CustomerController.php` | Customer CRUD + search |
| ServiceController | `app/Http/Controllers/Api/Admin/ServiceController.php` | Service CRUD + filter |
| TicketController | `app/Http/Controllers/Api/Admin/TicketController.php` | Ticket CRUD, replies, activity |
| InvoiceController | `app/Http/Controllers/Api/Admin/InvoiceController.php` | Invoice list, create, remind |
| DeviceTokenController | `app/Http/Controllers/Api/Admin/DeviceTokenController.php` | APNs device token registration |
| DashboardService | `app/Services/DashboardService.php` | Extracted dashboard metrics logic |
| MrrCalculator | `app/Services/MrrCalculator.php` | MRR calculation (extracted from existing controller) |
| DeviceToken (Model) | `app/Models/DeviceToken.php` | Eloquent model for `device_tokens` table |
| ApnChannel | `app/Notifications/Channels/ApnChannel.php` | Custom notification channel for APNs |
| NewTicketNotification | `app/Notifications/NewTicketNotification.php` | Push notification for new tickets |
| InvoiceOverdueNotification | `app/Notifications/InvoiceOverdueNotification.php` | Push notification for overdue invoices |
| CriticalTicketNotification | `app/Notifications/CriticalTicketNotification.php` | Push notification for critical tickets |
| EnsureIsAdmin (modified) | `app/Http/Middleware/EnsureIsAdmin.php` | Updated to return JSON 403 for API requests |

### iOS App (SwiftUI)

| Component | Location | Responsibility |
|-----------|----------|----------------|
| CKAdminApp | `ios/CKAdmin/CKAdmin/CKAdminApp.swift` | App entry point, root navigation |
| AuthManager | `ios/CKAdmin/CKAdmin/Services/AuthManager.swift` | Token storage (Keychain), auth state |
| APIClient | `ios/CKAdmin/CKAdmin/Services/APIClient.swift` | HTTP client, request/response handling |
| PushManager | `ios/CKAdmin/CKAdmin/Services/PushManager.swift` | APNs registration, token forwarding |
| DashboardView | `ios/CKAdmin/CKAdmin/Views/Dashboard/` | Dashboard screen and sub-views |
| CustomersView | `ios/CKAdmin/CKAdmin/Views/Customers/` | Customer list, detail, edit screens |
| ServicesView | `ios/CKAdmin/CKAdmin/Views/Services/` | Service list, detail, edit screens |
| TicketsView | `ios/CKAdmin/CKAdmin/Views/Tickets/` | Ticket list, detail, reply screens |
| InvoicesView | `ios/CKAdmin/CKAdmin/Views/Invoices/` | Invoice list, create screen |

---

## Interfaces

### API Route Definitions

All routes prefixed with `/api/admin`. All authenticated routes use middleware: `auth:sanctum`, `App\Http\Middleware\EnsureIsAdmin`.

#### Authentication

```php
// routes/api.php
Route::prefix('admin')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', EnsureIsAdmin::class])->group(function () {
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        // ... all other routes
    });
});
```

#### Endpoint Summary

| Method | Endpoint | Controller@Method | Description |
|--------|----------|-------------------|-------------|
| POST | `/api/admin/auth/login` | AuthController@login | Issue token |
| POST | `/api/admin/auth/refresh` | AuthController@refresh | Rotate token |
| POST | `/api/admin/auth/logout` | AuthController@logout | Revoke token |
| GET | `/api/admin/dashboard` | DashboardController@index | All KPIs |
| GET | `/api/admin/customers` | CustomerController@index | List (paginated, searchable) |
| POST | `/api/admin/customers` | CustomerController@store | Create |
| GET | `/api/admin/customers/{customer}` | CustomerController@show | Detail with counts |
| PUT | `/api/admin/customers/{customer}` | CustomerController@update | Update |
| DELETE | `/api/admin/customers/{customer}` | CustomerController@destroy | Delete |
| GET | `/api/admin/services` | ServiceController@index | List (paginated, filterable) |
| POST | `/api/admin/services` | ServiceController@store | Create |
| GET | `/api/admin/services/{service}` | ServiceController@show | Detail |
| PUT | `/api/admin/services/{service}` | ServiceController@update | Update |
| DELETE | `/api/admin/services/{service}` | ServiceController@destroy | Delete |
| GET | `/api/admin/tickets` | TicketController@index | List (paginated, filterable) |
| POST | `/api/admin/tickets` | TicketController@store | Create |
| GET | `/api/admin/tickets/{ticket}` | TicketController@show | Detail + replies + activity |
| PUT | `/api/admin/tickets/{ticket}` | TicketController@update | Update status/priority/assignee |
| POST | `/api/admin/tickets/{ticket}/replies` | TicketController@reply | Add reply |
| GET | `/api/admin/invoices` | InvoiceController@index | List (paginated, filterable) |
| POST | `/api/admin/invoices` | InvoiceController@store | Create one-off |
| POST | `/api/admin/invoices/{invoice}/remind` | InvoiceController@remind | Send reminder |
| POST | `/api/admin/device-tokens` | DeviceTokenController@store | Register device |
| DELETE | `/api/admin/device-tokens` | DeviceTokenController@destroy | Unregister device |

### JSON Response Structures

#### Login Response (POST /api/admin/auth/login)

```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com"
  }
}
```

#### Dashboard Response (GET /api/admin/dashboard)

```json
{
  "tickets": {
    "open_count": 12,
    "critical_count": 2,
    "high_count": 5,
    "overdue_count": 3,
    "avg_response_time_minutes": 45.5
  },
  "financials": {
    "mrr": 8500.00,
    "arr": 102000.00,
    "overdue_invoices_count": 4,
    "overdue_invoices_amount": 2340.50,
    "revenue_this_month": 12500.00
  },
  "recent_tickets": [
    {
      "ticket_id": 101,
      "subject": "Server down",
      "customer_name": "Acme Corp",
      "assigned_user_name": "John Smith",
      "status": "Open",
      "priority": "Critical"
    }
  ],
  "recent_logins": [
    {
      "user_name": "John Smith",
      "last_login": "2025-01-15T09:30:00Z"
    }
  ],
  "expiring_domains": [
    {
      "domain_name": "example.com",
      "customer_name": "Acme Corp",
      "expiry_date": "2025-02-01",
      "days_until_expiry": 17
    }
  ]
}
```

#### Paginated List Response (e.g., GET /api/admin/customers)

```json
{
  "data": [
    {
      "company_id": 1,
      "company_name": "Acme Corp",
      "customer_name": "John Doe",
      "phone_number": "+44 123 456 7890"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

#### Error Response (Validation — 422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "company_name": ["The company name field is required."],
    "email": ["The email has already been taken."]
  }
}
```

#### Error Response (Auth — 401/403)

```json
{
  "message": "Invalid credentials."
}
```

---

## Data Models

### New Model: DeviceToken

```php
// app/Models/DeviceToken.php
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',  // always 'ios' for now
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### New Migration: device_tokens

```php
Schema::create('device_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('token')->unique();
    $table->string('platform')->default('ios');
    $table->timestamps();

    $table->index(['user_id', 'platform']);
});
```

### Existing Models (No Schema Changes)

| Model | Primary Key | Key Relationships Used by API |
|-------|-------------|-------------------------------|
| User | `id` (default) | `customer()`, `tickets()` |
| Customer | `company_id` | `services()`, `tickets()`, `invoices()`, `domains()` |
| Service | `service_id` | `customer()` |
| Ticket | `ticket_id` | `customer()`, `user()`, `replies()`, `activities()`, `asset()`, `service()` |
| TicketReply | `id` (default) | `ticket()`, `user()` |
| TicketActivity | `id` (default) | `ticket()`, `user()` |
| Invoice | `invoice_id` | `customer()` |
| Domain | `id` (default) | `customer()` |

### User Model Addition

Add `HasApiTokens` trait for Sanctum:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    // ...
}
```

---

## Service Classes

### MrrCalculator

Extracted from the existing `DashboardController::calculateMrr()` method:

```php
// app/Services/MrrCalculator.php
namespace App\Services;

use App\Models\Service;

class MrrCalculator
{
    public function calculate(): float
    {
        $services = Service::where('status', 'Active')
            ->whereNotNull('service_monthly_charge')
            ->where('service_monthly_charge', '>', 0)
            ->get(['service_monthly_charge', 'service_payment_frequency', 'stripe_subscription_id']);

        return $services->sum(function ($service) {
            $charge = (float) $service->service_monthly_charge;

            if (empty($service->stripe_subscription_id)) {
                return $charge;
            }

            $months = match ($service->service_payment_frequency) {
                'Weekly'     => 0.25,
                'Monthly'    => 1,
                'Quarterly'  => 3,
                'Biannually' => 6,
                'Annually'   => 12,
                'Biennially' => 24,
                default      => 1,
            };

            return $charge / $months;
        });
    }
}
```

### DashboardService

Aggregates all dashboard KPIs into a single array for both the Blade and API controllers:

```php
// app/Services/DashboardService.php
namespace App\Services;

use App\Models\{Customer, Domain, Invoice, Service, Ticket, User};

class DashboardService
{
    public function __construct(private MrrCalculator $mrrCalculator) {}

    public function getMetrics(): array
    {
        $mrr = $this->mrrCalculator->calculate();

        return [
            'tickets' => $this->ticketStats(),
            'financials' => $this->financialStats($mrr),
            'recent_tickets' => $this->recentTickets(),
            'recent_logins' => $this->recentLogins(),
            'expiring_domains' => $this->expiringDomains(),
        ];
    }

    // private helper methods for each section...
}
```

---

## Error Handling

### API Error Response Strategy

All API errors follow a consistent JSON envelope:

| HTTP Status | Meaning | Response Shape |
|-------------|---------|----------------|
| 401 | Unauthenticated / invalid token | `{"message": "Unauthenticated."}` |
| 403 | Forbidden (not admin, locked) | `{"message": "...reason..."}` |
| 404 | Resource not found | `{"message": "...resource... not found."}` |
| 422 | Validation failed | `{"message": "...", "errors": {...}}` |
| 500 | Server error | `{"message": "Server error."}` (no stack in production) |

### Middleware Adaptation

The existing `EnsureIsAdmin` middleware uses `abort(403)` which returns HTML. For API requests, we detect the `Accept: application/json` header (or the request path starting with `api/`) and return a JSON response:

```php
public function handle(Request $request, Closure $next): Response
{
    // ... existing impersonation logic (N/A for API) ...

    if (! $request->user()?->isAdmin()) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }
        abort(403, 'Unauthorized');
    }

    return $next($request);
}
```

### iOS Error Handling

The `APIClient` maps HTTP status codes to typed Swift errors:

```swift
enum APIError: Error {
    case unauthenticated          // 401 → triggers logout
    case forbidden(String)        // 403
    case notFound                 // 404
    case validationFailed([String: [String]])  // 422
    case networkError(Error)      // connectivity issues
    case serverError              // 5xx
}
```

---

## iOS App Architecture

### Pattern: MVVM with Observable

```
View (SwiftUI) ──▶ ViewModel (@Observable) ──▶ APIClient ──▶ Laravel API
                         │
                         ▼
                   AuthManager (Keychain)
```

### AuthManager (Keychain Storage)

```swift
@Observable
final class AuthManager {
    var isAuthenticated: Bool { token != nil }
    private(set) var token: String?
    private let keychainKey = "com.ckenterprises.admin.token"

    func login(email: String, password: String) async throws { ... }
    func logout() async { ... }
    func handleUnauthorized() { ... }  // clears token, resets to login
}
```

### APIClient (Networking)

```swift
final class APIClient {
    private let baseURL: URL
    private let authManager: AuthManager

    func request<T: Decodable>(_ endpoint: Endpoint) async throws -> T { ... }
    // Automatically attaches Bearer token
    // Automatically calls authManager.handleUnauthorized() on 401
}
```

### Push Notification Flow

```
1. App launch → UNUserNotificationCenter.requestAuthorization()
2. Granted → UIApplication.registerForRemoteNotifications()
3. Delegate callback → didRegisterForRemoteNotificationsWithDeviceToken
4. PushManager → POST /api/admin/device-tokens { token: hex_string }
5. On logout → DELETE /api/admin/device-tokens (server removes token)
```

---

## Security Considerations

1. **Token storage**: iOS Keychain (not UserDefaults) for Sanctum tokens.
2. **Token rotation**: Refresh endpoint revokes old token and issues new one atomically.
3. **Account lockout**: API respects existing `is_locked` / `lock_until` fields — locked users get 403.
4. **Rate limiting**: Laravel's built-in throttle middleware on login endpoint (5 attempts/minute).
5. **Input validation**: All create/update endpoints use Form Request validation with explicit rules.
6. **HTTPS only**: iOS App Transport Security enforces TLS. API should be served behind HTTPS.
7. **Device token cleanup**: Invalid APNs tokens are pruned on feedback from Apple.

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Acceptance Criteria Testing Prework

**1.1** WHEN an Admin_User submits valid credentials, THE API SHALL issue a Sanctum_Token
  Thoughts: This is a fundamental auth behavior. We can generate random valid admin users and verify login always produces a token.
  Classification: PROPERTY
  Test Strategy: For any valid admin user, login returns a token string.

**1.2** WHEN a user submits invalid credentials, THE API SHALL return 401
  Thoughts: We can test with random wrong passwords and verify 401 is always returned.
  Classification: PROPERTY
  Test Strategy: For any user with incorrect password, response is always 401.

**1.3** WHEN a locked user submits credentials, THE API SHALL return 403
  Thoughts: For any user that is locked (is_locked=true OR lock_until in future), login must fail with 403.
  Classification: PROPERTY
  Test Strategy: Generate users with various lock states, verify 403.

**1.4** WHEN a non-admin user submits valid credentials, THE API SHALL return 403
  Thoughts: For any user where is_admin=false, login must fail with 403 even with correct password.
  Classification: PROPERTY
  Test Strategy: Generate non-admin users with valid credentials, verify 403.

**1.5** THE API SHALL return 422 when email or password is missing
  Thoughts: This is an edge case / validation rule. Two specific cases: missing email, missing password.
  Classification: EDGE_CASE
  Test Strategy: Test with missing email, missing password, both missing.

**2.1** Token refresh revokes old and issues new
  Thoughts: This is a round-trip-like property: refresh(token_old) → token_new, then token_old is invalid and token_new is valid.
  Classification: PROPERTY
  Test Strategy: For any authenticated user, after refresh, old token is rejected and new token works.

**2.2** Logout revokes token
  Thoughts: After logout, the token must be invalid. Universal across all sessions.
  Classification: PROPERTY
  Test Strategy: For any authenticated session, after logout, the token returns 401.

**2.3** Expired/revoked token returns 401
  Thoughts: This is testing Sanctum's built-in behavior combined with our middleware. Can test by revoking and then making a request.
  Classification: EXAMPLE
  Test Strategy: Revoke a token, make request, verify 401.

**2.4** All admin routes behind auth:sanctum + EnsureIsAdmin
  Thoughts: This is a structural/config check. We can verify by attempting unauthenticated access to every endpoint.
  Classification: PROPERTY
  Test Strategy: For any admin endpoint, unauthenticated request returns 401.

**3.1–3.5** Dashboard ticket statistics
  Thoughts: These are computations over data. We can seed random ticket data and verify the counts match expected calculations. All 5 criteria test the same endpoint with the same data model.
  Classification: PROPERTY
  Test Strategy: For any set of tickets, the dashboard response counts match the actual DB state.

**4.1** MRR normalization
  Thoughts: This is a pure calculation over services. For any set of active services with various billing frequencies, MRR must equal the sum of each service's charge divided by its billing period in months.
  Classification: PROPERTY
  Test Strategy: For any collection of services with varying frequencies, calculated MRR matches formula.

**4.2** ARR = MRR × 12
  Thoughts: This is a simple derivation. If MRR is correct, ARR follows. Can be combined with 4.1.
  Classification: PROPERTY
  Test Strategy: Verify ARR equals MRR × 12 for any state.

**4.3** Overdue invoices count excludes Void/Uncollectible
  Thoughts: For any set of invoices, overdue count must only include those that are Unpaid, past due, and NOT Void/Uncollectible.
  Classification: PROPERTY
  Test Strategy: Seed invoices with various statuses and dates, verify count.

**4.4** Overdue invoices amount precision
  Thoughts: Same filter as 4.3 but sum of amounts. Combined with 4.3.
  Classification: PROPERTY
  Test Strategy: Verify sum matches expected with 2 decimal precision.

**4.5** Revenue this month excludes Void/Uncollectible
  Thoughts: For any set of invoices, revenue must only sum Paid invoices in current month, excluding Void/Uncollectible.
  Classification: PROPERTY
  Test Strategy: Seed invoices across dates/statuses, verify sum.

**5.1** Recent tickets — top 5 most recent
  Thoughts: For any set of tickets, the response contains exactly the 5 most recently created.
  Classification: PROPERTY
  Test Strategy: Seed N tickets, verify response contains the 5 with latest created_at.

**5.2** Recent logins — top 5
  Thoughts: For any set of admin users, the response contains the 5 with most recent last_login.
  Classification: PROPERTY
  Test Strategy: Seed admin users with various last_login timestamps, verify top 5.

**5.3** Expiring domains — next 30 days, ordered ascending
  Thoughts: For any set of domains, the response contains those expiring within 30 days, ordered by expiry_date asc, max 5.
  Classification: PROPERTY
  Test Strategy: Seed domains with various expiry dates, verify filtering and ordering.

**6.1** Customer list is paginated with correct fields
  Thoughts: For any page of customer data, the response contains the expected fields and pagination metadata.
  Classification: PROPERTY
  Test Strategy: Seed customers, verify page contents match expected slice.

**6.2** Search filters by name/phone
  Thoughts: For any search query and customer set, returned results all match the query against company_name, customer_name, or phone_number.
  Classification: PROPERTY
  Test Strategy: Generate random customers and search terms, verify all results contain the term.

**6.3** Pagination defaults and max
  Thoughts: Default page size is 15, max is 100. If per_page > 100, should cap at 100.
  Classification: PROPERTY
  Test Strategy: For any per_page value, actual page size is min(per_page, 100) with default 15.

**6.4** Pagination metadata present
  Thoughts: Every paginated response must include current_page, last_page, total, per_page.
  Classification: PROPERTY
  Test Strategy: For any list request, response contains all 4 meta fields.

**7.1** Show returns all fields + relation counts
  Thoughts: For any customer, show endpoint returns all customer fields and counts of related entities.
  Classification: PROPERTY
  Test Strategy: For any customer with varying relations, counts match actual.

**7.2** Create returns 201
  Thoughts: For any valid customer payload, a new record is created and returned with 201.
  Classification: PROPERTY
  Test Strategy: Generate valid payloads, verify 201 and record exists in DB.

**7.3** Update returns updated fields
  Thoughts: For any valid partial update, the returned customer reflects the changes.
  Classification: PROPERTY
  Test Strategy: Generate random field updates, verify response matches changes.

**7.4** Delete returns 204
  Thoughts: For any existing customer, delete removes it and returns 204.
  Classification: EXAMPLE
  Test Strategy: Create customer, delete, verify 204 and gone.

**7.5** Invalid data returns 422
  Thoughts: For any payload missing required fields, response is 422 with field errors.
  Classification: PROPERTY
  Test Strategy: Generate invalid payloads (empty company_name, etc.), verify 422.

**7.6** Non-existent customer returns 404
  Thoughts: Simple edge case.
  Classification: EDGE_CASE
  Test Strategy: Request non-existent company_id, verify 404.

**8.1–8.4** Service list/filter/pagination
  Thoughts: Same pattern as customers. Filtering by status is a universal property: all returned results must match the filter.
  Classification: PROPERTY
  Test Strategy: Same as customer list tests but with status filter.

**9.1–9.6** Service CRUD
  Thoughts: Same pattern as customer CRUD.
  Classification: PROPERTY (create/update/show), EDGE_CASE (404, validation)

**10.1–10.6** Ticket list/filter
  Thoughts: Filter by status, priority, or both. All results must satisfy the filter criteria.
  Classification: PROPERTY
  Test Strategy: For any filter combination, all results match the filter.

**11.1–11.3** Ticket detail
  Thoughts: For any ticket, show returns all fields, replies ordered ascending, activities ordered ascending.
  Classification: PROPERTY
  Test Strategy: Seed ticket with replies/activities, verify ordering and completeness.

**11.4** Non-existent ticket 404
  Classification: EDGE_CASE

**12.1** Ticket create defaults status to Open
  Thoughts: For any valid ticket creation, status is always Open.
  Classification: PROPERTY
  Test Strategy: Create tickets with various payloads, verify status is always Open.

**12.2** Ticket update
  Thoughts: For any valid status/priority/user_id update, the ticket reflects the change.
  Classification: PROPERTY

**12.3** Reply creates TicketReply
  Thoughts: For any reply body, a new TicketReply is created linked to the ticket and user.
  Classification: PROPERTY

**12.4** First reply sets first_replied_at
  Thoughts: For any ticket with no prior replies, the first reply sets first_replied_at.
  Classification: PROPERTY

**12.5** Invalid data returns 422
  Classification: PROPERTY

**13.1–13.5** Invoice list/filter
  Thoughts: Similar to ticket/service listing. Default exclusion of Void/Uncollectible is a key filter property.
  Classification: PROPERTY
  Test Strategy: For any invoice set, list excludes Void/Uncollectible by default; filter by status works.

**14.1** Invoice create defaults
  Thoughts: For any valid payload, invoice_status defaults to Unpaid, invoice_date to today.
  Classification: PROPERTY

**14.2** Invalid invoice returns 422
  Classification: PROPERTY

**14.3** Amount validation
  Thoughts: For any non-positive or invalid decimal, reject with 422.
  Classification: PROPERTY

**15.1** Remind dispatches notification for unpaid
  Thoughts: For any unpaid invoice, remind dispatches a notification.
  Classification: EXAMPLE (side-effect verification)

**15.2** Remind on paid invoice returns 422
  Classification: EDGE_CASE

**15.3** Remind on non-existent invoice returns 404
  Classification: EDGE_CASE

**16.1** iOS sends device token (iOS client behavior)
  Classification: SMOKE (client integration)

**16.2** Store device token
  Thoughts: For any valid device token string, it is stored associated with the user.
  Classification: PROPERTY

**16.3** Duplicate token updates timestamp, no duplicate
  Thoughts: For any token submitted twice, only one record exists.
  Classification: PROPERTY (idempotence)

**16.4** Logout removes device token
  Thoughts: After logout, the device token record is deleted.
  Classification: PROPERTY

**17.1–17.4** Push notification triggers
  Thoughts: These are event-driven side effects involving APNs — an external service.
  Classification: INTEGRATION
  Test Strategy: Verify notification is dispatched (mock APNs) on event.

**17.5** Invalid token cleanup
  Thoughts: When APNs reports invalid, token is removed.
  Classification: INTEGRATION

**18.1–18.5** iOS Auth Flow
  Thoughts: These are iOS client UI behaviors.
  Classification: EXAMPLE (UI behavior)

**19.1–19.6** iOS Dashboard Display
  Thoughts: These are iOS client UI rendering behaviors.
  Classification: EXAMPLE (UI behavior)

**20.1–20.5** iOS Navigation
  Thoughts: These are iOS client UI/UX behaviors.
  Classification: EXAMPLE (UI behavior)

---

### Property Reflection

Reviewing all identified properties for redundancy:

1. **3.1–3.5** (ticket stats) can be combined into one property: "dashboard ticket statistics match DB state."
2. **4.1–4.2** (MRR/ARR) can be combined: "ARR = MRR × 12 and MRR is correct normalization."
3. **4.3–4.4** (overdue invoices count + amount) can be combined into one property about overdue invoice aggregation.
4. **6.1 + 6.4** (list fields + pagination metadata) can be combined: "paginated responses include correct data and metadata."
5. **6.3 + 8.3 + 10.5 + 13.3** (pagination defaults/max) are the same rule across resources — one property covers all.
6. **7.2 + 9.2 + 12.1 + 14.1** (create returns 201 with defaults) share the same pattern — but each has unique defaults, so keep separate but note the pattern.
7. **8.2 + 10.2 + 10.3 + 10.4 + 13.2** (filter matching) follow same pattern: "all results match filter criteria." One property per resource type is sufficient.
8. **7.5 + 9.5 + 12.5 + 14.2** (validation 422) same pattern — one property covers all resources.
9. **16.2 + 16.3** (store + idempotence) can combine: device token registration is idempotent.

After consolidation:

---

### Property 1: Login grants token only to valid unlocked admins

*For any* User record, the login endpoint SHALL issue a Sanctum token if and only if (1) the provided password matches, (2) `is_admin = true`, (3) `is_locked = false`, and (4) `lock_until` is null or in the past. In all other cases, the appropriate error status (401, 403) is returned.

**Validates: Requirements 1.1, 1.2, 1.3, 1.4**

### Property 2: Token refresh invalidates old token

*For any* authenticated admin session, after calling the refresh endpoint, the old token SHALL return 401 on subsequent requests and the new token SHALL authenticate successfully.

**Validates: Requirements 2.1**

### Property 3: Logout invalidates token

*For any* authenticated admin session, after calling the logout endpoint, the token SHALL return 401 on all subsequent requests.

**Validates: Requirements 2.2**

### Property 4: Unauthenticated requests are rejected

*For any* admin API endpoint (excluding login), a request without a valid Sanctum token SHALL return 401.

**Validates: Requirements 2.3, 2.4**

### Property 5: Dashboard ticket statistics match database state

*For any* set of Ticket records in the database, the dashboard endpoint SHALL return: open_count = count where status ∈ {Open, Pending, In Progress}, critical_count = count where status ∈ {Open, Pending, In Progress} AND priority = Critical, high_count = count where status ∈ {Open, Pending, In Progress} AND priority = High, overdue_count = count where status ∈ {Open, Pending, In Progress} AND due_at < now, avg_response_time = AVG(first_replied_at - created_at) in minutes for tickets with first_replied_at.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

### Property 6: MRR normalization is correct

*For any* collection of active Service records with `service_monthly_charge > 0`, the MRR SHALL equal the sum of each service's charge divided by its billing period in months (Weekly=0.25, Monthly=1, Quarterly=3, Biannually=6, Annually=12, Biennially=24) for Stripe-managed services, and the raw charge for manual services. ARR SHALL equal MRR × 12.

**Validates: Requirements 4.1, 4.2**

### Property 7: Financial aggregations exclude Void and Uncollectible

*For any* set of Invoice records, the dashboard overdue count and amount SHALL only include invoices where `invoice_status = 'Unpaid'` AND `due_date < now` AND `invoice_status ∉ {Void, Uncollectible}`. Revenue this month SHALL only sum invoices where `invoice_status = 'Paid'` AND `paid_date` is in the current calendar month AND `invoice_status ∉ {Void, Uncollectible}`.

**Validates: Requirements 4.3, 4.4, 4.5**

### Property 8: Dashboard recent items are correctly bounded and ordered

*For any* database state, the dashboard SHALL return at most 5 recent tickets ordered by created_at descending, at most 5 recent admin logins ordered by last_login descending, and at most 5 expiring domains (expiry_date within next 30 days) ordered by expiry_date ascending.

**Validates: Requirements 5.1, 5.2, 5.3**

### Property 9: List filtering returns only matching records

*For any* filter parameter applied to a list endpoint (customer search query, service status, ticket status, ticket priority, invoice status), every record in the response SHALL match the filter criteria. When multiple filters are combined, every record SHALL satisfy all filter conditions.

**Validates: Requirements 6.2, 8.2, 10.2, 10.3, 10.4, 13.2**

### Property 10: Pagination respects bounds and includes metadata

*For any* list request with a `per_page` parameter, the actual page size SHALL be `min(per_page, 100)` with a default of 15 when not specified. The response SHALL include `current_page`, `last_page`, `total`, and `per_page` metadata fields where `last_page = ceil(total / per_page)`.

**Validates: Requirements 6.3, 6.4, 8.3, 8.4, 10.5, 13.3**

### Property 11: Invoice list excludes Void and Uncollectible by default

*For any* request to the invoices list endpoint without an explicit status filter, no invoice with `invoice_status ∈ {Void, Uncollectible}` SHALL appear in the results.

**Validates: Requirements 13.4**

### Property 12: CRUD create returns 201 with correct defaults

*For any* valid create request: a new Customer returns 201 with the created record, a new Service returns 201 with the created record, a new Ticket returns 201 with `status = 'Open'`, a new Invoice returns 201 with `invoice_status = 'Unpaid'` and `invoice_date = today`.

**Validates: Requirements 7.2, 9.2, 12.1, 14.1**

### Property 13: CRUD update reflects submitted changes

*For any* valid update payload submitted to a resource's update endpoint, the response SHALL contain the updated field values matching what was submitted, and a subsequent show request SHALL return those same updated values.

**Validates: Requirements 7.3, 9.3, 12.2**

### Property 14: Validation rejects invalid payloads with 422 and field errors

*For any* create or update request where required fields are missing or field values violate their constraints (e.g., negative invoice_amount, empty company_name), the API SHALL return 422 with an `errors` object containing keys for each invalid field.

**Validates: Requirements 7.5, 9.5, 12.5, 14.2, 14.3, 1.5**

### Property 15: First reply sets first_replied_at exactly once

*For any* Ticket with no existing replies (`first_replied_at IS NULL`), when the first reply is created, the Ticket's `first_replied_at` SHALL be set to the current timestamp. For tickets that already have replies, adding another reply SHALL NOT change `first_replied_at`.

**Validates: Requirements 12.4**

### Property 16: Ticket show returns replies and activities in chronological order

*For any* Ticket with associated TicketReply and TicketActivity records, the show endpoint SHALL return replies ordered by `created_at` ascending and activities ordered by `created_at` ascending.

**Validates: Requirements 11.2, 11.3**

### Property 17: Device token registration is idempotent

*For any* device token string submitted by the same user multiple times, the `device_tokens` table SHALL contain exactly one record for that (user_id, token) pair. The `updated_at` timestamp SHALL be refreshed on subsequent submissions.

**Validates: Requirements 16.2, 16.3**

### Property 18: Logout removes associated device token

*For any* authenticated session with a registered device token, after logout, the device token record associated with that user/token SHALL no longer exist in the database.

**Validates: Requirements 16.4**

### Property 19: Show endpoint returns related counts for Customer

*For any* Customer record, the show endpoint SHALL return `services_count`, `tickets_count`, `invoices_count`, and `domains_count` values that match the actual count of related records in the database.

**Validates: Requirements 7.1**

---

## Implementation Notes

### Laravel Sanctum Setup

1. Install Sanctum: `composer require laravel/sanctum` (if not already present via framework)
2. Publish config: `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
3. Add `HasApiTokens` trait to User model
4. Configure token expiration in `config/sanctum.php` (recommended: 30 days for mobile)
5. No need for `EnsureFrontendRequestsAreStateful` — token-only mode

### Route Model Binding with Custom Keys

Since models use custom primary keys (`company_id`, `service_id`, etc.), route model binding works automatically because `$primaryKey` is already set on each model. Route parameters should match the key name:

```php
Route::get('/customers/{customer}', [CustomerController::class, 'show']);
// Resolves via Customer::where('company_id', $customer)->firstOrFail()
```

For this to work with implicit binding, add `getRouteKeyName()` to models or rely on the custom `$primaryKey` already defined.

### iOS Minimum Deployment

- Xcode 15+ required for iOS 17 SDK
- SwiftUI `@Observable` macro (iOS 17+) replaces `ObservableObject`
- Swift Concurrency (`async/await`) for all network calls
- No third-party dependencies for networking (URLSession suffices)
