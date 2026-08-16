# Requirements: Rental Fulfilment v2

## Introduction

This spec extends the existing Customer Shop with three capabilities: (1) quantity selection for one-off product purchases, (2) a full checkout/packing/collection/inspection lifecycle for rental bookings with photo evidence, and (3) a dedicated customer shop & rental history page in the admin panel aggregating all commerce data per customer.

The system uses local file storage for photos and PDFs. Customers see product names, quantities, and delivery instructions — not individual asset serial numbers. Agreements, invoices, and inspection records are stored against the customer's account for admin reference.

## Glossary

- **Packing List**: A record of specific assets assigned to a booking before dispatch, created by an admin during the packing stage.
- **Booking Inspection**: A record capturing the condition of equipment at checkout (departure) or return, including photos and notes.
- **Fulfilment Stage**: The current lifecycle state of a rental booking's physical fulfilment: Ordered → Packing → Ready → Checked Out → Returned → Inspected.
- **Asset Pool**: The set of Asset (CMDB) records linked to a specific Product, representing the individual trackable units available for rental.
- **Booking Asset**: A pivot record linking a specific Asset to a Booking, representing assignment of that physical unit to the customer's rental.

## Requirements

### Requirement 1: Quantity Selection for One-Off Purchases

**User Story:** As a customer, I want to select a quantity when purchasing one-off products, so that I can buy multiple units in a single transaction without adding items one at a time.

#### Acceptance Criteria

1. WHEN a customer views a product detail page for a one-off Product, THE Shop SHALL display a quantity input field with a minimum value of 1.
2. WHEN a customer sets a quantity and adds the product to cart, THE Cart SHALL store the selected quantity against the cart item.
3. IF the selected quantity exceeds the Product's stock_quantity, THEN THE Shop SHALL reject the add-to-cart request and display a stock limitation message.
4. THE Cart page SHALL display the quantity for each item and allow the customer to adjust the quantity before checkout.
5. WHEN a customer adjusts quantity on the cart page, THE Cart SHALL re-validate against current stock_quantity and update the line total (price × quantity).
6. WHEN checkout completes for a one-off product with quantity > 1, THE Shop SHALL decrement the Product stock_quantity by the purchased quantity.
7. THE Order detail page SHALL display the quantity purchased for each one-off item.

### Requirement 2: Asset Pool Linking

**User Story:** As an admin, I want to link individual CMDB assets to rental products, so that I can track which specific serial-numbered units are available in a product's rental pool.

#### Acceptance Criteria

1. THE Admin Panel SHALL allow admins to associate an Asset (CMDB record) with a Product by setting a product_id on the Asset.
2. WHEN an admin edits a Product of type Equipment Rental, THE Admin Panel SHALL display a list of all Assets linked to that Product with their serial number, device name, and current status.
3. THE Asset status options SHALL include: Available, Rented Out, Reserved, In Repair, Decommissioned.
4. WHEN an Asset is linked to a Product, THE system SHALL use the count of "Available" assets to inform availability (alongside the existing stock_quantity for non-tracked products).
5. THE Admin Panel SHALL allow admins to create a new Asset directly from the Product edit page with the product_id pre-filled.
6. WHEN viewing an individual Asset, THE Admin Panel SHALL display the Asset's booking history (all bookings where this asset was assigned).

### Requirement 3: Booking Fulfilment Lifecycle

**User Story:** As an admin, I want to progress rental bookings through a structured fulfilment workflow (packing → ready → checked out → returned → inspected), so that I can track the physical handling of equipment and maintain accountability.

#### Acceptance Criteria

