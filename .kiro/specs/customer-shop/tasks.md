# Implementation Plan: Customer Shop

## Overview

Implement a self-service product catalog and checkout flow within the existing Laravel CRM. The feature adds admin product management, customer-facing browsing with visibility rules, session-based cart, Stripe-integrated checkout (one-off and recurring), and fulfilment workflows. Implementation follows the established Laravel patterns with Admin/Portal controller separation, Eloquent models, and Blade views.

## Tasks

- [ ] 1. Database migrations and model foundations
  - [-] 1.1 Create migrations for customer_tiers and customer_tier_assignments tables
    - Create migration with `customer_tiers` table (id, name, slug unique, timestamps)
    - Create migration with `customer_tier_assignments` pivot table (company_id FK to customers, customer_tier_id FK, unique compound index)
    - _Requirements: 2.5_

  - [-] 1.2 Create migration for products table
    - Define columns: name, description, product_type enum (equipment_rental, one_off, hosting), price decimal(10,2), billing_frequency nullable enum (monthly, quarterly, annually), stock_quantity nullable integer, image_path nullable, is_archived boolean default false
    - Add indexes on product_type and is_archived
    - _Requirements: 1.2, 1.3, 1.6_

  - [-] 1.3 Create migrations for product_visibilities, product_visibility_customers, and product_visibility_tiers tables
    - `product_visibilities`: product_id FK, visibility_type enum (all, customers, tiers) default 'all'
    - `product_visibility_customers`: product_visibility_id FK, company_id FK to customers
    - `product_visibility_tiers`: product_visibility_id FK, customer_tier_id FK
    - _Requirements: 2.1, 2.2, 2.3_

  - [-] 1.4 Create migrations for orders and order_items tables
    - `orders`: company_id FK, payment_status enum (pending, paid, failed), fulfilment_status enum (pending, awaiting_fulfilment, completed), stripe_checkout_session_id nullable unique, stripe_payment_intent_id nullable, total_amount decimal(10,2), admin_notes nullable text, fulfilled_at nullable timestamp
    - `order_items`: order_id FK, product_id nullable FK, service_id nullable FK to services, product_name, product_type, price decimal(10,2), billing_frequency nullable, stripe_subscription_id nullable
    - _Requirements: 5.1, 7.1, 8.1_

  - [~] 1.5 Create Eloquent models: Product, ProductVisibility, CustomerTier, Order, OrderItem
    - Define all fillable attributes, casts, and relationships as specified in the design
    - Add `scopeVisible(Builder $query, Customer $customer)` to Product model for visibility filtering
    - Add `scopeActive(Builder $query)` to Product model to exclude archived products
    - Add `isAvailable(): bool` method to Product model (checks stock and archive status)
    - Add `tiers()` BelongsToMany relationship on existing Customer model
    - _Requirements: 1.5, 2.2, 2.3, 2.4_

- [~] 2. Checkpoint - Run migrations and verify models
  - Ensure all migrations run without error, ask the user if questions arise.

- [ ] 3. Service layer implementation
  - [~] 3.1 Implement CartService
    - Session-based cart stored under key `shop_cart`
    - Methods: `getItems()`, `addItem(Product)`, `removeItem(int $index)`, `getTotal()`, `getOneOffItems()`, `getRecurringItems()`, `clear()`, `isEmpty()`
    - Validate product availability before adding (archived, stock)
    - _Requirements: 4.1, 4.2, 4.3_

  - [~] 3.2 Implement StripeService
    - Methods: `ensureCustomer(Customer)`, `createCheckoutSession(string $stripeCustomerId, array $lineItems, string $successUrl, string $cancelUrl)`, `createSubscription(string $stripeCustomerId, string $priceId, array $metadata)`, `verifyWebhookSignature(string $payload, string $sigHeader)`
    - Wrap Stripe PHP SDK calls with proper error handling
    - Use config values for Stripe keys and webhook secret
    - _Requirements: 4.7, 9.5_

  - [~] 3.3 Implement CheckoutService
    - Inject StripeService dependency
    - Method `processCheckout(Customer, array $cartItems)` returns CheckoutResult value object
    - Partition cart items into one-off and recurring groups
    - Process one-off items via `createCheckoutSession()`, recurring via `createSubscription()` per item
    - Create Order and OrderItems records linking to products
    - Handle mixed carts (one-off + recurring)
    - _Requirements: 4.4, 4.5, 4.6, 4.7, 4.8_

  - [~] 3.4 Implement FulfilmentService
    - Methods: `handleHostingPurchase(OrderItem, Customer)`, `handleEquipmentRentalPurchase(OrderItem, Customer)`, `handleOneOffPurchase(OrderItem)`, `fulfilOrder(Order)`, `decrementStock(Product)`
    - Hosting: create Service record with status "active", correct fields, set order fulfilment_status "completed"
    - Equipment rental: create Service record with status "pending", set order fulfilment_status "awaiting_fulfilment"
    - One-off: set order fulfilment_status "pending", decrement stock
    - Stock decrement uses atomic `decrement()` with `where('stock_quantity', '>', 0)` guard
    - _Requirements: 5.1, 5.4, 6.1, 6.2, 6.3, 7.1, 7.2, 7.5_

  - [~] 3.5 Create CheckoutResult value object
    - Properties: `?string $checkoutSessionUrl`, `?Order $order`, `array $subscriptionIds`, `bool $success`, `?string $errorMessage`
    - _Requirements: 4.4, 4.8_

  - [ ]* 3.6 Write unit tests for CartService
    - Test addItem, removeItem, getTotal, clear, isEmpty
    - Test that archived/out-of-stock products cannot be added
    - **Property 8: Cart Total Equals Sum of Item Prices**
    - **Validates: Requirements 4.2**

  - [ ]* 3.7 Write unit tests for CheckoutService item partitioning
    - **Property 9: Checkout Correctly Partitions Items by Payment Type**
    - **Validates: Requirements 4.4, 4.5, 4.6**

