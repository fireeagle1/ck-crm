# Requirements Document

## Introduction

The Shop Enhancements feature extends the existing Customer Shop with twelve capability domains: hosting auto-provisioning via WHM, date-based equipment rental booking with calendar availability, admin and customer email notifications, production-grade webhook and payment resilience, rental agreements with digital signatures, admin manual booking, PDF invoice/receipt generation, customer dashboard rental display, delivery/collection instructions, address collection at checkout, and dynamic portal navigation. These enhancements transform the shop from a basic storefront into a production-ready commerce platform with full lifecycle management.

## Glossary

- **Shop**: The customer-facing storefront within the Portal at `/portal/shop`.
- **Portal**: The customer-facing interface at `/portal` for authenticated Customers.
- **Admin_Panel**: The administrative interface at `/admin` for administrators.
- **Product**: A purchasable or rentable item in the catalog with product_type of `hosting`, `one_off`, or `equipment_rental`.
- **Order**: A record of a Customer's completed purchase, linking Customer to one or more OrderItems with payment and fulfilment status.
- **OrderItem**: A line item within an Order referencing a Product, price, billing frequency, and associated Service or Booking.
- **Booking**: A date-bounded reservation of an equipment_rental Product for a specific period, linked to an OrderItem.
- **Service**: A record representing an active or pending subscription-based service for a Customer (PK: service_id).
- **Customer**: An organisation record (PK: company_id) with address fields, users, and associated Orders.
- **WHM_API**: The cPanel/WHM server management API used to auto-provision hosting accounts.
- **Hosting_Config**: Admin-configurable WHM connection settings including server hostname, API token, default package, and nameservers.
- **Rental_Agreement**: Configurable text associated with an equipment_rental Product that Customers must accept before completing checkout.
- **Digital_Signature**: A canvas-captured handwritten signature stored as base64 text, linked to a Booking and Rental_Agreement acceptance.
- **PDF_Invoice**: A DomPDF-generated document containing company details, customer details, line items, VAT, total, and payment reference.
- **Delivery_Instructions**: Admin-configurable text per Product providing delivery or collection guidance for physical items.
- **Notification_Queue**: A queued email dispatch system for sending admin and customer notifications asynchronously.
- **Webhook_Event**: An incoming Stripe event with a unique event_id subject to idempotency deduplication.
- **Cooldown_Period**: A mandatory gap between the end of one Booking and the start of the next for the same Product unit.
- **Pessimistic_Lock**: A database-level lock preventing concurrent Booking creation for the same Product dates.
- **Stripe_Customer**: The Stripe customer record associated with a Customer, created if missing or recreated if not found.

## Requirements

### Requirement 1: Hosting Auto-Provision — WHM Configuration

**User Story:** As an admin, I want to configure WHM connection settings, so that hosting products can be automatically provisioned when customers purchase them.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a WHM configuration page for setting server hostname, API token, default hosting package, and nameserver addresses (ns0.thundercloud.uk, ns1.thundercloud.uk).
2. WHEN an admin saves WHM configuration, THE Admin_Panel SHALL validate connectivity to the WHM server using the provided credentials before persisting.
3. IF WHM connectivity validation fails, THEN THE Admin_Panel SHALL display the connection error and retain the form values without saving.
4. THE Admin_Panel SHALL store WHM API credentials in encrypted form.

### Requirement 2: Hosting Auto-Provision — Domain Collection

**User Story:** As a customer, I want to specify my domain name during hosting checkout, so that my hosting account is provisioned with the correct domain.

#### Acceptance Criteria

1. WHEN a Customer adds a hosting Product to the Cart, THE Shop SHALL prompt the Customer to enter a domain name for that hosting item.
2. THE Shop SHALL validate the domain name format before allowing checkout to proceed.
3. WHEN checkout is completed for a hosting Product, THE Shop SHALL store the provided domain name on the OrderItem.
4. THE Shop SHALL display nameserver instructions (ns0.thundercloud.uk, ns1.thundercloud.uk) to the Customer after successful hosting purchase on the order confirmation page.

