# Design Document: Shop Enhancements

## Architecture Overview

The Shop Enhancements feature extends the existing Laravel MVC architecture with new service classes, models, controllers, scheduled commands, mail notifications, and Blade views. The design follows the established patterns: session-based carts via `CartService`, Stripe integration via `StripeService`, fulfilment logic in `FulfilmentService`, global settings via the `Setting` model, and the Admin/Portal controller split.

### High-Level Component Map

```
┌─────────────────────────────────────────────────────────────────────┐
│ Portal (Customer-facing)                                            │
│  ShopController  CartController  OrderController  DashboardController│
└──────────┬────────────┬──────────────┬───────────────┬──────────────┘
           │            │              │               │
┌──────────▼────────────▼──────────────▼───────────────▼──────────────┐
│ Service Layer                                                        │
│  CartService (enhanced)       BookingService (new)                   │
│  CheckoutService (enhanced)   WhmService (new)                       │
│  FulfilmentService (enhanced) NotificationService (new)              │
│  StripeService (enhanced)     PdfInvoiceService (new)                │
│  AvailabilityService (new)                                           │
└──────────┬────────────┬──────────────┬───────────────┬──────────────┘
           │            │              │               │
┌──────────▼────────────▼──────────────▼───────────────▼──────────────┐
│ Data Layer                                                           │
│  Booking  RentalAgreementAcceptance  ProcessedWebhookEvent           │
│  Product (columns added)  Order (columns added)  OrderItem (columns) │
│  Service (columns added)  Settings (WHM config rows)                 │
└─────────────────────────────────────────────────────────────────────┘
           │
┌──────────▼──────────────────────────────────────────────────────────┐
│ Background / Scheduled                                               │
│  Queued Mail Jobs   PurgeWebhookEvents Command                       │
│  RentalEndReminder Command   LowStockCheck (observer)                │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema Changes

### New Tables

#### `bookings`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| order_item_id | bigint FK → order_items.id | cascade delete |
| product_id | bigint FK → products.id | nullable on delete |
| company_id | bigint FK → customers.company_id | cascade delete |
| start_date | date | rental start |
| end_date | date | rental end |
| quantity | int | units booked, default 1 |
| total_price | decimal(10,2) | price × days × quantity |
| status | enum('confirmed','active','returned','cancelled') | default 'confirmed' |
| returned_at | timestamp nullable | admin marks return |
| signature_data | longText nullable | base64 PNG |
| agreement_accepted_at | timestamp nullable | acceptance timestamp |
| agreement_text_snapshot | longText nullable | copy of agreement at time of acceptance |
| created_at / updated_at | timestamps | |

Indexes: `(product_id, start_date, end_date)`, `(company_id, status)`, `(status)`

#### `processed_webhook_events`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| stripe_event_id | string(255) unique | Stripe event ID |
| event_type | string(100) | e.g. checkout.session.completed |
| processed_at | timestamp | when processed |
| created_at | timestamp | for purge scheduling |

Index: `(stripe_event_id)` unique, `(created_at)` for purge query

### Column Additions to Existing Tables

#### `products` — add columns

| Column | Type | Notes |
|--------|------|-------|
| min_rental_days | int nullable | minimum rental period |
| cooldown_days | int nullable default 0 | gap between bookings |
| rental_agreement_text | longText nullable | rich-text agreement content |
| delivery_instructions | text nullable | plain-text delivery/collection |
| low_stock_threshold | int nullable | alert trigger level |
| low_stock_notified | boolean default false | one-shot flag |

#### `orders` — add columns

| Column | Type | Notes |
|--------|------|-------|
| delivery_address_line1 | string nullable | |
| delivery_address_line2 | string nullable | |
| delivery_city | string nullable | |
| delivery_state | string nullable | |
| delivery_postal_code | string nullable | |
| delivery_country | string nullable | |
| invoice_pdf_path | string nullable | storage path to generated PDF |

Also modify `payment_status` enum to add `'paid_offline'`.

#### `order_items` — add columns

| Column | Type | Notes |
|--------|------|-------|
| domain_name | string nullable | for hosting items |
| quantity | int default 1 | for rental items |
| rental_start_date | date nullable | |
| rental_end_date | date nullable | |
| booking_id | bigint FK → bookings.id nullable | |

#### `services` — add columns

| Column | Type | Notes |
|--------|------|-------|
| domain_name | string nullable | customer-provided domain |
| cpanel_username | string nullable | set after WHM provisioning |

#### `settings` — WHM config rows (no schema change)

Keys stored via `Setting::set()`:
- `whm_hostname` (encrypted)
- `whm_api_token` (encrypted)
- `whm_default_package`
- `whm_nameserver_0` (default: ns0.thundercloud.uk)
- `whm_nameserver_1` (default: ns1.thundercloud.uk)

---

## Service Layer Architecture

### New Services

#### `BookingService`

```php
class BookingService
{
    /**
     * Check date availability for a product considering existing bookings,
     * stock_quantity, and cooldown periods.
     *
     * @return array{available: bool, booked_units: int, total_units: int}
     */
    public function checkAvailability(Product $product, Carbon $startDate, Carbon $endDate, int $quantity = 1): array;