- [ ] 4. Admin controllers and views - Product management
  - [~] 4.1 Implement Admin\ShopProductController
    - `index`: list products with filter by type and archived status
    - `create`/`store`: product creation form with validation (name required, description required, product_type required, price required; billing_frequency required when type is equipment_rental or hosting)
    - `edit`/`update`: edit product including visibility rules and stock
    - `archive`/`restore`: soft-archive toggle without deleting orders
    - Handle image upload and storage
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [~] 4.2 Create admin product Blade views
    - `resources/views/admin/shop/products/index.blade.php` — product list with archive toggle, type filter
    - `resources/views/admin/shop/products/create.blade.php` — creation form with conditional billing_frequency field
    - `resources/views/admin/shop/products/edit.blade.php` — edit form including visibility rule configuration (all/customers/tiers selector, customer/tier pickers)
    - _Requirements: 1.1, 1.4, 2.1_

  - [ ]* 4.3 Write feature tests for product validation
    - **Property 1: Product Validation Rejects Incomplete Data**
    - **Property 2: Recurring Product Requires Billing Frequency**
    - **Validates: Requirements 1.2, 1.3**

- [ ] 5. Admin controllers and views - Customer tiers
  - [~] 5.1 Implement Admin\CustomerTierController
    - `index`: list tiers with assigned customer counts
    - `store`: create tier with name and auto-generated slug
    - `update`: edit tier name/slug
    - `destroy`: delete tier (cascade removes assignments)
    - Add tier assignment UI on customer edit or as part of the tiers index page
    - _Requirements: 2.5_

  - [~] 5.2 Create admin tier Blade view
    - `resources/views/admin/shop/tiers/index.blade.php` — tier CRUD list with inline create/edit forms, customer assignment management
    - _Requirements: 2.5_

- [ ] 6. Portal controllers and views - Shop browsing
  - [~] 6.1 Implement Portal\ShopController
    - `index`: display products using `Product::scopeVisible($customer)` with type filter and search (name/description LIKE query)
    - `show`: product detail page with full description, price, billing frequency
    - Enforce visibility rules — only show products the authenticated customer can access
    - Show "unavailable" badge for zero-stock products
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 1.7_

  - [~] 6.2 Create portal shop Blade views
    - `resources/views/portal/shop/index.blade.php` — product grid/list with search bar and type filter dropdown
    - `resources/views/portal/shop/show.blade.php` — product detail with "Add to Cart" button (disabled if unavailable)
    - _Requirements: 3.2, 3.6_

  - [ ]* 6.3 Write feature tests for product visibility
    - **Property 5: Visibility Rules Correctly Restrict Product Access**
    - **Property 3: Archived Products Are Hidden From Shop**
    - **Property 4: Zero-Stock Products Cannot Be Purchased**
    - **Validates: Requirements 2.2, 2.3, 2.4, 1.5, 1.7**

  - [ ]* 6.4 Write feature tests for shop filtering and search
    - **Property 6: Type Filter Returns Only Matching Products**
    - **Property 7: Search Returns Only Relevant Products**
    - **Validates: Requirements 3.3, 3.4**

- [ ] 7. Portal controllers and views - Cart and checkout
  - [~] 7.1 Implement Portal\CartController
    - `index`: display cart items from CartService with total
    - `add`: validate product availability and visibility, add to cart via CartService
    - `remove`: remove item by index from CartService
    - `checkout`: call CheckoutService, handle success (redirect to Stripe or success page) and failure (show error, retain cart)
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8_

  - [~] 7.2 Create portal cart Blade view
    - `resources/views/portal/shop/cart.blade.php` — cart items list with prices, remove buttons, total display, and checkout button
    - _Requirements: 4.2, 4.3_

  - [ ]* 7.3 Write feature tests for checkout flow
    - **Property 10: Stripe Customer Ensured Before Payment**
    - **Property 11: Failed Payment Preserves Cart**
    - **Validates: Requirements 4.7, 4.8**

