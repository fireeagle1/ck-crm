# Requirements Document

## Introduction

This document defines requirements for a set of enhancements to the CKAdmin iOS application and its supporting Laravel API. The changes span six areas: enriching asset detail views with rental and inspection data, adding rental metrics to the dashboard, introducing a dedicated Rentals tab, removing destructive delete actions from detail views, fixing the invoice loading bug, and overhauling the visual design system across the entire app.

## Glossary

- **CKAdmin_App**: The iOS SwiftUI admin application targeting iOS 17+, using the `@Observable` macro and async/await concurrency.
- **Admin_API**: The Laravel backend serving JSON endpoints under `/api/admin/*`, returning snake_case keys consumed by the iOS client via `keyDecodingStrategy = .convertFromSnakeCase`.
- **Asset**: A record in the `cmdb` table identified by `device_id`, representing a managed device or piece of equipment.
- **Booking**: A rental reservation record with a lifecycle tracked via `fulfilment_stage` (ordered → packing → ready → checked_out → returned → inspected).
- **BookingAsset**: A join record linking an Asset (`asset_id` → `device_id`) to a Booking (`booking_id`).
- **BookingInspection**: An inspection record associated with a Booking, typed as either "checkout" or "return", containing photos, condition notes, damage flag, inspector, and timestamp.
- **Fulfilment_Stage**: The lifecycle state of a Booking: one of `ordered`, `packing`, `ready`, `checked_out`, `returned`, `inspected`.
- **DashboardService**: The Laravel service class aggregating KPI metrics for the dashboard endpoint.
- **PaginatedResponse**: A generic iOS response wrapper containing a `data` array of type `T` and a `meta` object with `currentPage`, `lastPage`, `perPage`, and `total`.
- **Design_System**: A lightweight set of reusable SwiftUI components, colour tokens, and typography scales applied consistently across all app screens.
- **AssetDetailView**: The iOS view displaying a single asset's details, tickets, and actions.
- **CustomerDetailView**: The iOS view displaying a single customer's details.
- **ServiceDetailView**: The iOS view displaying a single service's details.
- **InvoiceListView**: The iOS view displaying the paginated list of invoices.

## Requirements

### Requirement 1: Asset Detail Rental Status

**User Story:** As an admin, I want to see whether an asset is currently rented out and to which booking, so that I can quickly assess its availability and rental context.

#### Acceptance Criteria

1. WHEN the Asset detail endpoint is requested, THE Admin_API SHALL include a `current_booking` object containing the Booking `id`, `status`, `fulfilment_stage`, `start_date`, `end_date`, and `customer_name` if the Asset has an active BookingAsset record with no `released_at` value.
2. WHEN the Asset detail endpoint is requested and the Asset has no active BookingAsset record, THE Admin_API SHALL return `current_booking` as `null`.
3. WHEN an Asset has a current booking, THE AssetDetailView SHALL display the rental status with the Booking fulfilment stage and date range.
4. WHEN an Asset has a current booking displayed, THE AssetDetailView SHALL provide a tappable link that navigates to the booking detail within the Rentals tab.

### Requirement 2: Asset Detail Inspection History

**User Story:** As an admin, I want to see recent inspections for an asset so that I can review its condition history without leaving the asset detail screen.

#### Acceptance Criteria

1. WHEN the Asset detail endpoint is requested, THE Admin_API SHALL include a `recent_inspections` array containing up to the 5 most recent BookingInspection records linked through BookingAsset → Booking → inspections, ordered by `inspected_at` descending.
2. THE Admin_API SHALL return each inspection object with: `id`, `type` (checkout or return), `condition_notes`, `damage_flagged`, `inspector_name`, and `inspected_at`.
3. WHEN inspections exist for an Asset, THE AssetDetailView SHALL display a "Recent Inspections" section showing each inspection's type, inspector name, date, and damage flag indicator.
4. WHEN no inspections exist for an Asset, THE AssetDetailView SHALL display a message indicating no inspections have been recorded.

### Requirement 3: Dashboard Rental Metrics

**User Story:** As an admin, I want to see rental activity metrics on the dashboard so that I can monitor rental operations at a glance.

#### Acceptance Criteria

1. WHEN the dashboard endpoint is requested, THE Admin_API SHALL return a `rentals` object containing `active_rentals_count`, `upcoming_returns_count`, and `recently_returned_count`.
2. THE Admin_API SHALL calculate `active_rentals_count` as the number of Bookings with status `active` or fulfilment_stage `checked_out`.
3. THE Admin_API SHALL calculate `upcoming_returns_count` as the number of active Bookings with `end_date` within the next 7 days.
4. THE Admin_API SHALL calculate `recently_returned_count` as the number of Bookings with `returned_at` within the last 7 days.
5. WHEN dashboard data loads, THE CKAdmin_App Dashboard SHALL display the rental metrics section showing active rentals count, upcoming returns count, and recently returned count.

