# Design Document: Customer Shop

## Architecture Overview

The Customer Shop feature extends the existing Laravel CRM with a self-service product catalog and checkout flow. It follows the established patterns: Admin controllers under `App\Http\Controllers\Admin`, Portal controllers under `App\Http\Controllers\Portal`, Blade views in corresponding directories, and Eloquent models in `App\Models`.

The architecture separates concerns into:
- **Catalog Management Layer** — Admin CRUD for products, visibility rules, and stock
- **Shop Presentation Layer** — Customer-facing browsing, filtering, and cart (session-based)
- **Checkout & Payment Layer** — Stripe integration for one-off and recurring payments
- **Fulfilment Layer** — Order creation, status tracking, and admin fulfilment workflows
- **Webhook Layer** — Stripe event handling for payment lifecycle updates

```
┌─────────────────────────────────────────────────────────────────┐
│                         Routes (web.php)                         │
├─────────────────────────────────────────────────────────────────┤
│  /admin/shop/*          │  /portal/shop/*    │  /stripe/webhook  │
│  EnsureIsAdmin          │  auth, verified    │  No auth (signed) │
├─────────────────────────┼────────────────────┼───────────────────┤
│  Admin\ShopProduct      │  Portal\Shop       │  Webhook          │
│  Controller             │  Controller        │  Controller       │
│  Admin\ShopOrder        │  Portal\Cart       │                   │
│  Controller             │  Controller        │                   │
│                         │  Portal\Order      │                   │
│                         │  Controller        │                   │
├─────────────────────────┴────────────────────┴───────────────────┤
│                        Service Layer                             │
│  CartService │ CheckoutService │ FulfilmentService │ StripeService│
├─────────────────────────────────────────────────────────────────┤
│                        Model Layer                               │
│  Product │ ProductVisibility │ CustomerTier │ Cart(session)      │
│  Order │ OrderItem                                               │
├─────────────────────────────────────────────────────────────────┤
│              Existing Models: Customer, Service, User            │
└─────────────────────────────────────────────────────────────────┘
```

## Components

### 1. Models

#### Product
New model representing a purchasable item in the catalog.

```php
// App\Models\Product
class Product extends Model
{
    protected $fillable = [
        'name', 'description', 'product_type', 'price',
        'billing_frequency', 'stock_quantity', 'image_path',
        'is_archived',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_archived' => 'boolean',
    ];

    public function visibilityRule(): HasOne;
    public function orderItems(): HasMany;
    public function isAvailable(): bool;
    public function scopeVisible(Builder $query, Customer $customer): Builder;
    public function scopeActive(Builder $query): Builder;
}
```

#### ProductVisibility
Stores the visibility configuration per product.

```php
// App\Models\ProductVisibility
class ProductVisibility extends Model
{
    protected $table = 'product_visibilities';

    protected $fillable = [
        'product_id', 'visibility_type', // 'all', 'customers', 'tiers'
    ];

    public function product(): BelongsTo;
    public function customers(): BelongsToMany; // pivot: product_visibility_customers
    public function tiers(): BelongsToMany;     // pivot: product_visibility_tiers
}
```

#### CustomerTier
Classification for customers to control product visibility.

```php
// App\Models\CustomerTier
class CustomerTier extends Model
{
    protected $fillable = ['name', 'slug'];

    public function customers(): BelongsToMany; // pivot: customer_tier_assignments
    public function visibilities(): BelongsToMany;
}
```

#### Order
Represents a completed purchase or subscription initiation.

```php
// App\Models\Order
class Order extends Model
{
    protected $fillable = [
        'company_id', 'payment_status', 'fulfilment_status',
        'stripe_checkout_session_id', 'stripe_payment_intent_id',
        'total_amount', 'admin_notes', 'fulfilled_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'fulfilled_at' => 'datetime',
    ];

    public function customer(): BelongsTo;
    public function items(): HasMany;
}
```

#### OrderItem
Line items within an order, linking to products and optionally to a service.

```php
// App\Models\OrderItem
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'service_id',
        'product_name', 'product_type', 'price',
        'billing_frequency', 'stripe_subscription_id',
    ];

    protected $casts = ['price' => 'decimal:2'];

    public function order(): BelongsTo;
    public function product(): BelongsTo;
    public function service(): BelongsTo;
}
```

