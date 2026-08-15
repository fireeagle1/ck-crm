# Implementation Plan: Shop Enhancements

## Overview

This plan implements twelve capability domains extending the existing Customer Shop into a production-ready commerce platform. Tasks are organized by dependency: database schema and models first, then service layer, then controllers and views, with integration and wiring last. The implementation uses PHP/Laravel following the existing patterns (CartService, StripeService, FulfilmentService, Setting model, Admin/Portal controller split).

## Tasks

- [x] 1. Database migrations and model foundations
  - [x] 1.1 Create `bookings` table migration
    - Create migration with columns: id, order_item_id (FK), product_id (FK), company_id (FK), start_date, end_date, quantity, total_price, status enum (confirmed/active/returned/cancelled), returned_at, signature_data (longText), agreement_accepted_at, agreement_text_snapshot (longText), timestamps
    - Add indexes on (product_id, start_date, end_date), (company_id, status), (status)
    - _Requirements: 6.4, 13.3, 14.5, 15.1_

  - [x] 1.2 Create `processed_webhook_events` table migration
    - Create migration with columns: id, stripe_event_id (string 255 unique), event_type (string 100), processed_at (timestamp), created_at (timestamp)
    - Add unique index on stripe_event_id and index on created_at for purge query
    - _Requirements: 9.1, 9.3_

  - [x] 1.3 Create migration to add columns to `products` table
    - Add columns: min_rental_days (int nullable), cooldown_days (int nullable default 0), rental_agreement_text (longText nullable), delivery_instructions (text nullable), low_stock_threshold (int nullable), low_stock_notified (boolean default false)
    - _Requirements: 4.3, 5.4, 7.4, 12.1, 20.1_

  - [x] 1.4 Create migration to add columns to `orders` table
    - Add columns: delivery_address_line1, delivery_address_line2, delivery_city, delivery_state, delivery_postal_code, delivery_country (all string nullable), invoice_pdf_path (string nullable)
    - Modify payment_status enum to include 'paid_offline'
    - _Requirements: 16.5, 17.3, 22.5_

  - [x] 1.5 Create migration to add columns to `order_items` table
    - Add columns: domain_name (string nullable), quantity (int default 1), rental_start_date (date nullable), rental_end_date (date nullable), booking_id (bigint FK nullable)
    - _Requirements: 2.3, 4.1, 6.4_

  - [x] 1.6 Create migration to add columns to `services` table
    - Add columns: domain_name (string nullable), cpanel_username (string nullable)
    - _Requirements: 3.1, 3.4_

  - [x] 1.7 Create `Booking` model with relationships and fillable attributes
    - Define relationships: belongsTo OrderItem, belongsTo Product, belongsTo Customer (company_id)
    - Add status enum casting, date casts for start_date/end_date
    - Add scopes: confirmed(), active(), upcoming(), forProduct(), overlapping(startDate, endDate)
    - _Requirements: 5.2, 6.4, 19.3, 19.4_

  - [x] 1.8 Create `ProcessedWebhookEvent` model
    - Define fillable: stripe_event_id, event_type, processed_at
    - Add timestamp casting for processed_at, created_at
    - _Requirements: 9.1_

  - [x] 1.9 Update `Product` model with new fillable attributes and casts
    - Add new columns to fillable array
    - Add casts for min_rental_days, cooldown_days, low_stock_threshold, low_stock_notified
    - Add relationship: hasMany Booking
    - Add helper: isEquipmentRental(), isHosting(), isOneOff(), hasRentalAgreement()
    - _Requirements: 4.3, 5.4, 12.3, 20.3_

  - [x] 1.10 Update `Order`, `OrderItem`, `Service` models with new attributes
    - Order: add delivery address fields to fillable, add relationship hasMany OrderItem (already exists), add invoice_pdf_path
    - OrderItem: add domain_name, quantity, rental_start_date, rental_end_date, booking_id to fillable; add belongsTo Booking relationship
    - Service: add domain_name, cpanel_username to fillable
    - _Requirements: 2.3, 3.1, 22.5_