### Requirement 4: Rentals Tab

**User Story:** As an admin, I want a dedicated Rentals tab to view and manage all bookings by their lifecycle stage, so that I can efficiently track rental fulfilment.

#### Acceptance Criteria

1. THE CKAdmin_App SHALL include a "Rentals" tab in the main TabView, positioned after the Shop tab.
2. WHEN the Rentals tab is selected, THE CKAdmin_App SHALL display a list of Bookings with filtering by fulfilment_stage.
3. THE CKAdmin_App Rentals list SHALL display for each Booking: the product name, customer name, date range, quantity, and current fulfilment_stage.
4. WHEN a Booking is tapped in the Rentals list, THE CKAdmin_App SHALL navigate to a Booking detail view showing full booking information, assigned assets, and inspection records.
5. WHEN the Rentals list is requested, THE Admin_API SHALL return a paginated list of Bookings with related product, customer, and assigned asset data, supporting optional `stage` and `status` query filters.
6. THE Admin_API Rentals list endpoint SHALL use the PaginatedResponse format with `data` array and `meta` object.

### Requirement 5: Remove Delete Actions

**User Story:** As an admin, I want destructive delete buttons removed from detail views so that accidental data deletion is prevented in the mobile app.

#### Acceptance Criteria

1. THE AssetDetailView SHALL NOT display a delete button or delete confirmation dialog.
2. THE CustomerDetailView SHALL NOT display a delete button or delete confirmation dialog.
3. THE ServiceDetailView SHALL NOT display a delete button or delete confirmation dialog.

### Requirement 6: Invoice Loading Fix

**User Story:** As an admin, I want the Invoices tab to load and display invoices correctly so that I can manage billing without errors.

#### Acceptance Criteria

1. WHEN the Invoices tab is selected, THE CKAdmin_App SHALL successfully fetch and display the paginated list of invoices from the Admin_API.
2. WHEN the Admin_API returns invoice data, THE CKAdmin_App SHALL decode the response without error, handling `invoice_id` as the identifier field and `invoice_amount` as either a numeric or string value.
3. IF the invoice list request fails, THEN THE CKAdmin_App SHALL display an error message with a retry option.
4. WHEN the invoice list loads successfully, THE CKAdmin_App SHALL display each invoice with its status, amount, invoice date, due date, and customer name.

### Requirement 7: Design System Foundation

**User Story:** As an admin, I want a polished, professional visual design so that the app feels cohesive and is easy to use.

#### Acceptance Criteria

1. THE CKAdmin_App SHALL define a colour palette with: a dark navy or charcoal primary colour, a teal or blue accent colour, and warm neutral background colours, supporting both light and dark mode via SwiftUI asset catalogue or programmatic colour definitions.
2. THE CKAdmin_App SHALL define a typography scale with consistent font sizes and weights for headings, body text, captions, and metric values, using the SF Pro system font family.
3. THE CKAdmin_App SHALL provide reusable card-based layout components for presenting grouped information with consistent padding, corner radius, and shadow styling.
4. THE CKAdmin_App SHALL provide reusable custom row components for list items that replace default Form and List row styling on primary screens.
5. THE CKAdmin_App SHALL display the app name as "CK Enterprises UK" in all user-facing branding contexts including the app display name, login screen, and dashboard header.

### Requirement 8: Design System Application

**User Story:** As an admin, I want the new design system applied consistently across all tabs so that the app provides a unified visual experience.

#### Acceptance Criteria

1. THE CKAdmin_App Dashboard tab SHALL use the Design_System colour palette, typography scale, and card-based layout components.
2. THE CKAdmin_App Tickets tab SHALL use the Design_System colour palette, typography scale, and custom row components.
3. THE CKAdmin_App Customers tab SHALL use the Design_System colour palette, typography scale, and custom row components.
4. THE CKAdmin_App CMDB tab SHALL use the Design_System colour palette, typography scale, and custom row components.
5. THE CKAdmin_App Invoices tab SHALL use the Design_System colour palette, typography scale, and custom row components.
6. THE CKAdmin_App Shop tab SHALL use the Design_System colour palette, typography scale, and custom row components.
7. THE CKAdmin_App Rentals tab SHALL use the Design_System colour palette, typography scale, and custom row components.
8. WHILE the Design_System is applied, THE CKAdmin_App SHALL maintain native SwiftUI navigation behaviour, gesture interactions, and accessibility support including VoiceOver compatibility.
