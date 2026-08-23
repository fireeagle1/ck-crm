# Implementation Plan: iOS App Enhancements

## Overview

This plan implements six functional areas across the Laravel Admin API and CKAdmin iOS app: asset detail enrichment (rental status + inspections), dashboard rental metrics, a dedicated Rentals tab, delete action removal, invoice loading fix, and a full design system overhaul. Tasks are ordered so backend changes land first, followed by iOS models, views, and finally the design system application across all tabs.

## Tasks

- [x] 1. Design System Foundation
  - [x] 1.1 Create `CKTheme.swift` with colour palette tokens
    - Create `ios/CKAdmin/CKAdmin/DesignSystem/CKTheme.swift`
    - Define primary, accent, background, text, and semantic colour constants supporting light and dark mode
    - Use SwiftUI `Color` with named asset catalogue colours or programmatic definitions
    - _Requirements: 7.1_

  - [x] 1.2 Create `CKTypography.swift` with typography scale
    - Create `ios/CKAdmin/CKAdmin/DesignSystem/CKTypography.swift`
    - Define font tokens: largeTitle, title, title2, headline, body, callout, caption, metric, metricSmall
    - Use SF Pro system font family with explicit sizes and weights
    - _Requirements: 7.2_

  - [x] 1.3 Create `CKCard.swift` reusable card component
    - Create `ios/CKAdmin/CKAdmin/DesignSystem/Components/CKCard.swift`
    - Generic ViewBuilder-based card with consistent padding (16pt), corner radius (12pt), shadow, and background colour from CKTheme
    - _Requirements: 7.3_

  - [x] 1.4 Create `CKRow.swift` reusable row component
    - Create `ios/CKAdmin/CKAdmin/DesignSystem/Components/CKRow.swift`
    - Generic row with leading/trailing ViewBuilder slots, consistent spacing and padding
    - _Requirements: 7.4_

  - [x] 1.5 Create `CKMetricCard.swift` metric card component
    - Create `ios/CKAdmin/CKAdmin/DesignSystem/Components/CKMetricCard.swift`
    - Displays icon, metric value, and title label with CKTheme colours and CKTypography fonts
    - _Requirements: 7.3_

  - [x] 1.6 Update app branding to "CK Enterprises UK"
    - Update the Xcode project display name / bundle display name to "CK Enterprises UK"
    - Update the DashboardView hero header text from "Admin Dashboard" to reference "CK Enterprises UK"
    - Update any login screen branding text to use "CK Enterprises UK"
    - Ensure the app name appears correctly in the iOS home screen and app switcher
    - _Requirements: 7.1, 8.1_

- [x] 2. Invoice Loading Fix
  - [x] 2.1 Fix `InvoiceListItem` model decoding for nullable dates
    - Modify `ios/CKAdmin/CKAdmin/Models/InvoiceResponse.swift`
    - Change `invoiceDate` and `dueDate` from `String` to `String?`
    - Update the custom `init(from:)` decoder to use `decodeIfPresent` for these fields
    - Preserve existing flexible decoding of `invoice_amount` (String or Double)
    - _Requirements: 6.1, 6.2_

  - [x] 2.2 Update `InvoiceListView` to handle nil dates
    - Modify `ios/CKAdmin/CKAdmin/Views/Invoices/InvoiceListView.swift`
    - Display "—" fallback when `invoiceDate` or `dueDate` is nil
    - Update `isOverdue` computed property to guard against nil `dueDate`
    - _Requirements: 6.3, 6.4_

  - [x]* 2.3 Write unit tests for invoice decoding
    - Test decoding with `invoice_amount` as Double and as String
    - Test decoding with null `invoice_date` and null `due_date`
    - Test `isOverdue` logic with nil and valid dates
    - _Requirements: 6.2_

- [x] 3. Backend API: Asset Detail Extension
  - [x] 3.1 Extend `AssetController::show()` to include `current_booking`
    - Modify `app/Http/Controllers/Api/Admin/AssetController.php`
    - Eager-load active BookingAsset (where `released_at` is null) with related Booking and Customer
    - Add `current_booking` key to the response (null when no active rental)
    - Include: `id`, `status`, `fulfilment_stage`, `start_date`, `end_date`, `customer_name`
    - _Requirements: 1.1, 1.2_

  - [x] 3.2 Extend `AssetController::show()` to include `recent_inspections`
    - Query BookingInspection records through BookingAsset → Booking → inspections chain
    - Limit to 5 most recent, ordered by `inspected_at` descending
    - Include each inspection's `id`, `type`, `condition_notes`, `damage_flagged`, `inspector_name`, `inspected_at`
    - _Requirements: 2.1, 2.2_

  - [x]* 3.3 Write unit tests for asset detail endpoint extensions
    - Test response includes `current_booking` when active BookingAsset exists
    - Test response returns `current_booking` as null when no active rental
    - Test `recent_inspections` returns max 5 items ordered correctly
    - Test inspections include all required fields
    - _Requirements: 1.1, 1.2, 2.1, 2.2_

