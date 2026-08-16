# Implementation Plan: Rental Fulfilment v2

## Overview

This plan implements three feature groups: (1) quantity selection for one-off purchases, (2) a full checkout/packing/collection/inspection lifecycle for rental bookings with asset tracking and photo evidence, and (3) a dedicated customer shop & rental history page in the admin panel. Tasks are ordered by dependency: schema first, then models, services, controllers, and views.

## Tasks

- [x] 1. Database migrations
  - [x] 1.1 Create migration to add `product_id` to `cmdb` table
    - Add nullable `product_id` (bigint unsigned) FK referencing `products.id` ON DELETE SET NULL
    - Add index on `product_id`
    - _Requirements: 2.1, 2.4_

  - [x] 1.2 Create migration to add `fulfilment_stage` to `bookings` table
    - Add `fulfilment_stage` varchar(20) NOT NULL DEFAULT 'ordered'
    - Add index on `fulfilment_stage`
    - Update existing bookings: set stage based on current status (confirmed→ordered, active→checked_out, returned→inspected)
    - _Requirements: 3.1_

  - [x] 1.3 Create `booking_assets` pivot table migration
    - Columns: id, booking_id (FK→bookings.id CASCADE), asset_id (FK→cmdb.device_id CASCADE), assigned_at (timestamp), released_at (timestamp nullable), timestamps
    - Add indexes on booking_id and asset_id
    - _Requirements: 3.4, 2.6_

  - [x] 1.4 Create `booking_inspections` table migration
    - Columns: id, booking_id (FK→bookings.id CASCADE), type (enum: checkout/return), photos (JSON default []), condition_notes (text nullable), damage_flagged (boolean default false), inspected_by (FK→users.id RESTRICT), inspected_at (timestamp), timestamps
    - Add indexes on (booking_id) and (booking_id, type)
    - _Requirements: 4.1_

  - [x] 1.5 Create migration to add `track_individual_assets` to `products` table
    - Add `track_individual_assets` boolean NOT NULL DEFAULT FALSE
    - _Requirements: 2.4_

- [x] 2. Models
  - [x] 2.1 Create `BookingAsset` model
    - Table: `booking_assets`
    - Fillable: booking_id, asset_id, assigned_at, released_at
    - Casts: assigned_at→datetime, released_at→datetime
    - Relationships: belongsTo Booking, belongsTo Asset (asset_id→device_id)
    - _Requirements: 3.4_

  - [x] 2.2 Create `BookingInspection` model
    - Table: `booking_inspections`
    - Fillable: booking_id, type, photos, condition_notes, damage_flagged, inspected_by, inspected_at
    - Casts: photos→array, damage_flagged→boolean, inspected_at→datetime
    - Relationships: belongsTo Booking, belongsTo User (inspected_by)
    - _Requirements: 4.1_

  - [x] 2.3 Update `Asset` model
    - Add `product_id` to fillable
    - Add relationship: belongsTo Product
    - Add relationship: hasMany BookingAsset (asset_id→device_id)
    - Add helper: `isAvailableForRental(): bool` (status === 'Available')
    - Update validation in AssetController to accept new status values: Available, Rented Out, Reserved, In Repair, Decommissioned
    - _Requirements: 2.1, 2.3, 2.6_

  - [x] 2.4 Update `Booking` model
    - Add `fulfilment_stage` to fillable
    - Add relationships: hasMany BookingAsset (as `assignedAssets`), hasMany BookingInspection (as `inspections`), hasOne BookingInspection where type=checkout (as `checkoutInspection`), hasOne BookingInspection where type=return (as `returnInspection`)
    - Add scopes: `scopeAtStage($stage)`, `scopeNeedsAction()` (stages before inspected)
    - _Requirements: 3.1, 3.4, 4.5_

  - [x] 2.5 Update `Product` model
    - Add `track_individual_assets` to fillable and casts (boolean)
    - Add relationship: hasMany Asset
    - Add helper: `getAvailableAssetCount(): int` — if track_individual_assets, count linked Available assets; otherwise return stock_quantity
    - Add helper: `getAvailableAssets()` — returns query of linked assets with status Available
    - _Requirements: 2.2, 2.4_

- [x] 3. Checkpoint — Run migrations, verify models load correctly
  - Run `php artisan migrate` and confirm no errors
  - Verify new tables exist with correct columns and indexes