### 2. Database Schema (New Migrations)

#### `create_customer_tiers_table`
```php
Schema::create('customer_tiers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->timestamps();
});

Schema::create('customer_tier_assignments', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('company_id');
    $table->foreignId('customer_tier_id')->constrained()->cascadeOnDelete();
    $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
    $table->unique(['company_id', 'customer_tier_id']);
    $table->timestamps();
});
```

#### `create_products_table`
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description');
    $table->enum('product_type', ['equipment_rental', 'one_off', 'hosting']);
    $table->decimal('price', 10, 2);
    $table->enum('billing_frequency', ['monthly', 'quarterly', 'annually'])->nullable();
    $table->integer('stock_quantity')->nullable(); // null = unlimited (hosting)
    $table->string('image_path')->nullable();
    $table->boolean('is_archived')->default(false);
    $table->timestamps();

    $table->index('product_type');
    $table->index('is_archived');
});
```

#### `create_product_visibilities_table`
```php
Schema::create('product_visibilities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->enum('visibility_type', ['all', 'customers', 'tiers'])->default('all');
    $table->timestamps();
});

Schema::create('product_visibility_customers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_visibility_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('company_id');
    $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
    $table->unique(['product_visibility_id', 'company_id']);
});

Schema::create('product_visibility_tiers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_visibility_id')->constrained()->cascadeOnDelete();
    $table->foreignId('customer_tier_id')->constrained()->cascadeOnDelete();
    $table->unique(['product_visibility_id', 'customer_tier_id']);
});
```

#### `create_orders_table`
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('company_id');
    $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
    $table->enum('fulfilment_status', ['pending', 'awaiting_fulfilment', 'completed'])->default('pending');
    $table->string('stripe_checkout_session_id')->nullable()->unique();
    $table->string('stripe_payment_intent_id')->nullable();
    $table->decimal('total_amount', 10, 2);
    $table->text('admin_notes')->nullable();
    $table->timestamp('fulfilled_at')->nullable();
    $table->timestamps();

    $table->foreign('company_id')->references('company_id')->on('customers')->cascadeOnDelete();
    $table->index(['company_id', 'fulfilment_status']);
    $table->index('fulfilment_status');
});

Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
    $table->unsignedBigInteger('service_id')->nullable();
    $table->string('product_name'); // snapshot at time of purchase
    $table->string('product_type');
    $table->decimal('price', 10, 2);
    $table->string('billing_frequency')->nullable();
    $table->string('stripe_subscription_id')->nullable();
    $table->timestamps();

    $table->foreign('service_id')->references('service_id')->on('services')->nullOnDelete();
});
```

### 3. Controllers

#### Admin\ShopProductController
Handles product CRUD, archiving, visibility rules, and stock management.

```php
class ShopProductController extends Controller
{
    public function index(Request $request): View;      // List all products with filters
    public function create(): View;                      // New product form
    public function store(Request $request): RedirectResponse;  // Create product
    public function edit(Product $product): View;        // Edit form
    public function update(Request $request, Product $product): RedirectResponse;
    public function archive(Product $product): RedirectResponse;  // Soft-archive
    public function restore(Product $product): RedirectResponse;  // Unarchive
}
```

#### Admin\ShopOrderController
Handles admin order management and fulfilment.

```php
class ShopOrderController extends Controller
{
    public function index(Request $request): View;      // All orders with filters
    public function show(Order $order): View;            // Order detail
    public function fulfil(Order $order): RedirectResponse; // Mark as fulfilled
    public function addNote(Request $request, Order $order): RedirectResponse;
}
```

#### Admin\CustomerTierController
Manages customer tiers and assignments.

```php
class CustomerTierController extends Controller
{
    public function index(): View;
    public function store(Request $request): RedirectResponse;
    public function update(Request $request, CustomerTier $tier): RedirectResponse;
    public function destroy(CustomerTier $tier): RedirectResponse;
}
```

#### Portal\ShopController
Customer-facing product browsing.

