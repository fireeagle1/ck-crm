# Design Document: iOS App Enhancements

## Architecture Overview

This enhancement spans two layers — the Laravel Admin API and the CKAdmin iOS app — across six functional areas. The architecture preserves existing patterns: Laravel controllers return JSON with snake_case keys, the iOS `APIClient` decodes with `.convertFromSnakeCase`, and views use `@Observable` view models with async/await.

```
┌─────────────────────────────────────────────────────────────────┐
│  CKAdmin iOS App (SwiftUI, iOS 17+)                             │
├─────────────────────────────────────────────────────────────────┤
│  Tabs: Dashboard | Tickets | Customers | CMDB | Invoices | Shop | Rentals │
│                                                                 │
│  Design System Layer                                            │
│  ├── CKTheme (colours, typography)                              │
│  ├── CKCard (card container)                                    │
│  ├── CKRow (list row)                                           │
│  └── CKMetricCard (dashboard metric)                            │
│                                                                 │
│  Feature Views                                                  │
│  ├── AssetDetailView (+ rental status, inspections)             │
│  ├── DashboardView (+ rental metrics section)                   │
│  ├── RentalListView + RentalListViewModel                       │
│  ├── BookingDetailView                                          │
│  └── InvoiceListView (bug fix)                                  │
│                                                                 │
│  Models                                                         │
│  ├── AssetDetail (extended: currentBooking, recentInspections)  │
│  ├── BookingListItem, BookingDetail                             │
│  ├── DashboardResponse (extended: rentals)                      │
│  └── InvoiceListItem (existing, decoding fix)                   │
├─────────────────────────────────────────────────────────────────┤
│  APIClient (Endpoint, PaginatedResponse<T>)                     │
└──────────────────────────┬──────────────────────────────────────┘
                           │ HTTPS / Bearer Token
┌──────────────────────────▼──────────────────────────────────────┐
│  Laravel Admin API                                              │
├─────────────────────────────────────────────────────────────────┤
│  Routes: /api/admin/*                                           │
│  ├── GET /admin/assets/{asset}      (extended response)         │
│  ├── GET /admin/dashboard           (extended: rentals metrics) │
│  ├── GET /admin/shop/rentals        (existing, used by new tab) │
│  ├── GET /admin/shop/rentals/{id}   (existing, booking detail)  │
│  └── GET /admin/invoices            (existing, no change)       │
│                                                                 │
│  Models: Asset, Booking, BookingAsset, BookingInspection         │
│  Services: DashboardService (extended)                           │
└─────────────────────────────────────────────────────────────────┘
```

## Component Design

### 1. Asset Detail Rental Status (Requirement 1)

#### API Changes — AssetController::show()

Extend the existing `show` method to eager-load the active BookingAsset with its Booking and Customer:

```swift
// Pseudocode for the Laravel extension
$asset->load([
    'bookingAssets' => fn ($q) => $q->whereNull('released_at')->with('booking.customer'),
]);
```

The response adds a `current_booking` key:

```json
{
  "data": {
    "device_id": 42,
    "...existing fields...",
    "current_booking": {
      "id": 7,
      "status": "active",
      "fulfilment_stage": "checked_out",
      "start_date": "2025-01-10",
      "end_date": "2025-01-20",
      "customer_name": "Acme Corp"
    }
  }
}
```

When no active BookingAsset exists, `current_booking` is `null`.

#### iOS Model Extension

```swift
struct AssetCurrentBooking: Decodable {
    let id: Int
    let status: String
    let fulfilmentStage: String
    let startDate: String
    let endDate: String
    let customerName: String?
}

// Extend AssetDetail
struct AssetDetail: Decodable, Identifiable {
    // ...existing fields...
    let currentBooking: AssetCurrentBooking?
    let recentInspections: [AssetInspection]?
}
```

#### iOS View — AssetDetailView

Add a "Rental Status" section above "Linked Incidents" that shows the booking info with a `NavigationLink` to `BookingDetailView`. The link encodes a `ScanDeepLink` to navigate through the Rentals tab path.

---

### 2. Asset Detail Inspection History (Requirement 2)

#### API Changes — AssetController::show()

Query inspections through the BookingAsset → Booking → BookingInspection chain:

```php
$inspections = BookingInspection::whereHas('booking.assignedAssets', function ($q) use ($asset) {
    $q->where('asset_id', $asset->device_id);
})
->with('inspector')
->orderByDesc('inspected_at')
->limit(5)
->get();
```

Response shape:

```json
{
  "data": {
    "...existing fields...",
    "recent_inspections": [
      {
        "id": 12,
        "type": "checkout",
        "condition_notes": "Minor scratches on left panel",
        "damage_flagged": false,
        "inspector_name": "Jane Admin",
        "inspected_at": "2025-01-15T14:30:00Z"
      }
    ]
  }
}
```

#### iOS Model

```swift
struct AssetInspection: Decodable, Identifiable {
    let id: Int
    let type: String  // "checkout" or "return"
    let conditionNotes: String?
    let damageFlagged: Bool
    let inspectorName: String?
    let inspectedAt: String
}
```

#### iOS View — AssetDetailView

Add a "Recent Inspections" section after the rental status section. Each row shows: type badge (capsule), inspector name, date, and a red exclamation indicator if `damageFlagged` is true. When `recentInspections` is empty or nil, show a placeholder message.

---

### 3. Dashboard Rental Metrics (Requirement 3)

#### API Changes — DashboardService

Add a `getRentalMetrics()` method:

```php
private function getRentalMetrics(): array
{
    $activeRentalsCount = Booking::where(function ($q) {
        $q->where('status', 'active')
          ->orWhere('fulfilment_stage', 'checked_out');
    })->count();

    $upcomingReturnsCount = Booking::where('status', 'active')
        ->where('end_date', '<=', now()->addDays(7)->toDateString())
        ->where('end_date', '>=', now()->toDateString())
        ->count();

    $recentlyReturnedCount = Booking::whereNotNull('returned_at')
        ->where('returned_at', '>=', now()->subDays(7))
        ->count();

    return [
        'active_rentals_count' => $activeRentalsCount,
        'upcoming_returns_count' => $upcomingReturnsCount,
        'recently_returned_count' => $recentlyReturnedCount,
    ];
}
```

Include in `getMetrics()`:

```php
public function getMetrics(): array
{
    return [
        // ...existing keys...
        'rentals' => $this->getRentalMetrics(),
    ];
}
```

#### iOS Model Extension

```swift
struct RentalStats: Decodable {
    let activeRentalsCount: Int
    let upcomingReturnsCount: Int
    let recentlyReturnedCount: Int
}

struct DashboardResponse: Decodable {
    // ...existing fields...
    let rentals: RentalStats?
}
```

The `rentals` field is optional to maintain backward compatibility during rollout.

#### iOS View — DashboardView

Add a "Rentals" section after "Ticket Statistics" using the existing `metricRow` pattern:

```swift
if let rentals = dashboard.rentals {
    Section {
        metricRow(label: "Active Rentals", value: "\(rentals.activeRentalsCount)", icon: "box.truck", color: .teal)
        metricRow(label: "Upcoming Returns", value: "\(rentals.upcomingReturnsCount)", icon: "calendar.badge.clock", color: .orange)
        metricRow(label: "Recently Returned", value: "\(rentals.recentlyReturnedCount)", icon: "checkmark.circle", color: .green)
    } header: {
        Label("Rentals", systemImage: "shippingbox")
    }
}
```

---

### 4. Rentals Tab (Requirement 4)

#### iOS Models

```swift
// BookingResponse.swift
struct BookingListItem: Decodable, Identifiable {
    let id: Int
    let productName: String?
    let customerName: String?
    let startDate: String?
    let endDate: String?
    let quantity: Int
    let totalPrice: Double
    let status: String
    let fulfilmentStage: String
    let returnedAt: String?
}

struct BookingDetail: Decodable, Identifiable {
    let id: Int
    let productName: String?
    let customerName: String?
    let orderId: Int?
    let startDate: String?
    let endDate: String?
    let quantity: Int
    let totalPrice: Double
    let status: String
    let fulfilmentStage: String
    let returnedAt: String?
    let nextStage: String?
    let preConditions: [String]?
    let assignedAssets: [BookingAssetItem]
    let checkoutInspection: BookingInspectionDetail?
    let returnInspection: BookingInspectionDetail?
}

struct BookingAssetItem: Decodable, Identifiable {
    let id: Int
    let deviceName: String?
    let serialNumber: String?
    let status: String?
    let assignedAt: String?
    let releasedAt: String?
}

struct BookingInspectionDetail: Decodable {
    let photos: [String]?
    let conditionNotes: String?
    let damageFlagged: Bool?
    let inspectorName: String?
    let inspectedAt: String?
}

struct BookingDetailResponse: Decodable {
    let data: BookingDetail
}
```