### Requirement 3: Hosting Auto-Provision — Pending and Provisioning Flow

**User Story:** As an admin, I want to review and approve hosting orders before provisioning, so that I can verify the domain and configuration before creating the WHM account.

#### Acceptance Criteria

1. WHEN a hosting Order payment completes, THE Shop SHALL create a Service record with status "pending" and store the customer-provided domain name on the Service record.
2. THE Admin_Panel SHALL display pending hosting Services in a provisioning queue with customer name, domain, and product details.
3. WHEN an admin approves a pending hosting Service, THE Admin_Panel SHALL call the WHM_API to create the hosting account with the configured package and customer domain.
4. WHEN WHM_API account creation succeeds, THE Admin_Panel SHALL update the Service status to "Active", store the cPanel username on the Service record, and update the Order fulfilment_status to "completed".
5. IF WHM_API account creation fails, THEN THE Admin_Panel SHALL display the error message and retain the Service in "pending" status for retry.
6. WHEN a hosting Service is provisioned, THE Admin_Panel SHALL send a confirmation email to the Customer containing nameserver instructions, cPanel username, and login details.

### Requirement 4: Equipment Rental Booking — Date Selection and Pricing

**User Story:** As a customer, I want to select rental dates and see the total cost based on per-day pricing, so that I can budget for equipment hire accurately.

#### Acceptance Criteria

1. WHEN a Customer views an equipment_rental Product, THE Shop SHALL display a date picker for selecting a start date and end date.
2. THE Shop SHALL calculate and display the total rental price as Product price multiplied by the number of rental days.
3. THE Shop SHALL enforce a configurable minimum rental period per Product and prevent date selections shorter than the minimum.
4. IF a Customer selects dates shorter than the minimum rental period, THEN THE Shop SHALL display a message stating the minimum required days.
5. THE Shop SHALL allow Customers to select a quantity of units for the rental period.
6. WHEN quantity exceeds one, THE Shop SHALL calculate the total as Product price multiplied by days multiplied by quantity.

### Requirement 5: Equipment Rental Booking — Calendar Availability

**User Story:** As a customer, I want to see which dates are available for rental, so that I do not attempt to book equipment that is already reserved.

#### Acceptance Criteria

1. THE Shop SHALL display a calendar view showing available and unavailable dates for each equipment_rental Product.
2. THE Shop SHALL determine availability based on existing confirmed Bookings and the Product stock_quantity (total available units).
3. WHILE all units of an equipment_rental Product are booked for a given date, THE Shop SHALL display that date as unavailable.
4. THE Shop SHALL enforce a configurable cooldown period between consecutive Bookings of the same Product unit, marking cooldown dates as unavailable.
5. WHEN a Customer selects dates, THE Shop SHALL verify availability in real-time before allowing the item to be added to the Cart.

### Requirement 6: Equipment Rental Booking — Concurrency and Payment

**User Story:** As a system operator, I want rental bookings to be protected from double-booking via concurrent requests, so that inventory is never over-allocated.

#### Acceptance Criteria

1. WHEN a Customer confirms a rental Booking at checkout, THE Shop SHALL acquire a Pessimistic_Lock on the Product availability for the selected date range before creating the Booking.
2. IF the Pessimistic_Lock reveals the dates are no longer available, THEN THE Shop SHALL reject the Booking, release the lock, and inform the Customer that the selected dates are no longer available.
3. WHEN a rental Booking is confirmed, THE Shop SHALL create a one-time Stripe payment for the total amount (price multiplied by days multiplied by quantity).
4. WHEN the Stripe payment for a rental Booking succeeds, THE Shop SHALL persist the Booking record with start date, end date, quantity, and total price, linked to the OrderItem.
5. IF the Stripe payment for a rental Booking fails, THEN THE Shop SHALL release the Pessimistic_Lock and inform the Customer of the payment failure without persisting the Booking.

### Requirement 7: Admin Notifications — Order and Stock Alerts