- [x] 2. Availability and Booking Service
  - [x] 2.1 Implement `AvailabilityService`
    - Create `app/Services/AvailabilityService.php`
    - Implement `getBookedUnitsPerDay(Product, rangeStart, rangeEnd)`: query confirmed/active bookings overlapping range, count units per day including cooldown_days
    - Implement `isAvailable(Product, startDate, endDate, quantity)`: check every day in range has enough remaining units
    - Account for cooldown_days after each booking's end_date
    - _Requirements: 5.2, 5.3, 5.4, 5.5_

  - [x]* 2.2 Write property tests for AvailabilityService
    - **Property 2: Availability Invariant** — For any product with stock_quantity=N and any set of confirmed bookings, booked units on any date never exceed N
    - **Property 3: Cooldown Enforcement** — For any product with cooldown_days=C and booking ending on date D, dates D+1 through D+C are unavailable
    - **Validates: Requirements 5.2, 5.3, 5.4, 6.1**

  - [x] 2.3 Implement `BookingService`
    - Create `app/Services/BookingService.php`
    - Implement `checkAvailability()` delegating to AvailabilityService
    - Implement `getUnavailableDates()` for calendar UI
    - Implement `createBooking()` with pessimistic locking: SELECT FOR UPDATE on overlapping bookings, re-check availability, insert booking
    - Implement `markReturned(Booking)` updating status and returned_at
    - Implement `calculateTotal(Product, startDate, endDate, quantity)`: price × days × quantity
    - Throw `BookingConflictException` if dates no longer available after lock
    - _Requirements: 5.1, 5.5, 6.1, 6.2, 6.4, 6.5_

  - [x]* 2.4 Write property tests for BookingService
    - **Property 1: Rental Price Calculation** — For any price > 0, days > 0, quantity ≥ 1, total = price × days × quantity
    - **Property 4: Minimum Rental Period Enforcement** — For any dates where days < min_rental_days, booking request is rejected
    - **Validates: Requirements 4.2, 4.3, 4.6**

- [x] 3. Checkpoint - Ensure migrations run and core service tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. WHM Service and Hosting Provisioning
  - [x] 4.1 Implement `WhmService`
    - Create `app/Services/WhmService.php`
    - Implement `testConnection(hostname, apiToken)`: make a WHM API listaccts call to verify connectivity
    - Implement `createAccount(domain, package, contactEmail)`: call WHM createacct API, return cPanel username
    - Use Laravel HTTP client with encrypted credentials from Settings
    - Throw `WhmConnectionException` on connectivity failure, `WhmProvisioningException` on account creation failure
    - _Requirements: 1.2, 1.3, 3.3, 3.5_

  - [x] 4.2 Create `Admin\WhmConfigController`
    - Create `app/Http/Controllers/Admin/WhmConfigController.php`
    - `edit()`: load current WHM settings from Setting model, render form view
    - `update(Request)`: validate inputs, call `WhmService::testConnection()`, on success encrypt and store via `Setting::set()`, on failure return with error and retain form values
    - Store whm_hostname and whm_api_token encrypted via `Crypt::encryptString()`
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x]* 4.3 Write property test for WHM credential encryption
    - **Property 14: WHM Credentials Encryption** — For any saved WHM API token, the stored database value ≠ plaintext input
    - **Validates: Requirements 1.4**

  - [x] 4.4 Create `Admin\HostingProvisionController`
    - Create `app/Http/Controllers/Admin/HostingProvisionController.php`
    - `index()`: query Services where status='pending' and product type is hosting, render provisioning queue view
    - `provision(Service)`: call `WhmService::createAccount()`, on success update Service status to 'Active', store cpanel_username, update Order fulfilment_status to 'completed', trigger provisioned notification; on failure display error
    - _Requirements: 3.2, 3.3, 3.4, 3.5, 3.6_

  - [x]* 4.5 Write unit tests for WhmService and HostingProvisionController
    - Test testConnection with mocked HTTP responses (success and failure)
    - Test createAccount with mocked WHM API (success and failure)
    - Test provision flow updates Service status and stores cpanel_username
    - _Requirements: 1.2, 3.3, 3.4, 3.5_