- [~] 8. Checkpoint - Verify admin and portal flows work end-to-end
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Stripe webhook handling
  - [~] 9.1 Implement StripeWebhookController
    - `handle(Request)`: verify signature via StripeService, dispatch to private handlers based on event type
    - `handleCheckoutSessionCompleted`: find order by stripe_checkout_session_id, update payment_status to "paid", trigger fulfilment for one-off items
    - `handleInvoicePaymentFailed`: find service by stripe_subscription_id, update status to "payment_failed"
    - `handleSubscriptionDeleted`: find service by stripe_subscription_id, update status to "cancelled"
    - Log and return 200 for events referencing non-existent records
    - Exclude route from CSRF middleware
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ]* 9.2 Write feature tests for webhook handling
    - **Property 16: Webhooks With Invalid References Do Not Error**
    - **Property 17: Invalid Webhook Signatures Are Rejected**
    - **Property 12: Order Status Matches Product Type on Creation**
    - **Validates: Requirements 9.1, 9.4, 9.5, 5.1, 7.1**

- [ ] 10. Fulfilment workflows
  - [~] 10.1 Implement Admin\ShopOrderController
    - `index`: list all orders with filters (product type, fulfilment_status, date range, customer), display revenue summaries grouped by product type
    - `show`: order detail with customer name, product details, payment status, fulfilment status, Stripe references, admin notes
    - `fulfil`: mark order as fulfilled (update fulfilment_status to "completed", set fulfilled_at, activate associated service for equipment rentals)
    - `addNote`: append admin notes to order
    - _Requirements: 5.2, 5.3, 7.3, 7.4, 10.1, 10.2, 10.3, 10.4_

  - [~] 10.2 Create admin order Blade views
    - `resources/views/admin/shop/orders/index.blade.php` — orders dashboard with filters, revenue summary cards
    - `resources/views/admin/shop/orders/show.blade.php` — order detail with fulfilment action button and notes form
    - _Requirements: 10.1, 10.4_

  - [ ]* 10.3 Write feature tests for fulfilment
    - **Property 13: Stock Decrements by Exactly One Per Stockable Item Ordered**
    - **Property 14: Hosting Service Auto-Provisioned With Correct Fields**
    - **Property 15: Equipment Rental Creates Pending Service**
    - **Validates: Requirements 5.4, 6.1, 6.2, 7.2, 7.4**

- [ ] 11. Portal order history
  - [~] 11.1 Implement Portal\OrderController
    - `index`: list orders for authenticated customer with product name, date, price, type, fulfilment status
    - `show`: order detail with payment reference and admin notes
    - Scope queries to authenticated customer's company_id
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [~] 11.2 Create portal order Blade views
    - `resources/views/portal/orders/index.blade.php` — order history list
    - `resources/views/portal/orders/show.blade.php` — order detail with status badge and payment reference
    - _Requirements: 8.1, 8.2_

- [ ] 12. Routes and integration wiring
  - [~] 12.1 Register all routes in web.php
    - Portal shop routes under `portal` prefix with `auth`, `verified` middleware
    - Admin shop routes under `admin` prefix with `auth`, `verified`, `EnsureIsAdmin` middleware
    - Stripe webhook route excluded from CSRF verification
    - Use named routes matching the design specification
    - _Requirements: 3.1, 8.1, 9.5_

  - [~] 12.2 Register service providers and bind services
    - Register CartService, CheckoutService, FulfilmentService, StripeService in the service container (or use constructor injection via Laravel auto-resolution)
    - Add Stripe webhook secret and any new config values to `config/services.php`
    - _Requirements: 4.4, 9.5_

- [~] 13. Final checkpoint - Full integration verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design
- Unit tests validate specific examples and edge cases
- The Cart is session-based (no database table) as specified in the design
- Stock decrement uses atomic DB operations to prevent race conditions
- The Stripe webhook route must be excluded from CSRF middleware

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "1.4"] },
    { "id": 1, "tasks": ["1.5"] },
    { "id": 2, "tasks": ["3.1", "3.2", "3.5"] },
    { "id": 3, "tasks": ["3.3", "3.4", "3.6", "3.7"] },
    { "id": 4, "tasks": ["4.1", "5.1", "6.1", "12.1", "12.2"] },
    { "id": 5, "tasks": ["4.2", "4.3", "5.2", "6.2", "6.3", "6.4"] },
    { "id": 6, "tasks": ["7.1", "9.1"] },
    { "id": 7, "tasks": ["7.2", "7.3", "9.2", "10.1", "11.1"] },
    { "id": 8, "tasks": ["10.2", "10.3", "11.2"] }
  ]
}
```