#### iOS ViewModel — RentalListViewModel

```swift
@Observable
final class RentalListViewModel {
    private(set) var bookings: [BookingListItem] = []
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var errorMessage: String?
    var selectedStage: FulfilmentStageFilter = .all { didSet { ... } }
    
    private(set) var currentPage = 0
    private(set) var lastPage = 1
    var hasMorePages: Bool { currentPage < lastPage }
    
    private let apiClient: APIClient
    
    func loadInitial() async { ... }
    func loadNextPage() async { ... }
}
```

#### Fulfilment Stage Filter

```swift
enum FulfilmentStageFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case ordered = "ordered"
    case packing = "packing"
    case ready = "ready"
    case checkedOut = "checked_out"
    case returned = "returned"
    case inspected = "inspected"
    
    var id: String { rawValue }
    var displayName: String { /* Human-readable */ }
    var queryValue: String? {
        self == .all ? nil : rawValue
    }
}
```

#### iOS View — RentalListView

Follows the same pattern as `InvoiceListView`:
- Toolbar with stage filter picker
- Paginated list with infinite scroll via `onAppear` on last item
- Each row shows: product name, customer name, date range, quantity badge, fulfilment stage capsule
- Tapping a row navigates to `BookingDetailView`

#### ContentView Tab Addition

Add Rentals tab at tag 6 after Shop (tag 5):

```swift
NavigationStack {
    RentalListView(apiClient: apiClient)
}
.tabItem { Label("Rentals", systemImage: "shippingbox") }
.tag(6)
```

---

### 5. Remove Delete Actions (Requirement 5)

#### AssetDetailView

Remove:
- `@State private var showingDeleteConfirmation`
- `@State private var isDeleting`
- The `.confirmationDialog("Delete Asset", ...)` modifier
- The `Section` containing the delete button
- The `deleteAsset()` method

#### CustomerDetailView

Remove:
- `@State private var showingDeleteConfirmation`
- `@State private var isDeleting`
- The `.confirmationDialog("Delete Customer", ...)` modifier
- The `Section` containing the delete button
- The `deleteCustomer()` method

#### ServiceDetailView

Remove the delete button section and associated state/methods following the same pattern.

---

### 6. Invoice Loading Fix (Requirement 6)

#### Root Cause Analysis

The `InvoiceListItem` struct uses a custom `init(from:)` decoder that handles `invoice_amount` as either `Double` or `String`. The `APIClient` uses `.convertFromSnakeCase` key decoding strategy, which converts `invoice_id` → `invoiceId` correctly.

The likely bug: The `InvoiceListItem` custom decoder uses explicit `CodingKeys` which override the automatic snake_case conversion. When the custom decoder specifies `case invoiceId`, it expects the **already-converted** key `invoiceId` in the container — but since `CodingKeys` are matched **before** key decoding strategy is applied in custom decoders, the raw JSON key `invoice_id` is being looked up as `invoiceId`.

**Wait** — actually `keyDecodingStrategy` is applied globally to the decoder, so all containers (including those in custom `init(from:)`) see converted keys. The `CodingKeys` enum has `case invoiceId` which matches the converted key `invoiceId`. This should work correctly.

**Alternative hypothesis**: The `InvoiceListItem` response includes `invoice_date` and `due_date` which are decoded as `String` — if the API ever returns `null` for `invoice_date` (which the controller shows as `$invoice->invoice_date?->toDateString()` — nullable), then decoding a non-optional `String` from `null` would fail silently with `.invalidResponse`.

**Fix**: Make `invoiceDate` optional or ensure the API always returns a value. Looking at the controller, `invoice_date` uses `?->toDateString()` which could return `null`. The model declares `invoiceDate` as non-optional `String`. This mismatch causes `DecodingError.valueNotFound` which the APIClient catches as `.invalidResponse`.