    /**
     * Get unavailable dates for a product within a date range.
     * A date is unavailable when booked_units >= stock_quantity OR falls in cooldown.
     *
     * @return Collection<Carbon> dates that are fully booked or in cooldown
     */
    public function getUnavailableDates(Product $product, Carbon $rangeStart, Carbon $rangeEnd): Collection;

    /**
     * Create a booking with pessimistic locking.
     * Acquires FOR UPDATE lock on bookings for the product/date range,
     * re-checks availability, then inserts.
     *
     * @throws BookingConflictException if dates are no longer available
     */
    public function createBooking(OrderItem $item, Product $product, Carbon $startDate, Carbon $endDate, int $quantity, ?string $signatureData = null, ?string $agreementText = null): Booking;

    /**
     * Mark a booking as returned and record timestamp.
     */
    public function markReturned(Booking $booking): void;

    /**
     * Calculate total price: product.price × days × quantity.
     */
    public function calculateTotal(Product $product, Carbon $startDate, Carbon $endDate, int $quantity): float;
}
```

#### `WhmService`

```php
class WhmService
{
    /**
     * Test connectivity to the configured WHM server.
     *
     * @throws WhmConnectionException on failure
     */
    public function testConnection(string $hostname, string $apiToken): bool;

    /**
     * Create a hosting account on the WHM server.
     *
     * @return array{username: string} on success
     * @throws WhmProvisioningException on failure
     */
    public function createAccount(string $domain, string $package, string $contactEmail): array;
}
```

#### `NotificationService`

```php
class NotificationService
{
    /** Send new order notification to admin. Req 7.1 */
    public function notifyAdminNewOrder(Order $order): void;

    /** Send rental end reminder to admin. Req 7.2 */
    public function notifyAdminRentalEnded(Booking $booking): void;

    /** Send return confirmation to customer. Req 7.3 */
    public function notifyCustomerReturnConfirmed(Booking $booking): void;

    /** Send low-stock alert to admin (once per breach). Req 7.4 */
    public function notifyAdminLowStock(Product $product): void;

    /** Send payment failure to admin and customer. Req 8.1, 8.2 */
    public function notifyPaymentFailure(Order $order, string $failureReason): void;

    /** Send hosting provisioned email to customer. Req 3.6 */
    public function notifyCustomerHostingProvisioned(Service $service): void;

    /** Send order confirmation with optional PDF attachment. Req 18.3 */
    public function sendOrderConfirmation(Order $order): void;
}
```

#### `PdfInvoiceService`

```php
class PdfInvoiceService
{
    /**
     * Generate a PDF invoice for the given order and store at storage path.
     * Returns the relative storage path or null on failure.
     *
     * Uses DomPDF. Includes company name/address/logo, customer name/address,
     * order date, line items, VAT, total, Stripe reference.
     */
    public function generate(Order $order): ?string;
}
```

#### `AvailabilityService`

```php
class AvailabilityService
{
    /**
     * For a given product and date range, return the number of booked units per day.
     * Accounts for cooldown_days after each booking.
     *
     * @return array<string, int> date => booked_units
     */
    public function getBookedUnitsPerDay(Product $product, Carbon $rangeStart, Carbon $rangeEnd): array;