**User Story:** As an admin, I want to receive email notifications for key shop events, so that I can respond promptly to new orders and stock issues.

#### Acceptance Criteria

1. WHEN a new Order is created, THE Notification_Queue SHALL send an email to the admin containing order details, customer name, and product information.
2. WHEN a rental Booking end date is reached, THE Notification_Queue SHALL send an email to the admin indicating the rental period has ended and equipment is due for return.
3. WHEN a rental item is marked as returned by the admin, THE Notification_Queue SHALL send a confirmation email to the Customer confirming the return.
4. WHEN a Product stock_quantity falls to or below a configurable low-stock threshold, THE Notification_Queue SHALL send a low-stock alert email to the admin once only per threshold breach.
5. THE Notification_Queue SHALL reset the low-stock notification flag when stock_quantity rises above the threshold, allowing a future notification if stock drops again.

### Requirement 8: Admin Notifications — Payment Failure

**User Story:** As an admin and customer, I want to be notified when a payment fails, so that both parties can take corrective action.

#### Acceptance Criteria

1. WHEN a Stripe payment failure event is received, THE Notification_Queue SHALL send an email to the admin containing the Customer name, Order reference, and failure reason.
2. WHEN a Stripe payment failure event is received, THE Notification_Queue SHALL send an email to the Customer containing the Order reference, failure description, and instructions to update payment method.

### Requirement 9: Production Readiness — Idempotent Webhooks

**User Story:** As a system operator, I want webhook processing to be idempotent, so that duplicate or replayed events do not cause double-processing.

#### Acceptance Criteria

1. WHEN a Webhook_Event is received, THE Shop SHALL store the Stripe event_id in a processed events table before executing the event handler.
2. IF a Webhook_Event with a previously stored event_id is received, THEN THE Shop SHALL return a success response without re-processing.
3. THE Shop SHALL retain processed event_id records for seven days and purge records older than seven days.
4. THE Shop SHALL verify the Stripe webhook signature before processing or storing the event_id.

### Requirement 10: Production Readiness — Transactional Fulfilment

**User Story:** As a system operator, I want fulfilment operations to be atomic, so that partial failures do not leave the system in an inconsistent state.

#### Acceptance Criteria

1. WHEN processing a successful payment event, THE Shop SHALL wrap Order creation, OrderItem creation, stock decrement, Service creation, and Booking persistence within a single database transaction.
2. IF any step within the fulfilment transaction fails, THEN THE Shop SHALL roll back the entire transaction and log the failure with event details.
3. WHEN a transaction rollback occurs during fulfilment, THE Shop SHALL retain the Webhook_Event for retry processing.

### Requirement 11: Production Readiness — Stripe Customer Sync

**User Story:** As a system operator, I want the system to automatically manage Stripe customer records, so that payment processing is reliable regardless of Stripe-side state.

#### Acceptance Criteria

1. WHEN a Customer proceeds to checkout without a stripe_customer_id, THE Shop SHALL create a new Stripe customer record using the Customer company_name and primary user email, and store the stripe_customer_id on the Customer record.
2. IF a Stripe API call returns a "customer not found" error for an existing stripe_customer_id, THEN THE Shop SHALL create a new Stripe customer record, update the stored stripe_customer_id, and retry the original operation.
3. THE Shop SHALL include the Customer company_name and email when creating Stripe customer records.

### Requirement 12: Rental Agreements — Configuration

**User Story:** As an admin, I want to configure rental agreement text per product, so that customers acknowledge specific terms before renting equipment.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a rental agreement text editor on the equipment_rental Product edit form.
2. THE Admin_Panel SHALL allow admins to enter and update rich-text rental agreement content per Product.
3. WHEN no rental agreement text is configured for an equipment_rental Product, THE Shop SHALL proceed to checkout without displaying an agreement.

### Requirement 13: Rental Agreements — Customer Acceptance

**User Story:** As a customer, I want to review and accept rental terms before completing my booking, so that I understand the conditions of the equipment hire.

#### Acceptance Criteria