```php
class ShopController extends Controller
{
    public function index(Request $request): View;       // Shop grid with filters/search
    public function show(Product $product): View;        // Product detail page
}
```

#### Portal\CartController
Session-based cart management.

```php
class CartController extends Controller
{
    public function index(): View;                       // View cart
    public function add(Request $request, Product $product): RedirectResponse;
    public function remove(Request $request, int $index): RedirectResponse;
    public function checkout(Request $request): RedirectResponse; // Process payment
}
```

#### Portal\OrderController
Customer order history.

```php
class OrderController extends Controller
{
    public function index(Request $request): View;       // Order list
    public function show(Order $order): View;            // Order detail
}
```

#### StripeWebhookController
Handles incoming Stripe webhook events (no auth middleware, signature-verified).

```php
class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response;

    private function handleCheckoutSessionCompleted(array $payload): void;
    private function handleInvoicePaymentFailed(array $payload): void;
    private function handleSubscriptionDeleted(array $payload): void;
}
```

### 4. Service Classes

#### CartService
Manages the session-based cart.

```php
class CartService
{
    public function getItems(): array;
    public function addItem(Product $product): void;
    public function removeItem(int $index): void;
    public function getTotal(): float;
    public function getOneOffItems(): array;
    public function getRecurringItems(): array;
    public function clear(): void;
    public function isEmpty(): bool;
}
```

#### CheckoutService
Orchestrates the Stripe payment flow.

```php
class CheckoutService
{
    public function __construct(private StripeService $stripe);

    public function processCheckout(Customer $customer, array $cartItems): CheckoutResult;
    private function processOneOffItems(Customer $customer, array $items): ?string; // session_id
    private function processRecurringItems(Customer $customer, array $items): array; // subscription_ids
    private function createOrderFromCheckout(Customer $customer, array $items, ?string $sessionId): Order;
}
```

#### FulfilmentService
Handles post-payment fulfilment logic.

```php
class FulfilmentService
{
    public function handleHostingPurchase(OrderItem $item, Customer $customer): Service;
    public function handleEquipmentRentalPurchase(OrderItem $item, Customer $customer): Service;
    public function handleOneOffPurchase(OrderItem $item): void;
    public function fulfilOrder(Order $order): void;
    public function decrementStock(Product $product): void;
}
```

#### StripeService
Wraps Stripe API calls for testability.

```php
class StripeService
{
    public function ensureCustomer(Customer $customer): string; // returns stripe_customer_id
    public function createCheckoutSession(string $stripeCustomerId, array $lineItems, string $successUrl, string $cancelUrl): Session;
    public function createSubscription(string $stripeCustomerId, string $priceId, array $metadata): Subscription;
    public function verifyWebhookSignature(string $payload, string $sigHeader): Event;
}
```

### 5. Views (Blade Templates)

#### Admin Views
- `admin/shop/products/index.blade.php` — Product list with archive toggle
- `admin/shop/products/create.blade.php` — Product creation form
- `admin/shop/products/edit.blade.php` — Product edit form (includes visibility rules)
- `admin/shop/orders/index.blade.php` — Orders dashboard with filters and revenue summary
- `admin/shop/orders/show.blade.php` — Order detail with fulfilment actions
- `admin/shop/tiers/index.blade.php` — Customer tier management

#### Portal Views
- `portal/shop/index.blade.php` — Product grid with search/filter
- `portal/shop/show.blade.php` — Product detail page
- `portal/shop/cart.blade.php` — Cart view with checkout button
- `portal/orders/index.blade.php` — Order history list
- `portal/orders/show.blade.php` — Order detail

### 6. Routes

