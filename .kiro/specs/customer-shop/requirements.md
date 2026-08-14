# Requirements Document

## Introduction

The Customer Shop enables customers to self-serve purchases and subscriptions directly from the customer portal dashboard. Customers can browse an admin-managed product catalog and purchase one-off items (via Stripe Checkout), subscribe to hosting packages (auto-provisioned), or rent equipment (admin-fulfilled). Admins control the catalog, pricing, and product visibility rules from the admin panel.

## Glossary

- **Shop**: The customer-facing storefront accessible within the portal at `/portal/shop`, displaying available products for browsing and purchasing.
- **Product**: A purchasable or rentable item in the catalog, belonging to one of three types: Equipment Rental, One-Off Purchase, or Hosting.
- **Product_Catalog**: The collection of all Products managed by administrators, including metadata such as pricing, descriptions, images, and visibility rules.
- **Cart**: A temporary collection of Products selected by a Customer before proceeding to checkout.
- **Order**: A record representing a Customer's completed purchase or subscription initiation, linking the Customer to one or more Products with payment and fulfilment status.
- **Visibility_Rule**: A per-product configuration that determines which Customers or customer tiers can view and purchase a given Product.
- **Customer_Tier**: A classification assigned to Customers used to control product visibility and access.
- **Admin_Panel**: The administrative interface at `/admin` used by administrators to manage the Product_Catalog, Orders, and fulfilment.
- **Portal**: The customer-facing interface at `/portal` where authenticated Customers access the Shop and manage their account.
- **Stripe_Checkout_Session**: A Stripe-hosted payment page used for processing one-off purchases.
- **Stripe_Subscription**: A recurring billing arrangement created via the Stripe API for hosting and equipment rental Products.
- **Fulfilment_Status**: The state of an Order's delivery, tracking whether physical or provisioning actions have been completed.

## Requirements

### Requirement 1: Product Catalog Management

**User Story:** As an admin, I want to create and manage products in a catalog, so that customers can browse and purchase them without admin intervention per transaction.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a product management interface for creating, editing, and archiving Products.
2. WHEN an admin creates a Product, THE Admin_Panel SHALL require a name, description, product type (Equipment Rental, One-Off Purchase, or Hosting), and price.
3. WHEN an admin creates a Product of type Equipment Rental or Hosting, THE Admin_Panel SHALL require a billing frequency (monthly, quarterly, or annually).
4. THE Admin_Panel SHALL allow admins to upload an image for each Product.
5. WHEN an admin archives a Product, THE Product_Catalog SHALL hide the Product from the Shop without deleting existing Orders linked to that Product.
6. THE Admin_Panel SHALL allow admins to set stock quantity for Products of type Equipment Rental and One-Off Purchase.
7. WHILE a Product has zero stock quantity, THE Shop SHALL display the Product as unavailable for purchase.

### Requirement 2: Product Visibility Control

**User Story:** As an admin, I want to control which customers can see specific products, so that I can offer tailored product selections to different customer segments.

#### Acceptance Criteria

1. THE Admin_Panel SHALL allow admins to assign a Visibility_Rule to each Product with options: all customers, specific customers, or specific Customer_Tiers.
2. WHEN a Visibility_Rule restricts a Product to specific customers, THE Shop SHALL display that Product only to those designated Customers.
3. WHEN a Visibility_Rule restricts a Product to a Customer_Tier, THE Shop SHALL display that Product only to Customers assigned to that tier.
4. WHEN no Visibility_Rule is configured for a Product, THE Shop SHALL display the Product to all authenticated Customers.
5. THE Admin_Panel SHALL allow admins to assign one or more Customer_Tiers to each Customer.

### Requirement 3: Shop Browsing

**User Story:** As a customer, I want to browse available products from my portal dashboard, so that I can find and select items to purchase or rent.

#### Acceptance Criteria

1. THE Portal SHALL provide a Shop page accessible at `/portal/shop` to authenticated Customers.
2. THE Shop SHALL display Products as a grid or list with name, image, description, price, and product type.
3. THE Shop SHALL allow Customers to filter Products by product type (Equipment Rental, One-Off Purchase, Hosting).
4. THE Shop SHALL allow Customers to search Products by name or description.
5. WHEN a Customer views the Shop, THE Shop SHALL display only Products that satisfy the Visibility_Rule for that Customer.
6. WHEN a Customer selects a Product, THE Shop SHALL display a detail page with full description, pricing, and billing frequency where applicable.

### Requirement 4: Cart and Checkout

**User Story:** As a customer, I want to add products to a cart and check out, so that I can purchase multiple items in a single transaction flow.

#### Acceptance Criteria