- [x] 5. Stripe and Webhook Enhancements
  - [x] 5.1 Enhance `StripeService` with customer sync and one-time payments
    - Add `ensureCustomer(Customer)` method: verify existing stripe_customer_id via Stripe API, catch "customer not found" → recreate with company_name and email, update customer record, retry
    - Add `createOneTimePayment(amount, customerId, metadata)` method for rental bookings
    - _Requirements: 11.1, 11.2, 11.3, 6.3_

  - [x]* 5.2 Write property test for Stripe customer resilience
    - **Property 7: Stripe Customer Resilience** — For any customer with stale stripe_customer_id, ensureCustomer() results in valid new ID stored on customer
    - **Validates: Requirements 11.1, 11.2, 11.3**

  - [x] 5.3 Implement webhook idempotency in `StripeWebhookController`
    - Add Stripe signature verification before processing (Req 9.4)
    - Check `ProcessedWebhookEvent` for existing event_id before handling
    - If found, return 200 immediately without processing
    - If not found, insert event_id row, then process within DB::transaction
    - On transaction failure: delete event_id row (allow Stripe retry), log error
    - On success: return 200
    - _Requirements: 9.1, 9.2, 9.4, 10.1, 10.2, 10.3_

  - [x]* 5.4 Write property tests for webhook idempotency and transactional atomicity
    - **Property 5: Webhook Idempotency** — Processing same event_id N times produces same DB state as once
    - **Property 6: Transactional Atomicity** — If any fulfilment sub-step fails, no records persist
    - **Validates: Requirements 9.1, 9.2, 10.1, 10.2, 10.3**

  - [x] 5.5 Enhance `FulfilmentService` with transactional wrapping
    - Wrap `fulfilOrder()` in `DB::transaction()`
    - On hosting payment: create Service with status 'pending' and domain_name
    - On rental payment: create Booking via BookingService, set status 'active'
    - On any failure: full rollback, log details with event context
    - _Requirements: 10.1, 10.2, 10.3, 3.1, 6.4_

- [x] 6. Checkpoint - Ensure webhook and Stripe service tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Notification and PDF Services
  - [x] 7.1 Implement `NotificationService`
    - Create `app/Services/NotificationService.php`
    - Implement `notifyAdminNewOrder(Order)`: dispatch queued mail to admin
    - Implement `notifyAdminRentalEnded(Booking)`: dispatch queued mail for rental end
    - Implement `notifyCustomerReturnConfirmed(Booking)`: dispatch queued mail to customer
    - Implement `notifyAdminLowStock(Product)`: dispatch queued mail, set low_stock_notified=true
    - Implement `notifyPaymentFailure(Order, reason)`: dispatch to admin AND customer
    - Implement `notifyCustomerHostingProvisioned(Service)`: dispatch with nameservers, cPanel username
    - Implement `sendOrderConfirmation(Order)`: dispatch with line items, delivery instructions, PDF attachment if available
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 8.1, 8.2, 3.6, 18.3, 21.1, 21.2_

  - [x] 7.2 Create email Mailable/Notification classes and Blade templates
    - Create Mail classes: OrderConfirmation, AdminNewOrder, PaymentFailedAdmin, PaymentFailedCustomer, RentalEndedAdmin, ReturnConfirmed, LowStockAdmin, HostingProvisioned
    - Create Blade templates in `resources/views/emails/` for each mailable
    - OrderConfirmation template includes delivery instructions for applicable items and PDF attachment
    - HostingProvisioned template includes nameserver instructions and cPanel username
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 8.1, 8.2, 3.6, 18.3, 21.1, 21.2_

  - [x] 7.3 Implement `PdfInvoiceService`
    - Create `app/Services/PdfInvoiceService.php`
    - Install and configure `barryvdh/laravel-dompdf`
    - Implement `generate(Order)`: create PDF with company name/address/logo, customer name/address, order date, line items (product name, price), VAT, total, Stripe payment reference
    - Store PDF at `storage/app/invoices/order-{id}.pdf`, save path on Order record
    - Return null and log error on failure (do not block fulfilment)
    - _Requirements: 17.1, 17.2, 17.3, 17.4_

  - [x]* 7.4 Write property test for PDF invoice content completeness
    - **Property 15: PDF Invoice Content Completeness** — For any paid Order, generated PDF contains company name, customer name, line item names, and total
    - **Validates: Requirements 17.2**

  - [x] 7.5 Create `ProductObserver` for low-stock notifications
    - Create `app/Observers/ProductObserver.php`
    - On `updated` event: if stock_quantity changed and now ≤ low_stock_threshold and low_stock_notified == false, dispatch low-stock notification and set flag
    - Register observer in AppServiceProvider
    - _Requirements: 7.4, 7.5_

  - [x]* 7.6 Write property test for low-stock notification lifecycle
    - **Property 12: Low-Stock Notification Once-Per-Breach** — System sends at most one notification per threshold breach; after reset, can fire again
    - **Validates: Requirements 7.4, 7.5**

  - [x] 7.7 Create `OrderObserver` for PDF generation trigger
    - On `updated` event: if payment_status changed to 'paid' or 'paid_offline', dispatch PdfInvoiceService::generate() as queued job
    - Register observer in AppServiceProvider
    - _Requirements: 17.1_