```php
// Portal Shop Routes
Route::prefix('portal')->middleware(['auth', 'verified'])->name('portal.')->group(function () {
    Route::get('/shop', [Portal\ShopController::class, 'index'])->name('shop.index');
    Route::get('/shop/{product}', [Portal\ShopController::class, 'show'])->name('shop.show');
    Route::get('/cart', [Portal\CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/{product}', [Portal\CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/{index}', [Portal\CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/checkout', [Portal\CartController::class, 'checkout'])->name('cart.checkout');
    Route::get('/orders', [Portal\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Portal\OrderController::class, 'show'])->name('orders.show');
});

// Admin Shop Routes
Route::prefix('admin')->middleware(['auth', 'verified', EnsureIsAdmin::class])->name('admin.')->group(function () {
    Route::resource('shop/products', Admin\ShopProductController::class)->except(['show', 'destroy']);
    Route::post('shop/products/{product}/archive', [Admin\ShopProductController::class, 'archive'])->name('shop.products.archive');
    Route::post('shop/products/{product}/restore', [Admin\ShopProductController::class, 'restore'])->name('shop.products.restore');
    Route::get('shop/orders', [Admin\ShopOrderController::class, 'index'])->name('shop.orders.index');
    Route::get('shop/orders/{order}', [Admin\ShopOrderController::class, 'show'])->name('shop.orders.show');
    Route::post('shop/orders/{order}/fulfil', [Admin\ShopOrderController::class, 'fulfil'])->name('shop.orders.fulfil');
    Route::post('shop/orders/{order}/note', [Admin\ShopOrderController::class, 'addNote'])->name('shop.orders.note');
    Route::resource('shop/tiers', Admin\CustomerTierController::class)->except(['show', 'edit', 'create']);
});

// Stripe Webhook (no auth, signature-verified)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

## Interfaces

### CheckoutResult (Value Object)

```php
class CheckoutResult
{
    public function __construct(
        public readonly ?string $checkoutSessionUrl,  // Redirect URL for one-off items
        public readonly ?Order $order,
        public readonly array $subscriptionIds,
        public readonly bool $success,
        public readonly ?string $errorMessage,
    ) {}
}
```

### Product Visibility Query Interface

The `Product::scopeVisible()` method encapsulates the visibility logic:

```php
public function scopeVisible(Builder $query, Customer $customer): Builder
{
    return $query->where('is_archived', false)->where(function ($q) use ($customer) {
        $q->whereDoesntHave('visibilityRule') // no rule = visible to all
          ->orWhereHas('visibilityRule', function ($vr) use ($customer) {
              $vr->where('visibility_type', 'all')
                 ->orWhere(function ($inner) use ($customer) {
                     $inner->where('visibility_type', 'customers')
                           ->whereHas('customers', fn ($c) => $c->where('company_id', $customer->company_id));
                 })
                 ->orWhere(function ($inner) use ($customer) {
                     $inner->where('visibility_type', 'tiers')
                           ->whereHas('tiers', fn ($t) => $t->whereIn(
                               'customer_tier_id',
                               $customer->tiers()->pluck('customer_tiers.id')
                           ));
                 });
          });
    });
}
```

### Cart Session Structure

```php
// Session key: 'shop_cart'
// Structure: array of cart items
[
    [
        'product_id' => int,
        'name' => string,
        'price' => float,
        'product_type' => string,
        'billing_frequency' => ?string,
    ],
    // ...
]
```

## Data Models (ERD)

```
┌───────────────┐       ┌────────────────────────┐
│ customer_tiers│       │ customer_tier_          │
│───────────────│       │ assignments             │
│ id            │◄──────│────────────────────────│
│ name          │       │ company_id (FK)         │
│ slug          │       │ customer_tier_id (FK)   │
└───────────────┘       └────────────────────────┘
                                    │
                                    ▼
┌───────────────┐       ┌───────────────────────┐       ┌───────────────┐
│ customers     │       │ product_visibilities   │       │ products      │
│───────────────│       │───────────────────────│       │───────────────│
│ company_id PK │◄──┐   │ id                    │──────►│ id            │
│ ...           │   │   │ product_id (FK)       │       │ name          │
│ stripe_cust_id│   │   │ visibility_type       │       │ description   │
└───────────────┘   │   └───────────────────────┘       │ product_type  │
        │           │              │                     │ price         │
        │           │   ┌──────────┴──────────┐         │ billing_freq  │
        ▼           │   ▼                     ▼         │ stock_quantity│