- [x] 4. Backend API: Dashboard Rental Metrics
  - [x] 4.1 Add `getRentalMetrics()` method to `DashboardService`
    - Modify `app/Services/DashboardService.php`
    - Calculate `active_rentals_count`: bookings with status `active` OR fulfilment_stage `checked_out`
    - Calculate `upcoming_returns_count`: active bookings with `end_date` between today and today + 7 days
    - Calculate `recently_returned_count`: bookings with `returned_at` within last 7 days
    - Include `rentals` key in the `getMetrics()` return value
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [x]* 4.2 Write unit tests for dashboard rental metrics
    - **Property 5: Dashboard rental metrics computation**
    - **Validates: Requirements 3.2, 3.3, 3.4**

- [x] 5. Checkpoint - Backend changes complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. iOS Models: Asset Detail & Booking
  - [x] 6.1 Extend `AssetDetail` model with rental and inspection fields
    - Modify `ios/CKAdmin/CKAdmin/Models/AssetResponse.swift`
    - Add `AssetCurrentBooking` struct (id, status, fulfilmentStage, startDate, endDate, customerName)
    - Add `AssetInspection` struct (id, type, conditionNotes, damageFlagged, inspectorName, inspectedAt)
    - Add `currentBooking: AssetCurrentBooking?` and `recentInspections: [AssetInspection]?` to `AssetDetail`
    - _Requirements: 1.1, 1.2, 2.1, 2.2_

  - [x] 6.2 Create `BookingResponse.swift` with booking models
    - Create `ios/CKAdmin/CKAdmin/Models/BookingResponse.swift`
    - Define `BookingListItem`: id, productName, customerName, startDate, endDate, quantity, totalPrice, status, fulfilmentStage, returnedAt
    - Define `BookingDetail`: full detail model including assignedAssets, checkoutInspection, returnInspection
    - Define `BookingAssetItem` and `BookingInspectionDetail` supporting structs
    - Define `BookingDetailResponse` wrapper
    - Define `FulfilmentStageFilter` enum with all stages + "All" option
    - _Requirements: 4.2, 4.3, 4.5_

  - [x] 6.3 Extend `DashboardResponse` model with rental metrics
    - Modify `ios/CKAdmin/CKAdmin/Models/DashboardResponse.swift`
    - Add `RentalStats` struct (activeRentalsCount, upcomingReturnsCount, recentlyReturnedCount)
    - Add `rentals: RentalStats?` optional field to `DashboardResponse`
    - _Requirements: 3.1, 3.5_

- [x] 7. iOS Views: Asset Detail Enhancements
  - [x] 7.1 Add rental status section to `AssetDetailView`
    - Modify `ios/CKAdmin/CKAdmin/Views/Assets/AssetDetailView.swift`
    - Add "Rental Status" section above "Linked Incidents" when `currentBooking` is non-nil
    - Display fulfilment stage, date range, and customer name
    - Add NavigationLink to BookingDetailView via the Rentals tab path
    - _Requirements: 1.3, 1.4_

  - [x] 7.2 Add inspection history section to `AssetDetailView`
    - Add "Recent Inspections" section after the rental status section
    - Each row: type badge (capsule), inspector name, date, red exclamation if damageFlagged
    - Show "No inspections recorded" placeholder when list is empty or nil
    - _Requirements: 2.3, 2.4_

  - [x]* 7.3 Write property test for asset rental status rendering
    - **Property 2: Asset rental status rendering**
    - **Validates: Requirements 1.3**

  - [x]* 7.4 Write property test for asset inspection UI rendering
    - **Property 4: Asset inspection UI rendering**
    - **Validates: Requirements 2.3**

- [x] 8. iOS Views: Dashboard Rental Metrics
  - [x] 8.1 Add rental metrics section to `DashboardView`
    - Modify `ios/CKAdmin/CKAdmin/Views/Dashboard/DashboardView.swift`
    - Add "Rentals" section after "Ticket Statistics" using the existing `metricRow` pattern
    - Display active rentals, upcoming returns, and recently returned counts with appropriate icons and colours
    - Only show section when `dashboard.rentals` is non-nil
    - _Requirements: 3.5_

  - [x]* 8.2 Write property test for dashboard rental metrics rendering
    - **Property 10: Dashboard rental metrics UI rendering**
    - **Validates: Requirements 3.5**