#### Solution

Make `invoiceDate` and `dueDate` optional in `InvoiceListItem`, with fallback display values in the view:

```swift
struct InvoiceListItem: Identifiable {
    let invoiceId: Int
    let invoiceStatus: String
    let invoiceAmount: Double
    let invoiceDate: String?  // Changed: nullable
    let dueDate: String?      // Changed: nullable
    let paidDate: String?
    let customerName: String?
    
    var id: Int { invoiceId }
    
    var isOverdue: Bool {
        guard invoiceStatus == "Unpaid" else { return false }
        guard let due = dueDate, let dueDate = Self.dateFormatter.date(from: due) else { return false }
        return dueDate < Date()
    }
}
```

Update the custom decoder to use `decodeIfPresent` for `invoiceDate` and `dueDate`. Update the view to handle nil dates with a "—" fallback.

---

### 7. Design System Foundation (Requirements 7 & 8)

#### App Branding — "CK Enterprises UK"

The app is branded as **CK Enterprises UK** across all user-facing surfaces:

- **Bundle Display Name**: Set `CFBundleDisplayName` in `Info.plist` to "CK Enterprises UK" so the iOS home screen and app switcher show the correct name.
- **Dashboard Header**: The hero/header section in `DashboardView` displays "CK Enterprises UK" instead of "Admin Dashboard".
- **Login Screen**: Any branding text on the login screen uses "CK Enterprises UK" as the app title.
- Internal code references (module names, class prefixes) remain `CK`/`CKAdmin` for consistency with the existing codebase.

#### Colour Palette — CKTheme

```swift
// DesignSystem/CKTheme.swift
import SwiftUI

enum CKTheme {
    // MARK: - Primary Colours
    static let primary = Color("CKPrimary")           // Dark navy: #1B2838 (light) / #0D1B2A (dark)
    static let primaryVariant = Color("CKPrimaryVariant") // Charcoal: #2D3748
    
    // MARK: - Accent Colours
    static let accent = Color("CKAccent")             // Teal: #2DD4BF (light) / #14B8A6 (dark)
    static let accentSecondary = Color("CKAccentSecondary") // Blue: #3B82F6
    
    // MARK: - Backgrounds
    static let backgroundPrimary = Color("CKBackgroundPrimary")   // Warm off-white: #F8F7F4 / dark: #111827
    static let backgroundSecondary = Color("CKBackgroundSecondary") // Warm grey: #F1F0EB / dark: #1F2937
    static let backgroundCard = Color("CKBackgroundCard")          // White / dark: #1F2937
    
    // MARK: - Text
    static let textPrimary = Color("CKTextPrimary")     // Near-black: #1A1A2E / white
    static let textSecondary = Color("CKTextSecondary") // Mid-grey: #6B7280
    static let textTertiary = Color("CKTextTertiary")   // Light-grey: #9CA3AF
    
    // MARK: - Semantic
    static let success = Color("CKSuccess")   // #10B981
    static let warning = Color("CKWarning")   // #F59E0B
    static let error = Color("CKError")       // #EF4444
    static let info = Color("CKInfo")         // #3B82F6
}
```

#### Typography Scale — CKTypography

```swift
enum CKTypography {
    static let largeTitle = Font.system(size: 28, weight: .bold)
    static let title = Font.system(size: 22, weight: .bold)
    static let title2 = Font.system(size: 18, weight: .semibold)
    static let headline = Font.system(size: 16, weight: .semibold)
    static let body = Font.system(size: 15, weight: .regular)
    static let callout = Font.system(size: 14, weight: .medium)
    static let caption = Font.system(size: 12, weight: .regular)
    static let metric = Font.system(size: 24, weight: .bold).monospacedDigit()
    static let metricSmall = Font.system(size: 18, weight: .semibold).monospacedDigit()
}
```

#### Card Component — CKCard

```swift
struct CKCard<Content: View>: View {
    let content: Content
    
    init(@ViewBuilder content: () -> Content) {
        self.content = content()
    }
    
    var body: some View {
        content
            .padding(16)
            .background(CKTheme.backgroundCard)
            .clipShape(RoundedRectangle(cornerRadius: 12))
            .shadow(color: .black.opacity(0.06), radius: 8, y: 2)
    }
}
```