┌───────────────┐   │ product_visibility   product_     │ image_path    │
│ orders        │   │ _customers           visibility   │ is_archived   │
│───────────────│   │                      _tiers       └───────────────┘
│ id            │   │                                           │
│ company_id FK │───┘                                           │
│ payment_status│                                               │
│ fulfilment_   │       ┌───────────────────────┐              │
│   status      │       │ order_items           │              │
│ stripe_*      │◄──────│───────────────────────│──────────────┘
│ total_amount  │       │ id                    │
│ admin_notes   │       │ order_id (FK)         │
│ fulfilled_at  │       │ product_id (FK)       │
└───────────────┘       │ service_id (FK)       │──────► services
                        │ product_name          │
                        │ product_type          │
                        │ price                 │
                        │ billing_frequency     │
                        │ stripe_subscription_id│
                        └───────────────────────┘
```

## Error Handling

### Payment Errors
- Stripe API failures during checkout are caught and surfaced to the customer with the Stripe error message
- Cart contents are preserved in session when payment fails
- Failed checkout attempts are logged to the `event_log` table

### Webhook Errors
- Invalid signatures return 400 with no processing
- Events referencing non-existent orders/services are logged and return 200 (to prevent Stripe retries)
- Idempotent handling: duplicate events are detected via `stripe_checkout_session_id` uniqueness

### Stock Errors
- Race conditions on stock decrement are handled with atomic database operations (`decrement()` with a `where('stock_quantity', '>', 0)` guard)
- If stock reaches zero during checkout, the customer is informed and the item is removed from cart

### Visibility Edge Cases
- Archived products are excluded from all shop queries regardless of visibility rules
- If a customer's tier is removed after adding a product to cart, the product is validated again at checkout time

## Checkout Flow Sequence

```
Customer                Portal              CheckoutService         Stripe
   │                      │                       │                   │
   │── POST /cart/checkout─►│                       │                   │
   │                      │── processCheckout() ──►│                   │
   │                      │                       │── ensureCustomer()─►│
   │                      │                       │◄── customer_id ────│
   │                      │                       │                   │
   │                      │                       │── createCheckout() ►│ (one-off)
   │                      │                       │◄── session_url ────│
   │                      │                       │                   │
   │                      │                       │── createSubscription()►│ (recurring)
   │                      │                       │◄── subscription_id─│
   │                      │                       │                   │
   │                      │◄── CheckoutResult ────│                   │
   │◄── redirect to Stripe─│                       │                   │
   │                      │                       │                   │
   │                      │ (webhook later)       │                   │
   │                      │◄── checkout.session.completed ────────────│
   │                      │── update order paid ──►│                   │
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

---

**Acceptance Criteria Testing Prework:**

1.1 THE Admin_Panel SHALL provide a product management interface for creating, editing, and archiving Products.
  Thoughts: This is a UI/CRUD provision requirement — it just says the interface exists. Not a computable property.
  Classification: SMOKE
  Test Strategy: Single test to verify routes and views exist.

1.2 WHEN an admin creates a Product, THE Admin_Panel SHALL require a name, description, product type, and price.
  Thoughts: This is about validation rules. We can generate random product data and verify that missing required fields are always rejected. This is a universal property — for ALL product submissions lacking required fields, validation must fail.
  Classification: PROPERTY
  Test Strategy: Generate product data with randomly missing required fields and verify rejection.

1.3 WHEN an admin creates a Product of type Equipment Rental or Hosting, THE Admin_Panel SHALL require a billing frequency.
  Thoughts: Conditional validation. For all products of type equipment_rental or hosting, billing_frequency must be present. We can test this universally.
  Classification: PROPERTY
  Test Strategy: Generate products of recurring types without billing_frequency and verify rejection.

1.4 THE Admin_Panel SHALL allow admins to upload an image for each Product.
  Thoughts: UI capability. Not a computable property.
  Classification: EXAMPLE
  Test Strategy: Single test uploading an image and verifying storage.

1.5 WHEN an admin archives a Product, THE Product_Catalog SHALL hide the Product from the Shop without deleting existing Orders linked to that Product.
  Thoughts: This is a universal invariant. For ALL archived products, they must not appear in shop queries AND existing orders must remain intact.
  Classification: PROPERTY
  Test Strategy: Generate products with orders, archive them, verify shop exclusion and order preservation.