1. THE Shop SHALL allow Customers to add available Products to a Cart.
2. THE Cart SHALL display all selected Products with individual prices and a total amount.
3. THE Cart SHALL allow Customers to remove individual Products before checkout.
4. WHEN a Customer proceeds to checkout with one-off Products only, THE Shop SHALL create a Stripe_Checkout_Session for the total amount and redirect the Customer to Stripe.
5. WHEN a Customer proceeds to checkout with recurring Products (Hosting or Equipment Rental), THE Shop SHALL create a Stripe_Subscription for each recurring Product using the Customer's stripe_customer_id.
6. WHEN a Customer proceeds to checkout with a mix of one-off and recurring Products, THE Shop SHALL process one-off items via a Stripe_Checkout_Session and recurring items via individual Stripe_Subscriptions.
7. IF a Customer does not have a stripe_customer_id, THEN THE Shop SHALL create a Stripe customer record and store the stripe_customer_id on the Customer model before processing payment.
8. IF a Stripe payment or subscription creation fails, THEN THE Shop SHALL display the error message to the Customer and retain the Cart contents.

### Requirement 5: One-Off Purchase Fulfilment

**User Story:** As an admin, I want to track and fulfil one-off purchase orders, so that I can ensure customers receive their purchased items.

#### Acceptance Criteria

1. WHEN a Stripe_Checkout_Session completes successfully for a one-off Product, THE Shop SHALL create an Order with Fulfilment_Status set to "pending".
2. THE Admin_Panel SHALL display a list of Orders with Fulfilment_Status "pending" that require admin action.
3. WHEN an admin marks an Order as fulfilled, THE Admin_Panel SHALL update the Fulfilment_Status to "completed" and record the fulfilment timestamp.
4. WHEN an Order is created for a one-off Product, THE Shop SHALL decrement the stock quantity of that Product by one.

### Requirement 6: Hosting Auto-Provisioning

**User Story:** As a customer, I want hosting subscriptions to be activated automatically after payment, so that I can start using the service immediately.

#### Acceptance Criteria

1. WHEN a Stripe_Subscription is successfully created for a Hosting Product, THE Shop SHALL create a Service record with the service_type set to the Product name, status set to "active", and the stripe_subscription_id from the Stripe response.
2. WHEN a Hosting Service is auto-provisioned, THE Shop SHALL set the start_date to the current date, service_monthly_charge to the Product price, and service_payment_frequency to the selected billing frequency.
3. WHEN a Hosting Service is auto-provisioned, THE Shop SHALL create an Order with Fulfilment_Status set to "completed".
4. THE Portal SHALL display newly provisioned Hosting services in the Customer's existing services list.

### Requirement 7: Equipment Rental Fulfilment

**User Story:** As an admin, I want to review and fulfil equipment rental orders before they become active, so that I can verify stock availability and prepare the equipment.

#### Acceptance Criteria

1. WHEN a Stripe_Subscription is successfully created for an Equipment Rental Product, THE Shop SHALL create an Order with Fulfilment_Status set to "awaiting_fulfilment".
2. WHEN an Equipment Rental Order is created, THE Shop SHALL create a Service record with status set to "pending" and the stripe_subscription_id from the Stripe response.
3. THE Admin_Panel SHALL display Equipment Rental Orders with Fulfilment_Status "awaiting_fulfilment" in a dedicated fulfilment queue.
4. WHEN an admin confirms fulfilment of an Equipment Rental Order, THE Admin_Panel SHALL update the Fulfilment_Status to "completed" and the associated Service status to "active".
5. WHEN an Equipment Rental Order is created, THE Shop SHALL decrement the stock quantity of that Product by one.

### Requirement 8: Order History and Status Tracking

**User Story:** As a customer, I want to view my order history and current order status, so that I can track what I have purchased and its delivery progress.

#### Acceptance Criteria

1. THE Portal SHALL provide an Orders page accessible at `/portal/orders` displaying all Orders for the authenticated Customer.
2. THE Portal SHALL display each Order with the Product name, order date, price, product type, and current Fulfilment_Status.
3. WHEN a Fulfilment_Status changes, THE Portal SHALL reflect the updated status on the Customer's Orders page.
4. THE Portal SHALL allow Customers to view individual Order details including payment reference and any admin notes.

### Requirement 9: Stripe Webhook Handling

**User Story:** As a system operator, I want the application to respond to Stripe payment events, so that order and subscription statuses remain accurate.

#### Acceptance Criteria

1. WHEN a `checkout.session.completed` webhook event is received, THE Shop SHALL update the corresponding Order payment status to "paid".
2. WHEN an `invoice.payment_failed` webhook event is received for a subscription, THE Shop SHALL update the associated Service status to "payment_failed" and notify the Customer.
3. WHEN a `customer.subscription.deleted` webhook event is received, THE Shop SHALL update the associated Service status to "cancelled".
4. IF a webhook event references an Order or Service that does not exist, THEN THE Shop SHALL log the event and discard the request without error.
5. THE Shop SHALL verify the Stripe webhook signature on all incoming webhook requests to prevent unauthorized event processing.

### Requirement 10: Admin Order Overview

**User Story:** As an admin, I want a consolidated view of all shop orders, so that I can monitor sales activity and manage fulfilment efficiently.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide an Orders dashboard displaying all Orders across all Customers.
2. THE Admin_Panel SHALL allow admins to filter Orders by product type, Fulfilment_Status, date range, and Customer.
3. THE Admin_Panel SHALL display order totals and revenue summaries grouped by product type.
4. WHEN an admin views an Order, THE Admin_Panel SHALL display the Customer name, Product details, payment status, Fulfilment_Status, and associated Stripe references.