- [x] 4. Services
  - [x] 4.1 Create `FulfilmentStageService`
    - Define STAGES constant: ['ordered', 'packing', 'ready', 'checked_out', 'returned', 'inspected']
    - Implement `advance(Booking, targetStage)`: validate sequential transition, check pre-conditions, update booking fulfilment_stage, trigger side effects (asset status changes)
    - Implement `getNextStage(Booking): ?string`
    - Implement `checkPreConditions(Booking, targetStage): array` — returns list of unmet conditions
    - Pre-conditions: packing→paid; ready→assets assigned; checked_out→checkout inspection exists; returned→none; inspected→return inspection exists
    - On advance to checked_out: update assigned assets to 'Rented Out'
    - On advance to inspected: call AssetAssignmentService::releaseAssets()
    - _Requirements: 3.1, 3.2, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12_

  - [x] 4.2 Create `AssetAssignmentService`
    - Implement `assignAssets(Booking, assetIds[])`: validate assets belong to booking's product, validate all are 'Available', create BookingAsset records, update asset status to 'Reserved'
    - Implement `releaseAssets(Booking, damagedAssetIds[])`: update BookingAsset released_at, set asset status to 'Available' (or 'In Repair' for damaged), update booking fulfilment if needed
    - Use DB::transaction for atomicity
    - Throw exception if any asset is not available or doesn't belong to the product
    - _Requirements: 3.4, 3.5, 3.8, 3.11_

  - [x] 4.3 Create `BookingInspectionService`
    - Implement `createCheckoutInspection(Booking, UploadedFile[] photos, ?notes, adminId): BookingInspection`
    - Implement `createReturnInspection(Booking, UploadedFile[] photos, ?notes, bool damageFlagged, adminId): BookingInspection`
    - Photo storage: validate file type (jpeg/png) and size (max 10MB), store to `inspections/{booking_id}/checkout_{n}.jpg` or `return_{n}.jpg`, resize to max 1920px width using Intervention Image (or GD directly)
    - Store relative paths in photos JSON array
    - Validate max 10 photos per inspection
    - _Requirements: 4.1, 4.2, 4.3, 4.6_

  - [x] 4.4 Enhance `CartService` for one-off quantity
    - Update `addItem()`: accept `quantity` option for one-off products, validate quantity >= 1 and quantity <= stock_quantity
    - Add `updateItemQuantity(int $index, int $quantity)`: validate new quantity against stock, update cart session, recalculate line total
    - Ensure duplicate product additions with same options increment quantity rather than adding a new line
    - _Requirements: 1.2, 1.3, 1.4, 1.5_

- [x] 5. Checkpoint — Verify services instantiate and basic logic works
  - Write a quick smoke test or use tinker to confirm FulfilmentStageService stage transitions work
  - Confirm CartService accepts quantity for one-off products

- [x] 6. Admin Controllers
  - [x] 6.1 Create `Admin\FulfilmentQueueController`
    - `index(Request)`: query bookings grouped by fulfilment_stage, support tab/filter by stage, paginate each group, load product + customer relationships
    - `show(Booking)`: load booking with assignedAssets.asset, inspections, product, customer, orderItem.order; pass available assets for assignment
    - `assignAssets(Request, Booking)`: validate asset IDs, call AssetAssignmentService::assignAssets(), advance stage to 'packing' if at 'ordered', redirect with success
    - `advance(Request, Booking)`: call FulfilmentStageService::advance() to next stage, handle pre-condition failures with error messages, redirect back
    - `inspect(Request, Booking)`: validate photos (required, max 10, image types), notes, damage flag; determine type from current stage (checked_out→return, packing/ready→checkout); call BookingInspectionService; advance stage; redirect
    - _Requirements: 3.2, 3.3, 3.4, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12_

  - [x] 6.2 Create `Admin\CustomerShopController`
    - `index(Request, Customer)`: compute KPIs (total rental spend, total purchase spend, order count, rental count, avg order value), query orders with items, query bookings with inspections and assigned assets, collect document links (invoice PDFs + agreement snapshots), pass customer tier info
    - Support date range and product type filters via query params
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 6.1, 6.2, 6.3, 6.4_

  - [x] 6.3 Enhance `Admin\AssetController` for product linking
    - Update `create()` and `edit()`: add product_id dropdown (nullable, filtered to equipment_rental products)
    - Update `store()` and `update()` validation: add `product_id` as nullable|exists:products,id
    - Update asset_status validation to accept new values: Available, Rented Out, Reserved, In Repair, Decommissioned
    - _Requirements: 2.1, 2.3_

  - [x] 6.4 Enhance `Admin\ShopProductController` for asset pool display
    - On product edit page: display linked assets table (serial number, device name, status) when product is equipment_rental
    - Add "Link Existing Asset" and "Create New Asset" action buttons
    - Display available asset count vs total linked assets
    - _Requirements: 2.2, 2.5_