1.6 THE Admin_Panel SHALL allow admins to set stock quantity for Products of type Equipment Rental and One-Off Purchase.
  Thoughts: UI capability. Not a universal property.
  Classification: EXAMPLE
  Test Strategy: Example test setting stock.

1.7 WHILE a Product has zero stock quantity, THE Shop SHALL display the Product as unavailable for purchase.
  Thoughts: This is a universal invariant — for ALL products with zero stock, they must be shown as unavailable. Testable as property.
  Classification: PROPERTY
  Test Strategy: Generate products, set stock to zero, verify they cannot be added to cart.

2.1 THE Admin_Panel SHALL allow admins to assign a Visibility_Rule to each Product.
  Thoughts: UI capability. Not a universal property.
  Classification: EXAMPLE

2.2 WHEN a Visibility_Rule restricts a Product to specific customers, THE Shop SHALL display that Product only to those designated Customers.
  Thoughts: This is a core visibility invariant. For ALL customer-restricted products, non-designated customers must never see them. Universal.
  Classification: PROPERTY
  Test Strategy: Generate products with customer visibility, query as non-designated customers, verify exclusion.

2.3 WHEN a Visibility_Rule restricts a Product to a Customer_Tier, THE Shop SHALL display that Product only to Customers assigned to that tier.
  Thoughts: Same as above for tier-based. Universal.
  Classification: PROPERTY
  Test Strategy: Generate tier-restricted products, query as customers in/out of tier, verify correct visibility.

2.4 WHEN no Visibility_Rule is configured for a Product, THE Shop SHALL display the Product to all authenticated Customers.
  Thoughts: Default visibility — for ALL products without rules, ALL customers should see them. Universal.
  Classification: PROPERTY
  Test Strategy: Generate products with no visibility rules, verify all customers see them.

2.5 THE Admin_Panel SHALL allow admins to assign one or more Customer_Tiers to each Customer.
  Thoughts: UI capability.
  Classification: EXAMPLE

3.1-3.6: Shop browsing requirements are mostly UI/routing concerns. The filtering/search (3.3, 3.4) and visibility enforcement (3.5) are testable:

3.3 THE Shop SHALL allow Customers to filter Products by product type.
  Thoughts: For all filter queries, returned products must match the requested type. Universal.
  Classification: PROPERTY
  Test Strategy: Generate mixed-type products, filter by each type, verify results match.

3.4 THE Shop SHALL allow Customers to search Products by name or description.
  Thoughts: For all search queries, returned products must contain the search term in name or description. Universal.
  Classification: PROPERTY
  Test Strategy: Generate products, search by substring, verify all results contain the term.

3.5 WHEN a Customer views the Shop, THE Shop SHALL display only Products that satisfy the Visibility_Rule for that Customer.
  Thoughts: This is the same visibility invariant as 2.2/2.3/2.4 combined. Already covered.
  Classification: PROPERTY (covered by visibility properties)

4.1-4.3: Cart operations.

4.2 THE Cart SHALL display all selected Products with individual prices and a total amount.
  Thoughts: For all carts, the displayed total must equal the sum of item prices. Universal arithmetic invariant.
  Classification: PROPERTY
  Test Strategy: Generate random cart contents, verify total equals sum of prices.

4.4-4.6: Checkout routing based on product type.

4.6 WHEN a Customer proceeds to checkout with a mix of one-off and recurring Products, THE Shop SHALL process one-off items via Stripe_Checkout_Session and recurring items via individual Stripe_Subscriptions.
  Thoughts: For all carts containing both types, the checkout service must correctly partition items. Universal.
  Classification: PROPERTY
  Test Strategy: Generate mixed carts, verify one-off items go to checkout session and recurring to subscriptions.

4.7 IF a Customer does not have a stripe_customer_id, THEN THE Shop SHALL create a Stripe customer record and store the stripe_customer_id on the Customer model.
  Thoughts: Idempotent customer creation. For all customers without a stripe ID, after checkout the customer must have one. If they already have one, it must remain unchanged.
  Classification: PROPERTY
  Test Strategy: Generate customers with/without stripe_customer_id, process checkout, verify ID existence and idempotence.