- [x] 8. Scheduled Commands
  - [x] 8.1 Create `app:purge-webhook-events` Artisan command
    - Delete processed_webhook_events where created_at < now - 7 days
    - Schedule daily at 02:00 in Console Kernel
    - _Requirements: 9.3_

  - [x] 8.2 Create `app:notify-rental-ended` Artisan command
    - Find bookings with end_date = today and status = 'active'
    - Dispatch NotificationService::notifyAdminRentalEnded() for each
    - Schedule daily at 08:00 in Console Kernel
    - _Requirements: 7.2_

  - [x] 8.3 Create `app:reset-low-stock-flags` Artisan command
    - Find products where low_stock_notified = true AND stock_quantity > low_stock_threshold
    - Reset low_stock_notified = false
    - Schedule hourly in Console Kernel
    - _Requirements: 7.5_

- [x] 9. Checkout and Cart Enhancements
  - [x] 9.1 Enhance `CartService` for rental and hosting item data
    - Add support for `domain_name` field on hosting cart items
    - Add support for `rental_start_date`, `rental_end_date`, `quantity` on rental cart items
    - Update add-to-cart logic to validate: domain format for hosting, minimum rental period, date availability for rentals
    - _Requirements: 2.1, 2.2, 4.1, 4.3, 5.5_

  - [x]* 9.2 Write property tests for cart validation rules
    - **Property 13: Domain Name Validation** — Valid domain format accepted, invalid rejected
    - **Property 4: Minimum Rental Period** — Dates shorter than min_rental_days rejected
    - **Validates: Requirements 2.2, 4.3**

  - [x] 9.3 Enhance `CheckoutService` for full checkout flow
    - Add delivery address validation and storage on Order (skip for hosting-only carts)
    - Route rental items through BookingService::createBooking() inside DB::transaction
    - Route hosting items to create Service with 'pending' status and domain_name
    - Handle rental agreement acceptance: store acceptance timestamp and agreement text snapshot on Booking
    - Handle signature data: store base64 PNG on Booking record
    - Trigger PdfInvoiceService::generate() after payment
    - Trigger NotificationService::sendOrderConfirmation() after order creation
    - _Requirements: 2.3, 3.1, 6.1, 6.3, 6.4, 13.3, 14.5, 15.1, 17.1, 22.1, 22.4, 22.5, 22.6_

  - [x]* 9.4 Write property tests for address form visibility and validation
    - **Property 9: Address Form Visibility** — Hosting-only cart skips address; cart with physical items shows address
    - **Property 10: Address Validation** — Missing any of address_line1/city/postal_code/country causes rejection
    - **Validates: Requirements 22.1, 22.4, 22.6**