- [x] 7. Portal Controllers (Quantity Selection)
  - [x] 7.1 Enhance `Portal\CartController`
    - Add `updateQuantity(Request, int $index)` method: validate quantity (integer, min 1), call CartService::updateItemQuantity(), redirect back to cart with success/error
    - _Requirements: 1.4, 1.5_

  - [x] 7.2 Enhance `Portal\CartController@add` for one-off quantity
    - Extract `quantity` from request for one-off products (default 1)
    - Pass through to CartService via options array
    - _Requirements: 1.2_

  - [x] 7.3 Enhance `Portal\ShopController@show` for one-off quantity data
    - Pass `maxQuantity` (= stock_quantity or a cap of 99) to view for one-off products
    - _Requirements: 1.1_

- [x] 8. Admin Views
  - [x] 8.1 Create `admin.fulfilment.index` view
    - Tabbed interface with counts per stage: Ordered (badge) | Packing | Ready | Checked Out | Returned | Inspected
    - Each tab shows table: Customer, Product, Qty, Dates, Days in Stage, Action Button
    - Action button text varies by stage: "Start Packing", "Mark Ready", "Check Out", "Mark Returned", "Inspect"
    - Colour-coded stage badges
    - Search/filter by customer name or product name
    - _Requirements: 3.2_

  - [x] 8.2 Create `admin.fulfilment.show` view
    - Header: Product name, customer name, booking dates, quantity, total price
    - Timeline sidebar: shows each stage transition with timestamp (green for completed, grey for pending)
    - Asset Assignment panel: shown during packing stage; checkboxes for available assets (serial number + device name); assign button
    - Packing List panel: shows assigned assets in a table (device name, serial, assigned date)
    - Checkout Inspection panel: photo upload form (drag-and-drop area), condition notes textarea; shown when advancing to checked_out
    - Return Inspection panel: same as checkout but with damage checkbox; shown when advancing to inspected
    - Inspection Gallery: side-by-side display of checkout and return photos with notes; shown on completed bookings
    - _Requirements: 3.3, 3.4, 3.6, 3.7, 3.8, 3.9, 3.10, 4.4, 4.5_

  - [x] 8.3 Create `admin.customers.shop` view
    - KPI cards row: Total Rental Spend | Total Purchase Spend | Orders | Rentals | Avg Order Value
    - Loyalty summary card: Customer since (created_at), lifetime spend, tier badge (if CustomerTier assigned)
    - Filter bar: date range picker, product type dropdown
    - Orders table: ID, Date, Items (product names), Type badges, Total, Payment Status, Fulfilment Status, View link
    - Active Bookings table: Product, Dates, Stage badge, Assets assigned (count), Days remaining, View link
    - Past Bookings table: Product, Dates, Return date, Inspection summary (condition notes truncated), Total, View link
    - Documents section: grouped by order — Order #, Date, Document type (Invoice PDF / Rental Agreement), Download link
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 6.3, 6.4_

  - [x] 8.4 Update `admin.customers.show` to link to shop page
    - Add "Shop & Rentals" link/button in the customer header actions area (next to Edit/Scorecard)
    - Add a KPI card for "Shop Spend" showing total order amount in the existing KPI strip
    - _Requirements: 5.1_

  - [x] 8.5 Update `admin.assets.edit` and `admin.assets.create` views
    - Add product_id select dropdown: "Link to Product (optional)" with equipment_rental products listed
    - Add new status options in the status dropdown: Available, Rented Out, Reserved
    - _Requirements: 2.1, 2.3_

  - [x] 8.6 Update `admin.shop.products.edit` view for asset pool
    - Add "Linked Assets" section below existing fields (only for equipment_rental products)
    - Table: Device Name, Serial Number, Status badge, Location, Actions (unlink)
    - "Link Existing Asset" button (modal or dropdown with asset search)
    - "Create New Asset" button (links to asset create with product_id pre-filled)
    - Show available count / total count
    - _Requirements: 2.2, 2.5_