1. WHEN a Customer proceeds to checkout with an equipment_rental Product that has a configured Rental_Agreement, THE Shop SHALL display the agreement text with a mandatory acceptance checkbox.
2. THE Shop SHALL prevent checkout submission until the Customer has checked the agreement acceptance checkbox for each applicable rental item.
3. WHEN the Customer accepts the Rental_Agreement, THE Shop SHALL store the acceptance timestamp and link the acceptance record to the Booking.
4. THE Portal SHALL display the accepted Rental_Agreement text on the Order detail page for the Customer to review post-purchase.

### Requirement 14: Digital Signature — Capture

**User Story:** As a customer, I want to provide a digital signature at checkout for rental items, so that my agreement acceptance is formally recorded.

#### Acceptance Criteria

1. WHEN a Customer proceeds to checkout with an equipment_rental Product that has a configured Rental_Agreement, THE Shop SHALL display a canvas-based signature capture area below the agreement text.
2. THE Shop SHALL allow the Customer to draw a signature using mouse or touch input on the canvas.
3. THE Shop SHALL provide a "Clear" button to reset the signature canvas.
4. THE Shop SHALL prevent checkout submission until a signature has been drawn on the canvas for each applicable rental item.
5. WHEN the Customer submits checkout, THE Shop SHALL capture the signature canvas content as a base64 PNG text string.

### Requirement 15: Digital Signature — Storage and Display

**User Story:** As an admin, I want to view customer signatures associated with rental bookings, so that I have a record of agreement acceptance.

#### Acceptance Criteria

1. WHEN a rental checkout is completed, THE Shop SHALL store the base64 signature text in the database linked to the Booking record.
2. THE Admin_Panel SHALL display the stored signature as a rendered image on the Order detail page for rental Orders.
3. THE Portal SHALL display the stored signature on the Order detail page for the Customer who provided the signature.

### Requirement 16: Admin Manual Booking

**User Story:** As an admin, I want to create bookings directly without going through the portal checkout, so that I can handle phone orders and offline arrangements.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a manual booking creation form accessible from the Orders section.
2. THE Admin_Panel SHALL allow the admin to select a Customer, an equipment_rental Product, start date, end date, and quantity on the manual booking form.
3. THE Admin_Panel SHALL validate date availability and stock before creating the manual Booking using the same availability rules as portal checkout.
4. THE Admin_Panel SHALL provide an optional "paid offline" checkbox on the manual booking form.
5. WHEN "paid offline" is selected, THE Admin_Panel SHALL create the Order with payment_status "paid_offline" and skip Stripe payment processing.
6. WHEN "paid offline" is not selected, THE Admin_Panel SHALL create the Order with payment_status "pending" for later payment collection.
7. WHEN an admin creates a manual Booking, THE Admin_Panel SHALL create the Order, OrderItem, and Booking records within a single database transaction.

### Requirement 17: PDF Invoice/Receipt — Generation

**User Story:** As a system operator, I want PDF invoices to be automatically generated for paid orders, so that customers and admins have formal payment records.

#### Acceptance Criteria

1. WHEN an Order payment_status changes to "paid" or "paid_offline", THE Shop SHALL generate a PDF_Invoice using DomPDF.
2. THE PDF_Invoice SHALL contain the company name, company address, company logo, customer name, customer address, order date, line items with product name and price, VAT amount, total amount, and Stripe payment reference.
3. THE Shop SHALL store the generated PDF_Invoice file path on the Order record.
4. IF PDF generation fails, THEN THE Shop SHALL log the error and allow the Order to proceed without blocking fulfilment.

### Requirement 18: PDF Invoice/Receipt — Access and Distribution

**User Story:** As a customer and admin, I want to download PDF invoices and receive them by email, so that I have accessible records of transactions.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a download link for the PDF_Invoice on the Order detail page.
2. THE Portal SHALL provide a download link for the PDF_Invoice on the Customer's Order detail page.
3. WHEN a PDF_Invoice is generated, THE Notification_Queue SHALL attach the PDF to the order confirmation email sent to the Customer.
4. IF the Order does not have a generated PDF_Invoice, THEN THE Portal and Admin_Panel SHALL hide the download link for that Order.