#### Row Component — CKRow

```swift
struct CKRow<Leading: View, Trailing: View>: View {
    let leading: Leading
    let trailing: Trailing
    
    init(
        @ViewBuilder leading: () -> Leading,
        @ViewBuilder trailing: () -> Trailing
    ) {
        self.leading = leading()
        self.trailing = trailing()
    }
    
    var body: some View {
        HStack(spacing: 12) {
            leading
            Spacer()
            trailing
        }
        .padding(.vertical, 12)
        .padding(.horizontal, 16)
    }
}
```

#### Metric Card — CKMetricCard

```swift
struct CKMetricCard: View {
    let title: String
    let value: String
    let icon: String
    let color: Color
    
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Image(systemName: icon)
                    .font(.body)
                    .foregroundStyle(color)
                Spacer()
            }
            Text(value)
                .font(CKTypography.metric)
                .foregroundStyle(CKTheme.textPrimary)
            Text(title)
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .padding(16)
        .background(CKTheme.backgroundCard)
        .clipShape(RoundedRectangle(cornerRadius: 12))
        .shadow(color: .black.opacity(0.06), radius: 8, y: 2)
    }
}
```

#### Application Strategy

The design system is applied progressively to each tab:
1. Replace `List` section backgrounds with `CKTheme.backgroundPrimary`
2. Replace metric rows with `CKMetricCard` on the Dashboard
3. Replace `ForEach` row content with `CKRow` styled rows on list screens
4. Use `CKCard` for grouped content sections
5. Apply `CKTypography` font tokens to all text elements
6. Maintain all existing `NavigationStack`, `.refreshable`, `.task`, and accessibility modifiers

---

## Data Models Summary

### Extended API Response — Asset Detail

```json
{
  "data": {
    "device_id": 42,
    "device_name": "MacBook Pro 16",
    "device_type": "Laptop",
    "location": "Office A",
    "asset_status": "Active",
    "serial_number": "C02XYZ",
    "notes": null,
    "customer_id": 5,
    "customer_name": "Acme Corp",
    "created_at": "2024-06-01T00:00:00Z",
    "updated_at": "2025-01-10T00:00:00Z",
    "tickets": [],
    "current_booking": {
      "id": 7,
      "status": "active",
      "fulfilment_stage": "checked_out",
      "start_date": "2025-01-10",
      "end_date": "2025-01-20",
      "customer_name": "Acme Corp"
    },
    "recent_inspections": [
      {
        "id": 12,
        "type": "checkout",
        "condition_notes": "Good condition",
        "damage_flagged": false,
        "inspector_name": "Jane Admin",
        "inspected_at": "2025-01-10T09:00:00Z"
      }
    ]
  }
}
```

### Extended API Response — Dashboard

```json
{
  "tickets": { "..." },
  "financials": { "..." },
  "recent_tickets": [],
  "recent_logins": [],
  "expiring_domains": [],
  "rentals": {
    "active_rentals_count": 8,
    "upcoming_returns_count": 3,
    "recently_returned_count": 5
  }
}
```

---

## Error Handling

| Scenario | Handling |
|----------|----------|
| Asset detail endpoint returns null `current_booking` | iOS model uses optional; view hides rental section |
| Asset has no inspections | iOS shows "No inspections recorded" placeholder |
| Dashboard `rentals` field missing (old API) | iOS model uses `RentalStats?` optional; section hidden if nil |
| Rentals list fails to load | Error view with retry button (same pattern as other lists) |
| Invoice `invoice_date` is null | Changed to optional String; view shows "—" fallback |
| Invoice `invoice_amount` is string or number | Custom decoder handles both (existing logic preserved) |
| Booking detail fails to load | Error view with retry (same pattern) |

---

## File Structure