    /**
     * Determine if the requested quantity is available for every day in the range.
     */
    public function isAvailable(Product $product, Carbon $startDate, Carbon $endDate, int $quantity): bool;
}
```

### Modified Services

#### `CartService` — Enhanced

- Add support for `domain_name` field on hosting cart items.
- Add support for `rental_start_date`, `rental_end_date`, and `quantity` on rental cart items.
- New item structure includes optional keys: `domain_name`, `rental_start_date`, `rental_end_date`, `quantity`.

#### `CheckoutService` — Enhanced

- Add delivery address validation and storage on Order.
- Route rental items through `BookingService::createBooking()` inside a DB transaction.
- Route hosting items to create Service with `pending` status and store domain_name.
- Trigger `PdfInvoiceService::generate()` after payment confirmation.
- Trigger `NotificationService::sendOrderConfirmation()` after order creation.
- Handle rental agreement acceptance and signature data from checkout form.
- Skip address collection when cart contains only hosting items.

#### `FulfilmentService` — Enhanced

- Wrap fulfilment operations in DB transactions (Req 10.1).
- On hosting payment completion: create Service with `pending` status and `domain_name`.
- `fulfilOrder()` now also activates bookings (sets status to `active`).

#### `StripeService` — Enhanced

- Add `ensureCustomer()` resilience: catch "customer not found" → recreate → retry (Req 11.2).
- Add `createOneTimePayment()` method for rental booking payments.

---

## Controller Layer

### New Controllers

#### `Admin\WhmConfigController`

- `edit()` — Show WHM configuration form (Req 1.1)
- `update(Request)` — Validate, test connection, store encrypted settings (Req 1.2, 1.3, 1.4)

#### `Admin\HostingProvisionController`

- `index()` — List pending hosting services in provisioning queue (Req 3.2)
- `provision(Service)` — Approve and call WHM API (Req 3.3, 3.4, 3.5, 3.6)

#### `Admin\BookingController`

- `index()` — List all bookings with status filters
- `show(Booking)` — Booking detail with signature display
- `markReturned(Booking)` — Mark as returned, trigger notification (Req 7.3)
- `create()` — Manual booking form (Req 16.1, 16.2)
- `store(Request)` — Create manual booking with transaction (Req 16.3–16.7)

#### `Portal\BookingController`

- `availability(Product)` — JSON endpoint returning unavailable dates for calendar (Req 5.1–5.5)

### Modified Controllers

#### `Portal\ShopController` — Enhanced

- `show(Product)` — Add date picker for rental items, domain input for hosting (Req 2.1, 4.1)
- `addToCart(Request, Product)` — Validate domain format, rental dates, minimum period (Req 2.2, 4.3)

#### `Portal\CartController` — Enhanced

- `checkout()` — Show checkout form with: address collection (Req 22.1–22.4), rental agreement + signature (Req 13.1, 14.1–14.4), domain confirmation
- `processCheckout(Request)` — Handle full checkout flow including booking creation, address storage, agreement acceptance

#### `Portal\OrderController` — Enhanced

- `show(Order)` — Add rental agreement display, signature display, delivery instructions, PDF download link (Req 13.4, 15.3, 21.3, 18.2)
- `downloadInvoice(Order)` — Stream PDF file (Req 18.2)

#### `Portal\DashboardController` — Enhanced

- `index()` — Add rental summary section (active + upcoming bookings) (Req 19.1–19.6)

#### `Admin\ShopOrderController` — Enhanced

- `show(Order)` — Add signature display, delivery instructions, PDF download (Req 15.2, 21.4, 18.1)
- `downloadInvoice(Order)` — Stream PDF file (Req 18.1)

#### `Admin\ShopProductController` — Enhanced

- `edit/update(Product)` — Add rental agreement editor, delivery instructions field, min_rental_days, cooldown_days (Req 12.1, 12.2, 20.1, 20.2, 20.3)

#### `StripeWebhookController` — Enhanced

- Add idempotency check before processing (Req 9.1, 9.2)
- Wrap fulfilment in DB transaction (Req 10.1, 10.2, 10.3)
- Trigger notifications on payment events (Req 7.1, 8.1, 8.2)
- Trigger PDF generation on payment success (Req 17.1)

---

## Blade Views / UI Approach

### Portal Views

| View | Purpose | Key UI Elements |
|------|---------|-----------------|
| `portal.shop.show` (enhanced) | Product detail with rental dates | Flatpickr date picker, quantity input, domain name input |
| `portal.cart.checkout` (new) | Multi-step checkout | Address form (pre-filled), rental agreement panel, signature canvas (HTML5 Canvas + JS), payment confirmation |
| `portal.orders.show` (enhanced) | Order detail | Rental agreement accordion, signature image `<img src="data:image/png;base64,...">`, delivery instructions, PDF download button |
| `portal.dashboard` (enhanced) | Dashboard | Rental summary card with active/upcoming bookings table |
| `layouts.portal` (enhanced) | Portal layout | Dynamic nav hiding via `@if($customer->services()->exists())` etc. |

### Admin Views

| View | Purpose |
|------|---------|
| `admin.settings.whm` (new) | WHM configuration form |
| `admin.hosting.provision.index` (new) | Provisioning queue table |
| `admin.shop.bookings.index` (new) | Bookings list with filters |
| `admin.shop.bookings.show` (new) | Booking detail with signature |
| `admin.shop.bookings.create` (new) | Manual booking form with Flatpickr + FullCalendar |
| `admin.shop.orders.show` (enhanced) | Add signature, delivery instructions, PDF link |
| `admin.shop.products.edit` (enhanced) | Add rental agreement editor (Trix/TinyMCE), delivery instructions textarea, min_rental_days, cooldown_days inputs |

### Email Templates

| Template | Trigger |
|----------|---------|
| `emails.orders.confirmation` | Order paid — includes line items, delivery instructions, PDF attachment |
| `emails.orders.admin-new-order` | New order created |
| `emails.orders.payment-failed-admin` | Payment failure admin alert |
| `emails.orders.payment-failed-customer` | Payment failure customer notice |
| `emails.rentals.ended-admin` | Rental end date reached |
| `emails.rentals.return-confirmed` | Rental marked as returned |
| `emails.stock.low-stock-admin` | Stock below threshold |
| `emails.hosting.provisioned` | Hosting account created — includes nameservers, cPanel username |

### Frontend Libraries

- **Flatpickr** — Date range picker on product detail and manual booking form
- **FullCalendar** — Admin booking calendar overview (optional enhancement)
- **Signature Pad JS** (signature_pad npm package) — Canvas-based signature capture
- **DomPDF** (composer: barryvdh/laravel-dompdf) — Server-side PDF generation

---

## Scheduled Commands

### `app:purge-webhook-events`

- **Schedule**: Daily at 02:00
- **Action**: Delete `processed_webhook_events` records where `created_at < now() - 7 days` (Req 9.3)

### `app:notify-rental-ended`

- **Schedule**: Daily at 08:00
- **Action**: Find bookings with `end_date = today` and `status = 'active'`, dispatch `NotificationService::notifyAdminRentalEnded()` for each (Req 7.2)

### `app:reset-low-stock-flags`

- **Schedule**: Hourly
- **Action**: Find products where `low_stock_notified = true` AND `stock_quantity > low_stock_threshold`, reset `low_stock_notified = false` (Req 7.5)

---

## Model Observer / Event Approach

### `ProductObserver`

- **`updated` event**: If `stock_quantity` changed and now `<= low_stock_threshold` and `low_stock_notified == false`, dispatch low-stock notification and set `low_stock_notified = true` (Req 7.4, 7.5).

### `OrderObserver`

- **`updated` event**: If `payment_status` changed to `'paid'` or `'paid_offline'`, dispatch `PdfInvoiceService::generate()` as a queued job (Req 17.1).

---

## Webhook Idempotency Flow

```
Request arrives → Verify Stripe signature (Req 9.4)
  → Check processed_webhook_events for event_id
    → Found: return 200 immediately (Req 9.2)
    → Not found: insert event_id row
      → DB::transaction {
          Process event (create order, items, service/booking, decrement stock)
        } (Req 10.1)
      → On exception: rollback, delete event_id row (allow retry) (Req 10.2, 10.3)
      → On success: return 200