- [x] 9. iOS Views: Rentals Tab
  - [x] 9.1 Create `RentalListViewModel`
    - Create `ios/CKAdmin/CKAdmin/Views/Rentals/RentalListViewModel.swift`
    - `@Observable` class with paginated loading (loadInitial, loadNextPage)
    - Stage filter property that triggers reload on change
    - Use existing APIClient with `/admin/shop/rentals` endpoint
    - Support `stage` query parameter for filtering
    - _Requirements: 4.2, 4.5, 4.6_

  - [x] 9.2 Create `RentalListView`
    - Create `ios/CKAdmin/CKAdmin/Views/Rentals/RentalListView.swift`
    - Toolbar with fulfilment stage filter picker
    - Paginated list with infinite scroll (onAppear on last item)
    - Each row: product name, customer name, date range, quantity badge, fulfilment stage capsule
    - Navigation to BookingDetailView on tap
    - Pull-to-refresh support
    - _Requirements: 4.1, 4.2, 4.3_

  - [x] 9.3 Create `BookingDetailView`
    - Create `ios/CKAdmin/CKAdmin/Views/Rentals/BookingDetailView.swift`
    - Fetch booking detail from `/admin/shop/rentals/{id}`
    - Display full booking info: product, customer, dates, quantity, price, status, fulfilment stage
    - Show assigned assets section with device name, serial number, status
    - Show checkout and return inspection details when available
    - _Requirements: 4.4_

  - [x] 9.4 Add Rentals tab to `ContentView`
    - Modify `ios/CKAdmin/CKAdmin/Views/ContentView.swift`
    - Add NavigationStack with RentalListView at tag 6, after Shop (tag 5)
    - Use "Rentals" label with "shippingbox" system image
    - _Requirements: 4.1_

  - [x]* 9.5 Write property test for rental list stage filtering
    - **Property 6: Rentals list stage filtering**
    - **Validates: Requirements 4.2, 4.5**

  - [x]* 9.6 Write property test for booking list item rendering
    - **Property 7: Booking list item rendering completeness**
    - **Validates: Requirements 4.3**

- [x] 10. Remove Delete Actions
  - [x] 10.1 Remove delete functionality from `AssetDetailView`
    - Modify `ios/CKAdmin/CKAdmin/Views/Assets/AssetDetailView.swift`
    - Remove `showingDeleteConfirmation` and `isDeleting` state properties
    - Remove the confirmation dialog modifier
    - Remove the delete button section
    - Remove the `deleteAsset()` method
    - _Requirements: 5.1_

  - [x] 10.2 Remove delete functionality from `CustomerDetailView`
    - Modify `ios/CKAdmin/CKAdmin/Views/Customers/CustomerDetailView.swift`
    - Remove delete state, confirmation dialog, delete button section, and `deleteCustomer()` method
    - _Requirements: 5.2_

  - [x] 10.3 Remove delete functionality from `ServiceDetailView`
    - Modify `ios/CKAdmin/CKAdmin/Views/Services/ServiceDetailView.swift`
    - Remove delete state, confirmation dialog, delete button section, and delete method
    - _Requirements: 5.3_

- [x] 11. Checkpoint - Core features complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 12. Design System Application
  - [x] 12.1 Apply design system to Dashboard tab
    - Update `DashboardView` to use `CKTheme` colours, `CKTypography` fonts, and `CKMetricCard` components
    - Replace existing metric rows with `CKMetricCard` where appropriate
    - Apply `CKTheme.backgroundPrimary` to section backgrounds
    - _Requirements: 8.1_

  - [x] 12.2 Apply design system to Tickets tab
    - Update ticket list and detail views with `CKTheme` colours, `CKTypography` fonts, and `CKRow` components
    - _Requirements: 8.2_

  - [x] 12.3 Apply design system to Customers tab
    - Update customer list and detail views with design system tokens and components
    - _Requirements: 8.3_

  - [x] 12.4 Apply design system to CMDB (Assets) tab
    - Update asset list and detail views with design system tokens and components
    - _Requirements: 8.4_

  - [x] 12.5 Apply design system to Invoices tab
    - Update invoice list view with design system tokens and components
    - _Requirements: 8.5_

  - [x] 12.6 Apply design system to Shop tab
    - Update shop views with design system tokens and components
    - _Requirements: 8.6_

  - [x] 12.7 Apply design system to Rentals tab
    - Update `RentalListView` and `BookingDetailView` with design system tokens and components
    - _Requirements: 8.7_

  - [x] 12.8 Verify accessibility and navigation preservation
    - Ensure native SwiftUI navigation, gestures, and accessibility modifiers are maintained
    - Verify VoiceOver compatibility is not broken by design system changes
    - _Requirements: 8.8_

- [x] 13. Final Checkpoint
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- The rental API endpoints (`/admin/shop/rentals`) already exist — the iOS Rentals tab consumes them directly
- The design system is applied last to avoid rework as views are built in earlier tasks
- Backend tasks (3, 4) are independent and can be developed in parallel
- iOS model tasks (6) must precede their corresponding view tasks (7, 8, 9)

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.6", "2.1", "3.1", "3.2", "4.1"] },
    { "id": 1, "tasks": ["1.3", "1.4", "1.5", "2.2", "2.3", "3.3", "4.2"] },
    { "id": 2, "tasks": ["6.1", "6.2", "6.3", "10.1", "10.2", "10.3"] },
    { "id": 3, "tasks": ["7.1", "7.2", "8.1", "9.1"] },
    { "id": 4, "tasks": ["7.3", "7.4", "8.2", "9.2", "9.3"] },
    { "id": 5, "tasks": ["9.4", "9.5", "9.6"] },
    { "id": 6, "tasks": ["12.1", "12.2", "12.3", "12.4", "12.5", "12.6", "12.7"] },
    { "id": 7, "tasks": ["12.8"] }
  ]
}
```