- [x] 9. Portal Views (Quantity Selection)
  - [x] 9.1 Update `portal.shop.show` for one-off quantity input
    - Add quantity number input (min 1, max stock_quantity) inside the add-to-cart form for one-off products
    - Use same styling as the rental quantity input
    - Show "X in stock" helper text below input
    - Update form to submit quantity value
    - _Requirements: 1.1_

  - [x] 9.2 Update `portal.cart.index` for quantity adjustment
    - For one-off items: display quantity with +/- buttons or a number input
    - Add a form/AJAX call to PUT /portal/cart/{index}/quantity on change
    - Update line total display to show price × quantity
    - Show stock validation error if quantity exceeds available stock
    - Rental items: show quantity as read-only (already set at add time)
    - _Requirements: 1.4, 1.5_

  - [x] 9.3 Update `portal.orders.show` for quantity display
    - Show quantity column in the items table for one-off products (e.g. "×3")
    - Adjust line total display to reflect quantity × unit price
    - _Requirements: 1.7_

- [x] 10. Checkout flow enhancement for quantity stock decrement
  - [x] 10.1 Update `CheckoutService` to handle quantity for one-off products
    - On successful checkout: decrement product stock_quantity by the ordered quantity (not just 1)
    - Add stock re-validation at checkout time (race condition protection): if stock insufficient, throw exception with user-friendly message
    - Use `DB::transaction` with pessimistic lock on product row during stock decrement
    - _Requirements: 1.3, 1.6_

- [x] 11. Route registration and wiring
  - [x] 11.1 Register fulfilment queue routes
    - Add admin fulfilment routes group with all FulfilmentQueueController methods
    - Add admin customer shop route
    - Add portal cart quantity update route (PUT)
    - _Requirements: 3.2, 5.1, 1.4_

  - [x] 11.2 Wire fulfilment stage into existing checkout flow
    - In CheckoutService (or FulfilmentService): after creating a booking, set fulfilment_stage to 'ordered'
    - Ensure existing admin booking markReturned flow triggers advance to 'returned' stage
    - _Requirements: 3.1_

  - [x] 11.3 Add navigation links
    - Admin sidebar: add "Fulfilment Queue" link under Shop section
    - Admin customer page header: add "Shop & Rentals" button
    - _Requirements: 3.2, 5.1_

- [x] 12. Final Checkpoint — End-to-end verification
  - Verify one-off product quantity selection works: add with qty > 1, adjust in cart, checkout decrements stock correctly
  - Verify fulfilment lifecycle: create booking → advance through all 6 stages → photos stored → assets released
  - Verify customer shop page: KPIs calculate correctly, orders/bookings display, documents downloadable
  - Verify asset linking: product edit shows linked assets, assignment works from fulfilment queue

## Notes

- All photo uploads use local storage (`storage/app/inspections/`)
- Customer portal never shows asset serial numbers or inspection details — only product name, quantity, delivery instructions
- The fulfilment_stage field is separate from the existing booking `status` field — status tracks the rental lifecycle (confirmed/active/returned/cancelled), while fulfilment_stage tracks the physical handling workflow
- Existing admin booking "Mark Returned" flow should be enhanced to also advance the fulfilment stage
- Image resizing (max 1920px) can use PHP GD extension (already available in XAMPP) — no extra package needed
- The `track_individual_assets` flag is opt-in per product; products without it continue using manual stock_quantity

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "1.4", "1.5"] },
    { "id": 1, "tasks": ["2.1", "2.2", "2.3", "2.4", "2.5"] },
    { "id": 2, "tasks": ["3"] },
    { "id": 3, "tasks": ["4.1", "4.2", "4.3", "4.4"] },
    { "id": 4, "tasks": ["5"] },
    { "id": 5, "tasks": ["6.1", "6.2", "6.3", "6.4", "7.1", "7.2", "7.3"] },
    { "id": 6, "tasks": ["8.1", "8.2", "8.3", "8.4", "8.5", "8.6", "9.1", "9.2", "9.3"] },
    { "id": 7, "tasks": ["10.1"] },
    { "id": 8, "tasks": ["11.1", "11.2", "11.3"] },
    { "id": 9, "tasks": ["12"] }
  ]
}
```