- [x] 10. Checkpoint - Ensure checkout flow and cart tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Portal Controllers and Views (Customer-Facing)
  - [x] 11.1 Enhance `Portal\ShopController` for rental dates and hosting domain
    - `show(Product)`: pass date picker data for rental items, domain input for hosting items
    - `addToCart(Request, Product)`: validate domain format, rental dates against min_rental_days, check availability via AvailabilityService
    - Create/update Blade view `portal.shop.show` with Flatpickr date picker, quantity input, and domain name input conditionally
    - _Requirements: 2.1, 2.2, 4.1, 4.3, 4.4, 4.5, 4.6_

  - [x] 11.2 Create `Portal\BookingController` availability endpoint
    - Create `app/Http/Controllers/Portal/BookingController.php`
    - `availability(Product)`: JSON endpoint returning unavailable dates via BookingService::getUnavailableDates() for calendar rendering
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 11.3 Enhance `Portal\CartController` checkout view and processing
    - `checkout()`: render multi-section checkout form with address collection (pre-filled from Customer), rental agreement panel with signature canvas for applicable items, domain confirmation for hosting
    - `processCheckout(Request)`: validate all sections, call enhanced CheckoutService, handle BookingConflictException with user-friendly redirect
    - Create/update Blade view `portal.cart.checkout` with address form, agreement text display, signature_pad canvas, payment section
    - _Requirements: 2.4, 13.1, 13.2, 14.1, 14.2, 14.3, 14.4, 22.1, 22.2, 22.3, 22.4_

  - [x] 11.4 Enhance `Portal\OrderController` with rental details, signature, PDF download
    - `show(Order)`: display rental agreement text (accordion), signature image as data-URI, delivery instructions per OrderItem, PDF download button if invoice_pdf_path exists
    - `downloadInvoice(Order)`: authorize customer owns order, stream PDF from storage
    - Update Blade view `portal.orders.show` with agreement section, signature img, delivery instructions, download link
    - _Requirements: 13.4, 15.3, 18.2, 18.4, 21.3_

  - [x] 11.5 Enhance `Portal\DashboardController` with rental summary
    - Query active bookings (start_date ≤ today, end_date > today) and upcoming bookings (start_date > today) for authenticated customer
    - Pass booking data to view with product name, dates, status indicator, days remaining
    - Update Blade view `portal.dashboard` with rental summary card; hide section if no bookings
    - Link each booking to its order detail page
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6_

  - [x]* 11.6 Write property test for booking status classification
    - **Property 8: Booking Status Classification** — For S ≤ T < E → "Active"; T < S → "Upcoming"
    - **Validates: Requirements 19.3, 19.4**

- [x] 12. Admin Controllers and Views
  - [x] 12.1 Enhance `Admin\ShopProductController` with rental and delivery fields
    - `edit/update(Product)`: add rental_agreement_text rich-text editor (TinyMCE/Trix), delivery_instructions textarea, min_rental_days input, cooldown_days input, low_stock_threshold input
    - Hide delivery_instructions field for hosting products
    - Update Blade view `admin.shop.products.edit` with new form fields
    - _Requirements: 12.1, 12.2, 20.1, 20.2, 20.3_

  - [x] 12.2 Create `Admin\BookingController` for booking management and manual creation
    - Create `app/Http/Controllers/Admin/BookingController.php`
    - `index()`: list all bookings with status filters (confirmed, active, returned, cancelled)
    - `show(Booking)`: display booking details with rendered signature image
    - `markReturned(Booking)`: call BookingService::markReturned(), trigger NotificationService::notifyCustomerReturnConfirmed()
    - `create()`: manual booking form with customer select, product select, Flatpickr dates, quantity, "paid offline" checkbox
    - `store(Request)`: validate availability (same rules as portal), create Order/OrderItem/Booking in DB::transaction, set payment_status to 'paid_offline' or 'pending'
    - Create Blade views: `admin.shop.bookings.index`, `admin.shop.bookings.show`, `admin.shop.bookings.create`
    - _Requirements: 7.3, 15.2, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7_

  - [x]* 12.3 Write property test for admin/portal availability consistency
    - **Property 16: Admin and Portal Availability Consistency** — For same (product, dates, quantity), admin and portal availability checks return same result
    - **Validates: Requirements 16.3**

  - [x] 12.4 Enhance `Admin\ShopOrderController` with signature, delivery, and PDF
    - `show(Order)`: display signature image for rental orders, delivery instructions per OrderItem, PDF download link if available
    - `downloadInvoice(Order)`: stream PDF from storage
    - Update Blade view `admin.shop.orders.show` with signature, instructions, download button
    - _Requirements: 15.2, 18.1, 18.4, 21.4_

  - [x] 12.5 Create WHM config and hosting provisioning admin views
    - Create Blade view `admin.settings.whm`: form with hostname, API token (password field), default package, nameserver fields, test & save button
    - Create Blade view `admin.hosting.provision.index`: table of pending services with customer, domain, product, approve button
    - Add routes for WhmConfigController and HostingProvisionController
    - _Requirements: 1.1, 3.2_