```

---

## Booking Concurrency Flow

```
Customer submits checkout with rental dates
  → DB::transaction {
      SELECT ... FROM bookings WHERE product_id = ? AND dates overlap FOR UPDATE (Pessimistic Lock)
      → Count booked units for each day in range
      → If any day has booked_units + requested_quantity > stock_quantity:
          THROW BookingConflictException (Req 6.2)
      → Create Stripe one-time payment (Req 6.3)
        → On Stripe failure: THROW, transaction rolls back (Req 6.5)
      → INSERT booking record (Req 6.4)
      → INSERT order + order_item records
    } (Req 6.1)
```

---

## Stripe Customer Sync Flow (Req 11)

```php
public function ensureCustomer(Customer $customer): string
{
    if ($customer->stripe_customer_id) {
        try {
            // Verify exists on Stripe side
            StripeCustomer::retrieve($customer->stripe_customer_id);
            return $customer->stripe_customer_id;
        } catch (InvalidRequestException $e) {
            if (str_contains($e->getMessage(), 'No such customer')) {
                // Recreate — fall through to creation below
                Log::warning('Stripe customer not found, recreating', [...]);
            } else {
                throw $e;
            }
        }
    }

    $stripeCustomer = StripeCustomer::create([
        'name' => $customer->company_name,
        'email' => $customer->users()->first()?->email,
        'metadata' => ['company_id' => $customer->company_id],
    ]);

    $customer->update(['stripe_customer_id' => $stripeCustomer->id]);
    return $stripeCustomer->id;
}
```

---

## Address Collection Logic (Req 22)

```php
// In CheckoutService or CartController
$cartHasPhysicalItems = collect($cartItems)->contains(
    fn ($item) => in_array($item['product_type'], ['one_off', 'equipment_rental'])
);