1. WHEN a rental booking is confirmed and paid, THE system SHALL set its fulfilment stage to "ordered".
2. THE Admin Panel SHALL provide a fulfilment queue view showing bookings grouped by their current fulfilment stage.
3. WHEN an admin moves a booking to "packing" stage, THE Admin Panel SHALL present an asset assignment interface showing available assets for that product.
4. THE Admin Panel SHALL allow admins to select one or more specific assets from the pool and assign them to the booking (creating booking_asset records).
5. WHEN assets are assigned to a booking, THE system SHALL update those assets' status to "Reserved".
6. WHEN an admin marks a booking as "ready", THE system SHALL validate that at least one asset is assigned and display the packing list summary.
7. WHEN an admin marks a booking as "checked_out", THE Admin Panel SHALL require at least one departure photo upload and allow optional condition notes.
8. WHEN a booking is checked out, THE system SHALL update assigned assets' status to "Rented Out".
9. WHEN an admin marks a booking as "returned", THE system SHALL record the return timestamp on the booking.
10. WHEN an admin marks a booking as "inspected", THE Admin Panel SHALL require at least one return photo upload, allow condition notes, and record the inspecting admin's identity.
11. WHEN a booking is inspected, THE system SHALL update assigned assets' status back to "Available" (or "In Repair" if the admin flags damage).
12. THE system SHALL NOT allow skipping stages — transitions must follow the defined order.
13. WHEN a customer views their order/booking in the portal, THE Portal SHALL display only the product name, quantity, and delivery instructions — NOT individual asset serial numbers or internal inspection details.

### Requirement 4: Booking Inspection Records

**User Story:** As an admin, I want to capture photographic evidence and condition notes at checkout and return, so that I can resolve disputes about equipment damage.

#### Acceptance Criteria

1. THE system SHALL store inspection records with: booking_id, type (checkout or return), one or more photo file paths, condition_notes (text), inspected_by (admin user_id), and inspected_at timestamp.
2. Photos SHALL be stored on the local filesystem under `storage/app/inspections/{booking_id}/`.
3. THE Admin Panel SHALL allow uploading multiple photos (up to 10) per inspection record.
4. THE Admin Panel SHALL display inspection photos as a thumbnail gallery on the booking detail page.
5. WHEN viewing a booking detail, THE Admin Panel SHALL show both checkout and return inspections side-by-side for comparison.
6. Inspection records SHALL be immutable once created — admins cannot edit or delete photos after submission (only add a new inspection if needed).

### Requirement 5: Customer Shop & Rental History Page (Admin)

**User Story:** As an admin, I want a dedicated page showing all commerce and rental activity for a customer, so that I can quickly assess their rental history, spending patterns, and current obligations.

#### Acceptance Criteria

1. THE Admin Panel SHALL provide a "Shop & Rentals" tab/section on the customer detail page.
2. THE page SHALL display KPI cards: total rental spend, total purchase spend, number of completed rentals, number of purchases, and average order value.
3. THE page SHALL display an order history table for the customer showing: order ID, date, items, type badges (One-Off/Rental/Hosting), total, payment status, and fulfilment status.
4. THE page SHALL display active bookings for the customer showing: product name, dates, fulfilment stage, assigned asset count, and days remaining.
5. THE page SHALL display past bookings showing: product name, dates, return status, inspection summary (condition notes preview), and total price.
6. THE page SHALL display links to download any agreement PDFs and invoice PDFs associated with the customer's orders.
7. THE page SHALL calculate and display a "loyalty summary": total lifetime spend, number of orders, customer since date, and current tier (if assigned via CustomerTier).
8. THE page SHALL allow filtering orders/bookings by date range and product type.

### Requirement 6: Agreement & Invoice Storage on Customer Account

**User Story:** As an admin, I want agreements, invoices, and inspection records stored against the customer's account, so that I have a complete audit trail accessible from the customer page.

#### Acceptance Criteria

1. WHEN a rental agreement is accepted at checkout, THE system SHALL store the agreement text snapshot and acceptance timestamp on the Booking record (existing behaviour) AND make it accessible from the customer shop page.
2. WHEN an invoice PDF is generated, THE system SHALL store the file path on the Order record (existing behaviour) AND make it downloadable from the customer shop page.
3. THE customer shop page SHALL provide a "Documents" section listing all agreements and invoices for the customer with download links.
4. Documents SHALL be grouped by order, showing: order ID, date, document type (Invoice/Agreement), and a download action.