```
ios/CKAdmin/CKAdmin/
├── DesignSystem/
│   ├── CKTheme.swift
│   ├── CKTypography.swift
│   ├── Components/
│   │   ├── CKCard.swift
│   │   ├── CKRow.swift
│   │   └── CKMetricCard.swift
├── Models/
│   ├── AssetResponse.swift          (extended)
│   ├── BookingResponse.swift         (new)
│   ├── DashboardResponse.swift       (extended)
│   └── InvoiceResponse.swift         (fix)
├── Views/
│   ├── Assets/
│   │   └── AssetDetailView.swift     (extended, delete removed)
│   ├── Customers/
│   │   └── CustomerDetailView.swift  (delete removed)
│   ├── Services/
│   │   └── ServiceDetailView.swift   (delete removed)
│   ├── Dashboard/
│   │   └── DashboardView.swift       (extended)
│   ├── Invoices/
│   │   ├── InvoiceListView.swift     (date fallback fix)
│   │   └── InvoiceListViewModel.swift
│   ├── Rentals/
│   │   ├── RentalListView.swift      (new)
│   │   ├── RentalListViewModel.swift (new)
│   │   └── BookingDetailView.swift   (new)
│   └── ContentView.swift             (new tab)

app/Http/Controllers/Api/Admin/
├── AssetController.php               (extended show method)
├── DashboardController.php           (passes through to service)

app/Services/
├── DashboardService.php              (extended with rental metrics)
```

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Asset current_booking presence correctness

*For any* Asset record, the API response field `current_booking` is non-null if and only if there exists a BookingAsset record where `asset_id` equals the asset's `device_id` and `released_at` is null, and when non-null the object contains the correct `id`, `status`, `fulfilment_stage`, `start_date`, `end_date`, and `customer_name` from the related Booking.

**Validates: Requirements 1.1, 1.2**

### Property 2: Asset rental status rendering

*For any* `AssetDetail` model with a non-null `currentBooking`, the rendered AssetDetailView must display text containing the booking's fulfilment stage and date range (start and end dates).

**Validates: Requirements 1.3**

### Property 3: Asset inspection response correctness

*For any* Asset, the `recent_inspections` array in the API response contains at most 5 items, each item includes all required fields (`id`, `type`, `condition_notes`, `damage_flagged`, `inspector_name`, `inspected_at`), and items are ordered by `inspected_at` descending.

**Validates: Requirements 2.1, 2.2**

### Property 4: Asset inspection UI rendering

*For any* `AssetDetail` model with a non-empty `recentInspections` array, the rendered AssetDetailView displays each inspection's type, inspector name, date, and a damage flag indicator.

**Validates: Requirements 2.3**

### Property 5: Dashboard rental metrics computation

*For any* set of Booking records in the database: (a) `active_rentals_count` equals the count of bookings where `status = 'active'` OR `fulfilment_stage = 'checked_out'`; (b) `upcoming_returns_count` equals the count of active bookings with `end_date` between today and today + 7 days inclusive; (c) `recently_returned_count` equals the count of bookings with `returned_at` within the last 7 days.

**Validates: Requirements 3.2, 3.3, 3.4**

### Property 6: Rentals list stage filtering

*For any* fulfilment stage filter value and set of bookings, when the Rentals list is filtered by a specific stage, all items in the returned list have a `fulfilment_stage` matching the selected filter. When filter is "All", all bookings are returned regardless of stage.

**Validates: Requirements 4.2, 4.5**

### Property 7: Booking list item rendering completeness

*For any* `BookingListItem`, the rendered row contains text representing the product name, customer name, start date, end date, quantity, and fulfilment stage.

**Validates: Requirements 4.3**

### Property 8: Invoice amount flexible decoding

*For any* valid invoice JSON payload where `invoice_amount` is represented as either a JSON number (e.g., `150.00`) or a JSON string (e.g., `"150.00"`), decoding into `InvoiceListItem` succeeds and produces the correct numeric `Double` value.

**Validates: Requirements 6.2**

### Property 9: Invoice row rendering completeness

*For any* `InvoiceListItem`, the rendered row contains the invoice status, formatted amount, invoice date (or fallback), due date (or fallback), and customer name.

**Validates: Requirements 6.4**

### Property 10: Dashboard rental metrics UI rendering

*For any* `DashboardResponse` with a non-nil `rentals` object, the rendered DashboardView displays all three metric values: active rentals count, upcoming returns count, and recently returned count.

**Validates: Requirements 3.5**