4.8 IF a Stripe payment fails, THEN THE Shop SHALL retain the Cart contents.
  Thoughts: For all failed payments, the cart must not be cleared. Universal.
  Classification: PROPERTY
  Test Strategy: Simulate failures, verify cart persists.

5.1 WHEN a Stripe_Checkout_Session completes for a one-off Product, THE Shop SHALL create an Order with Fulfilment_Status "pending".
  Thoughts: For all completed one-off checkouts, the resulting order must have pending status. Universal.
  Classification: PROPERTY
  Test Strategy: Simulate completed checkout events, verify order status.

5.4 WHEN an Order is created for a one-off Product, THE Shop SHALL decrement the stock quantity of that Product by one.
  Thoughts: Stock decrement invariant. For all one-off purchases, stock must decrease by exactly one per item ordered. Universal.
  Classification: PROPERTY
  Test Strategy: Track stock before/after order creation, verify decrement.

6.1-6.3: Hosting auto-provisioning.

6.1 WHEN a Stripe_Subscription is created for a Hosting Product, THE Shop SHALL create a Service record with service_type=Product name, status="active", and stripe_subscription_id.
  Thoughts: For all hosting purchases, the created service must have correct fields. Universal.
  Classification: PROPERTY
  Test Strategy: Generate hosting products, simulate subscription creation, verify service fields.

7.1-7.2: Equipment rental provisioning.

7.1 WHEN a Stripe_Subscription is created for an Equipment Rental Product, THE Shop SHALL create an Order with Fulfilment_Status "awaiting_fulfilment".
  Thoughts: For all equipment rental purchases, order must have awaiting_fulfilment status. Universal.
  Classification: PROPERTY
  Test Strategy: Simulate equipment rental subscription, verify order status.

7.4 WHEN an admin confirms fulfilment of an Equipment Rental Order, THE Admin_Panel SHALL update Fulfilment_Status to "completed" and Service status to "active".
  Thoughts: For all fulfilment confirmations, both order and service states must update atomically. Universal.
  Classification: PROPERTY
  Test Strategy: Generate pending equipment orders, fulfil, verify both statuses.

9.1-9.5: Webhook handling.

9.4 IF a webhook references a non-existent Order/Service, THE Shop SHALL log and discard without error.
  Thoughts: For all webhook events with invalid references, the system must not throw. Universal.
  Classification: PROPERTY
  Test Strategy: Generate webhook payloads with random non-existent IDs, verify graceful handling.

9.5 THE Shop SHALL verify the Stripe webhook signature on all incoming webhook requests.
  Thoughts: For all requests with invalid signatures, they must be rejected. Universal.
  Classification: PROPERTY
  Test Strategy: Generate requests with tampered signatures, verify 400 response.

---

**Property Reflection:**

Reviewing all identified properties for redundancy:
- 2.2, 2.3, 2.4, 3.5 all test visibility rules — these can be combined into a single comprehensive visibility property that covers all visibility_type variants.
- 4.4, 4.5, 4.6 all test checkout routing — 4.6 subsumes 4.4 and 4.5 since a mixed cart covers the pure cases as edge cases.
- 5.4 and 7.5 both test stock decrement — combinable into one property for all stockable products.
- 5.1 and 7.1 test order status on creation — different expected statuses based on type, combinable into one type-aware property.
- 6.1 and 7.2 test service creation after subscription — different expected statuses, combinable into one property.

Final consolidated property set:

---

### Property 1: Product Validation Rejects Incomplete Data

*For any* product creation request missing one or more required fields (name, description, product_type, price), the system SHALL reject the request with a validation error, and no Product record SHALL be created.

**Validates: Requirements 1.2**

### Property 2: Recurring Product Requires Billing Frequency

*For any* product creation request with product_type of "equipment_rental" or "hosting" that does not include a billing_frequency, the system SHALL reject the request with a validation error.

**Validates: Requirements 1.3**

### Property 3: Archived Products Are Hidden From Shop

*For any* archived Product, querying the Shop (regardless of customer or visibility rule) SHALL never include that Product in results, AND all existing Orders referencing that Product SHALL remain intact and unmodified.

**Validates: Requirements 1.5**

### Property 4: Zero-Stock Products Cannot Be Purchased