// If only hosting → skip address form
// If physical items present → show address form, pre-fill from Customer fields
// Validation: address_line1, city, postal_code, country required
// Store on Order record
```

---

## Dynamic Portal Navigation (Req 23)

Implemented in portal layout Blade via a View Composer or inline queries:

```php
// AppServiceProvider or a dedicated ViewComposer
View::composer('layouts.portal', function ($view) {
    $customer = auth()->user()->customer;
    $view->with([
        'showServices' => $customer->services()->exists(),
        'showDomains'  => $customer->domains()->exists(),
        'showInvoices' => $customer->invoices()->exists(),
    ]);
});
```

In the Blade template:
```blade
@if($showServices) <a href="...">Services</a> @endif
@if($showDomains) <a href="...">Domains</a> @endif
@if($showInvoices) <a href="...">Invoices</a> @endif
{{-- Always shown --}}
<a href="...">Dashboard</a>
<a href="...">Support</a>
<a href="...">Shop</a>
<a href="...">Help</a>
```

---

## Error Handling Strategy

| Scenario | Handling |
|----------|----------|
| WHM API failure | Display error to admin, retain Service in "pending" — no automatic retry |
| Stripe payment failure at checkout | Release pessimistic lock, show error to customer, no booking created |
| PDF generation failure | Log error, set `invoice_pdf_path = null`, fulfilment continues unblocked |
| Webhook processing failure | Transaction rollback, delete event_id from processed table, event will be retried by Stripe |
| Stock decrement below zero | RuntimeException thrown, caught within transaction, rolled back |
| Booking conflict (dates taken) | BookingConflictException → user-facing message, redirect back to product page |
| Stripe "customer not found" | Recreate Stripe customer record, retry original operation once |

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Prework Analysis

**Acceptance Criteria Testing Prework:**

4.2 THE Shop SHALL calculate total rental price as Product price × number of rental days.
  Thoughts: This is a pure calculation that should hold for all valid inputs. We can generate random prices, start dates, end dates, and quantities and verify the formula.
  Classification: PROPERTY
  Test Strategy: Generate random (price, startDate, endDate, quantity=1), verify total = price × days

4.6 WHEN quantity exceeds one, THE Shop SHALL calculate the total as price × days × quantity.
  Thoughts: Extension of 4.2 with quantity > 1. Same pure calculation property.
  Classification: PROPERTY
  Test Strategy: Generate random (price, startDate, endDate, quantity≥1), verify total = price × days × quantity

5.2 THE Shop SHALL determine availability based on existing confirmed Bookings and stock_quantity.
  Thoughts: For any product with N units and a set of bookings, the availability on any given date = N - booked_units_on_that_date. This is testable as a property with random booking configurations.
  Classification: PROPERTY
  Test Strategy: Generate random product (stock N), random confirmed bookings, verify availability calculation

5.3 WHILE all units are booked for a given date, THE Shop SHALL display that date as unavailable.
  Thoughts: This is the boundary condition of 5.2 — when booked_units == stock_quantity. Same property.
  Classification: PROPERTY (subsumed by 5.2 property)

5.4 THE Shop SHALL enforce a configurable cooldown period between consecutive Bookings.
  Thoughts: For any booking with cooldown_days > 0, dates within cooldown after end_date should be marked unavailable. Testable with random bookings and cooldown values.
  Classification: PROPERTY
  Test Strategy: Generate bookings with random cooldown_days, verify cooldown dates are marked unavailable

6.1 WHEN a Customer confirms a rental Booking at checkout, THE Shop SHALL acquire a Pessimistic_Lock.
  Thoughts: This is about concurrency behavior. Testing that two concurrent booking attempts for the last unit don't both succeed. This is testable as a property about the invariant: total booked units never exceed stock_quantity.
  Classification: PROPERTY
  Test Strategy: For any product with N units, after any number of confirmed bookings, booked_units_per_day ≤ N

6.2 IF the Pessimistic_Lock reveals dates are no longer available, THEN THE Shop SHALL reject the Booking.
  Thoughts: Edge case of 6.1. If we try to book when full, it must fail.
  Classification: EDGE_CASE (covered by 6.1 property)

9.1 WHEN a Webhook_Event is received, THE Shop SHALL store the event_id before executing the handler.
  Thoughts: This is about ordering and idempotency. The key property: processing the same event twice produces the same result as processing it once.
  Classification: PROPERTY
  Test Strategy: For any valid webhook event, processing it N times (N≥1) produces the same database state as processing it once.

9.2 IF a Webhook_Event with a previously stored event_id is received, THEN THE Shop SHALL return success without re-processing.
  Thoughts: This is the mechanism for 9.1. Same idempotency property.
  Classification: PROPERTY (subsumed by 9.1 property)

10.1 WHEN processing a successful payment event, THE Shop SHALL wrap all operations within a single database transaction.
  Thoughts: The property is: if any sub-operation fails, none of the operations persist. This is testable by simulating failures at different points and verifying no partial state exists.
  Classification: PROPERTY
  Test Strategy: For any simulated failure point in the fulfilment chain, verify no Order/OrderItem/Service/Booking records exist after failure.

11.2 IF a Stripe API call returns "customer not found", THE Shop SHALL create a new Stripe customer and retry.
  Thoughts: This is a resilience property. After the retry, the customer should have a valid stripe_customer_id. Testable with mocked Stripe responses.
  Classification: PROPERTY
  Test Strategy: For any customer with stale stripe_customer_id, after ensureCustomer(), the customer has a valid new ID.

17.2 THE PDF_Invoice SHALL contain company name, address, logo, customer details, line items, VAT, total, and Stripe reference.
  Thoughts: For any order, the generated PDF content should contain all required fields. We can generate random orders and verify the PDF text content includes all expected data.
  Classification: PROPERTY
  Test Strategy: For any valid Order, generated PDF text contains all required fields.

19.3 WHEN a Booking is currently active (start ≤ today, end > today), THE Portal dashboard SHALL display "Active" status.
  Thoughts: This is a classification function: given a booking's start/end dates and today's date, determine the correct status label. Pure function, universally testable.
  Classification: PROPERTY
  Test Strategy: For any (startDate, endDate, today) where start ≤ today < end, status = "Active"

19.4 WHEN a Booking start date is in the future, THE Portal dashboard SHALL display "Upcoming" status.
  Thoughts: Same classification function as 19.3 — just the other branch.
  Classification: PROPERTY (combined with 19.3)

22.4 THE Shop SHALL require address_line1, city, postal_code, and country as mandatory.
  Thoughts: Validation rule. For any address input missing any of these fields, validation must fail. Testable with random address objects.
  Classification: PROPERTY
  Test Strategy: For any address missing at least one required field, validation fails.

22.6 WHEN a Cart contains only hosting Products, THE Shop SHALL skip the address collection form.
  Thoughts: For any cart composed entirely of hosting items, address form should not be required. For any cart with at least one physical item, address form is required.
  Classification: PROPERTY
  Test Strategy: For any cart where all items have product_type='hosting', address form is skipped. For any cart with ≥1 non-hosting item, address form is shown.

23.1–23.3 Navigation visibility based on record existence.
  Thoughts: For any customer, the visibility of each nav link is determined by whether they have at least one record of that type. This is a pure function of customer data.
  Classification: PROPERTY
  Test Strategy: For any customer state (has/hasn't services, domains, invoices), nav visibility matches expected.

7.4 WHEN stock falls to or below threshold, send alert once.
  Thoughts: The "once" constraint is the interesting part. After notification is sent, subsequent stock decrements below threshold should NOT trigger another notification until reset.
  Classification: PROPERTY
  Test Strategy: For any sequence of stock changes, notification fires at most once per threshold breach.

7.5 THE Notification_Queue SHALL reset the low-stock notification flag when stock rises above threshold.
  Thoughts: Round-trip property: if stock drops below → notified, then rises above → flag reset, then drops below again → notified again.
  Classification: PROPERTY (combined with 7.4)

4.3 THE Shop SHALL enforce a configurable minimum rental period per Product.
  Thoughts: For any date selection where (endDate - startDate) < min_rental_days, the system must reject.
  Classification: PROPERTY
  Test Strategy: For any dates where days < min_rental_days, addition to cart fails.

2.2 THE Shop SHALL validate the domain name format before allowing checkout.
  Thoughts: Domain validation is a pure function. For any valid domain string format, validation passes. For any invalid format, it fails.
  Classification: PROPERTY
  Test Strategy: For any string matching valid domain regex, validation passes; for any non-matching string, it fails.

1.4 THE Admin_Panel SHALL store WHM API credentials in encrypted form.
  Thoughts: After saving WHM config, the raw value in the database should not equal the plaintext input. Testable property.
  Classification: PROPERTY
  Test Strategy: For any saved WHM credential, the stored database value ≠ plaintext input.

12.3 WHEN no rental agreement text is configured, THE Shop SHALL proceed without displaying an agreement.
  Thoughts: For any product with null/empty rental_agreement_text, checkout should not show agreement step.
  Classification: EXAMPLE

16.3 THE Admin_Panel SHALL validate date availability using the same rules as portal checkout.
  Thoughts: This is a consistency property. The availability check in admin manual booking must produce the same result as portal availability check for the same inputs.
  Classification: PROPERTY
  Test Strategy: For any (product, dates, quantity), admin and portal availability checks return the same result.

**Property Reflection:**

- Properties from 5.2 and 5.3 are redundant — 5.3 is the boundary of 5.2. Combine into one availability property.
- Properties from 9.1 and 9.2 are redundant — both describe idempotency. Keep as single property.
- Properties 19.3 and 19.4 are two branches of the same classification function. Combine into one.
- Properties 4.2 and 4.6 are the same formula (4.2 is just quantity=1 case). Combine into one.
- Properties 7.4 and 7.5 together describe the low-stock notification lifecycle. Combine.
- Property 6.1 subsumes 6.2 (the conflict case is the boundary of the invariant).
- Property 16.3 (admin uses same rules) is a consistency property that deserves its own entry.

### Property 1: Rental Price Calculation

*For any* valid product price > 0, any start date before end date yielding days > 0, and any quantity ≥ 1, the calculated total SHALL equal `price × days × quantity`.

**Validates: Requirements 4.2, 4.6**

### Property 2: Availability Invariant

*For any* equipment_rental product with `stock_quantity = N` and any set of confirmed bookings, the number of booked units on any single date SHALL NOT exceed N, and a date is reported as unavailable if and only if booked units on that date equal N.

**Validates: Requirements 5.2, 5.3, 6.1**

### Property 3: Cooldown Enforcement

*For any* product with `cooldown_days = C > 0` and any confirmed booking ending on date D, all dates from D+1 through D+C SHALL be marked as unavailable for new bookings of the same product.

**Validates: Requirements 5.4**

### Property 4: Minimum Rental Period Enforcement

*For any* product with `min_rental_days = M` and any customer date selection where `(end_date - start_date) < M days`, the system SHALL reject the booking request.

**Validates: Requirements 4.3, 4.4**

### Property 5: Webhook Idempotency

*For any* valid Stripe webhook event, processing the same event_id N times (N ≥ 1) SHALL produce the same database state as processing it exactly once, and all subsequent attempts SHALL return success without executing the handler.

**Validates: Requirements 9.1, 9.2**

### Property 6: Transactional Atomicity

*For any* fulfilment operation, if any sub-step (Order creation, OrderItem creation, stock decrement, Service creation, or Booking persistence) fails, then NO records from that transaction SHALL persist in the database.

**Validates: Requirements 10.1, 10.2, 10.3**

### Property 7: Stripe Customer Resilience

*For any* customer whose `stripe_customer_id` references a deleted Stripe record, calling `ensureCustomer()` SHALL result in a new valid `stripe_customer_id` stored on the customer record.

**Validates: Requirements 11.1, 11.2, 11.3**

### Property 8: Booking Status Classification

*For any* booking with start_date S and end_date E, given current date T: if `S ≤ T < E` the status SHALL be "Active"; if `T < S` the status SHALL be "Upcoming"; if `T ≥ E` the status SHALL be "Returned" or "Completed".

**Validates: Requirements 19.3, 19.4**

### Property 9: Address Form Visibility

*For any* cart where ALL items have `product_type = 'hosting'`, the checkout SHALL NOT display an address form. *For any* cart containing at least one item with `product_type ∈ {'one_off', 'equipment_rental'}`, the checkout SHALL display the address form.

**Validates: Requirements 22.1, 22.6**

### Property 10: Address Validation

*For any* address submission missing at least one of `{address_line1, city, postal_code, country}`, checkout validation SHALL reject the submission.

**Validates: Requirements 22.4**

### Property 11: Dynamic Navigation Visibility

*For any* customer, the "Services" nav link is visible if and only if the customer has ≥ 1 Service record; "Domains" is visible if and only if ≥ 1 Domain record; "Invoices" is visible if and only if ≥ 1 Invoice record. "Dashboard", "Support", "Shop", and "Help" SHALL always be visible.

**Validates: Requirements 23.1, 23.2, 23.3, 23.4, 23.5**

### Property 12: Low-Stock Notification Once-Per-Breach

*For any* product with a `low_stock_threshold`, the system SHALL send at most one notification per threshold breach. After stock rises above the threshold (resetting the flag) and drops again, exactly one new notification SHALL be sent.

**Validates: Requirements 7.4, 7.5**

### Property 13: Domain Name Validation

*For any* string input, the domain validation function SHALL accept the string if and only if it matches a valid domain name format (contains at least one dot, no spaces, only valid domain characters).

**Validates: Requirements 2.2**

### Property 14: WHM Credentials Encryption

*For any* WHM API token stored via the admin configuration form, the value persisted in the `settings` table SHALL NOT equal the plaintext input value.

**Validates: Requirements 1.4**

### Property 15: PDF Invoice Content Completeness

*For any* paid Order with associated customer, the generated PDF text content SHALL contain the company name, customer name, each line item's product name, and the total amount.

**Validates: Requirements 17.2**

### Property 16: Admin and Portal Availability Consistency

*For any* product, date range, and quantity, the availability check used by admin manual booking SHALL return the same result as the availability check used by portal checkout.

**Validates: Requirements 16.3**

---

## Dependencies

### Composer Packages (new)

- `barryvdh/laravel-dompdf` — PDF generation
- No additional packages needed; Stripe SDK already present

### NPM Packages (new)

- `flatpickr` — Date range picker
- `signature_pad` — Canvas signature capture
- `fullcalendar` (optional) — Admin booking calendar view

---

## Security Considerations

- WHM API token encrypted at rest via Laravel's `Crypt` facade before storing in settings
- Stripe webhook signature verified before any processing (existing pattern)
- Signature data (base64) stored as longText; served inline as data-URI to avoid file system exposure
- PDF files stored in `storage/app/invoices/` (not public); served via authenticated controller route
- Pessimistic locks prevent race conditions on booking creation
- Address data stored per-order (not overwriting customer master record unless explicitly requested)