### Requirement 19: Customer Dashboard Rental Display

**User Story:** As a customer, I want to see my active and upcoming equipment rentals on my dashboard, so that I can track my current bookings at a glance.

#### Acceptance Criteria

1. THE Portal dashboard SHALL display a rental summary section showing all active and upcoming Bookings for the authenticated Customer.
2. THE Portal dashboard SHALL display each Booking with the Product name, start date, end date, Booking status, and days remaining until end date.
3. WHEN a Booking is currently active (start date is today or past, end date is future), THE Portal dashboard SHALL display the Booking with an "Active" status indicator.
4. WHEN a Booking start date is in the future, THE Portal dashboard SHALL display the Booking with an "Upcoming" status indicator.
5. THE Portal dashboard SHALL provide a link from each Booking entry to the full Order detail page.
6. WHEN a Customer has no active or upcoming Bookings, THE Portal dashboard SHALL hide the rental summary section.

### Requirement 20: Delivery/Collection Instructions — Configuration

**User Story:** As an admin, I want to configure delivery or collection instructions per product, so that customers and staff know how physical items will be handled.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a Delivery_Instructions text field on the Product edit form for products with product_type `one_off` or `equipment_rental`.
2. THE Admin_Panel SHALL allow admins to enter and update plain-text delivery or collection instructions per Product.
3. WHEN a Product has product_type `hosting`, THE Admin_Panel SHALL hide the Delivery_Instructions field.

### Requirement 21: Delivery/Collection Instructions — Display

**User Story:** As a customer and admin, I want to see delivery instructions for physical orders, so that all parties understand the logistics.

#### Acceptance Criteria

1. WHEN an Order contains items with configured Delivery_Instructions, THE Notification_Queue SHALL include the instructions in the order confirmation email sent to the Customer.
2. WHEN an Order contains items with configured Delivery_Instructions, THE Notification_Queue SHALL include the instructions in the new order notification email sent to the admin.
3. THE Portal SHALL display Delivery_Instructions on the Order detail page for each applicable OrderItem.
4. THE Admin_Panel SHALL display Delivery_Instructions on the Order detail page for each applicable OrderItem.

### Requirement 22: Address Collection at Checkout

**User Story:** As a customer, I want to provide a delivery address at checkout for physical products, so that the business knows where to send or collect items.

#### Acceptance Criteria

1. WHEN a Customer proceeds to checkout with a Cart containing one_off or equipment_rental Products, THE Shop SHALL display an address collection form.
2. THE Shop SHALL pre-fill the address form with the Customer's stored address fields (address_line1, address_line2, city, state, postal_code, country).
3. THE Shop SHALL allow the Customer to override the pre-filled address with a different delivery address.
4. THE Shop SHALL require address_line1, city, postal_code, and country as mandatory address fields before allowing checkout submission.
5. WHEN checkout is completed, THE Shop SHALL store the submitted delivery address on the Order record.
6. WHEN a Cart contains only hosting Products, THE Shop SHALL skip the address collection form.

### Requirement 23: Dynamic Portal Navigation — Hide Empty Sections

**User Story:** As a customer, I want to see only relevant navigation items in my portal header, so that the interface is not cluttered with sections that have no content for me.

#### Acceptance Criteria

1. WHILE a Customer has no Service records, THE Portal header SHALL hide the "Services" navigation link.
2. WHILE a Customer has no Domain records, THE Portal header SHALL hide the "Domains" navigation link.
3. WHILE a Customer has no Invoice records, THE Portal header SHALL hide the "Invoices" navigation link.
4. THE Portal header SHALL always display the "Dashboard", "Support", "Shop", and "Help" navigation links regardless of Customer data.
5. WHEN a Customer gains a new Service, Domain, or Invoice record, THE Portal header SHALL display the corresponding navigation link on the next page load.