*For any* Product with stock_quantity equal to zero, the system SHALL prevent that Product from being added to a Cart or processed through checkout.

**Validates: Requirements 1.7**

### Property 5: Visibility Rules Correctly Restrict Product Access

*For any* Product with a visibility_type of "customers", only designated Customers SHALL see the Product. *For any* Product with visibility_type "tiers", only Customers assigned to one of the designated tiers SHALL see it. *For any* Product with visibility_type "all" or no visibility rule, all authenticated Customers SHALL see it.

**Validates: Requirements 2.2, 2.3, 2.4, 3.5**

### Property 6: Type Filter Returns Only Matching Products

*For any* product type filter applied to the Shop, all returned Products SHALL have a product_type matching the filter value, and no Products of other types SHALL appear in results.

**Validates: Requirements 3.3**

### Property 7: Search Returns Only Relevant Products

*For any* search query applied to the Shop, all returned Products SHALL contain the search term (case-insensitive) in either their name or description.

**Validates: Requirements 3.4**

### Property 8: Cart Total Equals Sum of Item Prices

*For any* Cart containing one or more items, the displayed total amount SHALL equal the arithmetic sum of all individual item prices.

**Validates: Requirements 4.2**

### Property 9: Checkout Correctly Partitions Items by Payment Type

*For any* Cart containing a mix of product types, one-off items SHALL be grouped into a single Stripe Checkout Session, and each recurring item (hosting or equipment_rental) SHALL result in an individual Stripe Subscription. No one-off item SHALL create a subscription, and no recurring item SHALL be included in the checkout session.

**Validates: Requirements 4.4, 4.5, 4.6**

### Property 10: Stripe Customer Ensured Before Payment

*For any* Customer proceeding to checkout, if the Customer lacks a stripe_customer_id, one SHALL be created and stored before payment processing. If the Customer already has a stripe_customer_id, it SHALL remain unchanged (idempotent).

**Validates: Requirements 4.7**

### Property 11: Failed Payment Preserves Cart

*For any* checkout attempt that results in a Stripe error, the Cart contents SHALL remain intact and unchanged in the session.

**Validates: Requirements 4.8**

### Property 12: Order Status Matches Product Type on Creation

*For any* completed payment for a one-off Product, the resulting Order SHALL have fulfilment_status "pending". *For any* subscription created for an Equipment Rental Product, the resulting Order SHALL have fulfilment_status "awaiting_fulfilment". *For any* subscription created for a Hosting Product, the resulting Order SHALL have fulfilment_status "completed".

**Validates: Requirements 5.1, 6.3, 7.1**

### Property 13: Stock Decrements by Exactly One Per Stockable Item Ordered

*For any* Order created containing a stockable Product (one_off or equipment_rental), the Product's stock_quantity SHALL decrease by exactly one per OrderItem referencing that Product.

**Validates: Requirements 5.4, 7.5**

### Property 14: Hosting Service Auto-Provisioned With Correct Fields

*For any* Hosting Product subscription successfully created, the resulting Service record SHALL have service_type equal to the Product name, status "active", stripe_subscription_id matching the Stripe response, start_date equal to the current date, service_monthly_charge equal to the Product price, and service_payment_frequency equal to the selected billing frequency.

**Validates: Requirements 6.1, 6.2**

### Property 15: Equipment Rental Creates Pending Service

*For any* Equipment Rental Product subscription successfully created, the resulting Service record SHALL have status "pending" and the stripe_subscription_id from the Stripe response. After admin fulfilment, the Service status SHALL change to "active" and the Order fulfilment_status SHALL change to "completed".

**Validates: Requirements 7.2, 7.4**

### Property 16: Webhooks With Invalid References Do Not Error

*For any* incoming Stripe webhook event that references an Order ID or subscription ID not present in the database, the system SHALL return HTTP 200, log the event, and make no data modifications.

**Validates: Requirements 9.4**

### Property 17: Invalid Webhook Signatures Are Rejected

*For any* incoming request to the webhook endpoint with an invalid or missing Stripe signature header, the system SHALL return HTTP 400 and perform no data processing.

**Validates: Requirements 9.5**