- [x] 13. Dynamic Portal Navigation
  - [x] 13.1 Implement View Composer for portal navigation visibility
    - Create ViewComposer or add logic in AppServiceProvider to compose `layouts.portal`
    - Pass showServices, showDomains, showInvoices booleans based on customer record existence
    - Update portal layout Blade: conditionally show/hide Services, Domains, Invoices links
    - Always show Dashboard, Support, Shop, Help
    - _Requirements: 23.1, 23.2, 23.3, 23.4, 23.5_

  - [x]* 13.2 Write property test for navigation visibility
    - **Property 11: Dynamic Navigation Visibility** — Nav links visible iff customer has ≥1 record of that type; Dashboard/Support/Shop/Help always visible
    - **Validates: Requirements 23.1, 23.2, 23.3, 23.4, 23.5**

- [x] 14. Frontend Assets and Integration
  - [x] 14.1 Install and configure frontend dependencies
    - Install `flatpickr` via npm for date range picking
    - Install `signature_pad` via npm for canvas signature capture
    - Configure asset compilation (Vite/Mix) to include new packages
    - _Requirements: 4.1, 14.1_

  - [x] 14.2 Implement signature pad JavaScript component
    - Create JS module for signature capture: initialize signature_pad on canvas, implement clear button, capture as base64 PNG on form submit, validate signature exists before allowing submission
    - Include in checkout Blade view for rental items with agreements
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

  - [x] 14.3 Implement rental date picker with availability calendar
    - Create JS module for Flatpickr: fetch unavailable dates from availability endpoint, disable unavailable dates, calculate and display total price on date selection, enforce min_rental_days
    - Include in product detail view for equipment_rental products and admin manual booking form
    - _Requirements: 4.1, 4.2, 4.4, 5.1_

- [x] 15. Routing and Wiring
  - [x] 15.1 Register all new routes
    - Admin routes: WHM config (GET/POST), hosting provisioning (GET index, POST provision), bookings (resource), order invoice download
    - Portal routes: booking availability (GET JSON), checkout (GET/POST), order invoice download
    - Apply appropriate middleware (auth, admin role checks)
    - _Requirements: 1.1, 3.2, 5.1, 16.1, 18.1, 18.2_

  - [x] 15.2 Register observers and service providers
    - Register ProductObserver and OrderObserver in AppServiceProvider
    - Register scheduled commands in Console Kernel (purge-webhook-events daily 02:00, notify-rental-ended daily 08:00, reset-low-stock-flags hourly)
    - _Requirements: 7.4, 7.5, 9.3, 17.1_

  - [x] 15.3 Wire notification triggers into existing flows
    - StripeWebhookController: trigger notifyAdminNewOrder on order creation, trigger notifyPaymentFailure on payment failure events, trigger PDF generation on payment success
    - FulfilmentService: trigger sendOrderConfirmation after fulfilment
    - Admin BookingController markReturned: trigger notifyCustomerReturnConfirmed
    - _Requirements: 7.1, 8.1, 8.2, 17.1, 18.3_

- [x] 16. Final Checkpoint - Full integration verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- The implementation uses PHP/Laravel following existing project patterns
- DomPDF package (`barryvdh/laravel-dompdf`) needs to be installed via Composer
- Frontend assets (flatpickr, signature_pad) need npm install and asset compilation
- All encrypted settings use Laravel's `Crypt` facade consistent with the existing Setting model pattern

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "1.4", "1.5", "1.6"] },
    { "id": 1, "tasks": ["1.7", "1.8", "1.9", "1.10"] },
    { "id": 2, "tasks": ["2.1", "4.1", "5.1", "14.1"] },
    { "id": 3, "tasks": ["2.2", "2.3", "4.2", "4.3", "5.2", "5.3"] },
    { "id": 4, "tasks": ["2.4", "4.4", "4.5", "5.4", "5.5", "7.1"] },
    { "id": 5, "tasks": ["7.2", "7.3", "7.5", "7.7", "8.1", "8.2", "8.3"] },
    { "id": 6, "tasks": ["7.4", "7.6", "9.1"] },
    { "id": 7, "tasks": ["9.2", "9.3"] },
    { "id": 8, "tasks": ["9.4", "11.1", "11.2", "12.1", "13.1"] },
    { "id": 9, "tasks": ["11.3", "11.5", "12.2", "12.4", "12.5", "14.2", "14.3"] },
    { "id": 10, "tasks": ["11.4", "11.6", "12.3", "13.2"] },
    { "id": 11, "tasks": ["15.1", "15.2"] },
    { "id": 12, "tasks": ["15.3"] }
  ]
}
```
