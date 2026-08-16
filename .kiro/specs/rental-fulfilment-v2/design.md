# Design: Rental Fulfilment v2

## Overview

This design extends the existing shop platform with three capability groups:
1. Quantity selection for one-off purchases (portal-side)
2. A full fulfilment lifecycle for rental bookings with asset tracking and photographic inspections (admin-side)
3. A dedicated customer shop/rental history page in the admin panel

All changes follow the existing Laravel patterns: Blade views, service classes, Eloquent models, local file storage via `Storage::disk('local')`.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│ Portal (Customer-Facing)                                         │
│  ShopController ─► CartService ─► CheckoutService ─► StripeService│
│  (+ quantity for one-off)                                        │
└─────────────────────────────────────────────────────────────────┘
         │ creates Order + Booking
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Admin Fulfilment Pipeline                                        │
│                                                                  │
│  FulfilmentQueueController                                       │
│    ├─ index()         → grouped by stage                         │
│    ├─ assignAssets()  → packing stage                            │
│    ├─ markReady()     → ready stage                              │
│    ├─ checkOut()      → checkout inspection + photos             │
│    ├─ markReturned()  → return stage                             │
│    └─ inspect()       → return inspection + photos               │
│                                                                  │
│  BookingInspectionService                                        │
│    ├─ createCheckoutInspection(booking, photos, notes, adminId) │
│    └─ createReturnInspection(booking, photos, notes, adminId)   │
│                                                                  │
│  AssetAssignmentService                                          │
│    ├─ assignAssets(booking, assetIds[])                          │
│    ├─ releaseAssets(booking)                                     │
│    └─ markDamaged(booking, assetIds[], notes)                    │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Admin Customer Shop Page                                         │
│  CustomerShopController                                          │
│    ├─ index(customer) → KPIs + orders + bookings + documents    │
│    └─ downloadDocument(customer, type, id)                       │
└─────────────────────────────────────────────────────────────────┘
```

## Database Schema Changes

### Migration 1: Add `product_id` to `cmdb` table

```sql
ALTER TABLE cmdb ADD COLUMN product_id BIGINT UNSIGNED NULL;
ALTER TABLE cmdb ADD CONSTRAINT fk_cmdb_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;
ALTER TABLE cmdb ADD INDEX idx_cmdb_product_id (product_id);
```

Update `asset_status` to support new values:
- Existing: `Active`, `Decommissioned`, `In Repair`
- New: `Available`, `Rented Out`, `Reserved`

**Note:** We'll keep the column as a string (not enum) for flexibility and migrate existing `Active` → `Available` for assets linked to products.

### Migration 2: Create `booking_assets` pivot table

```sql
CREATE TABLE booking_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_id) REFERENCES cmdb(device_id) ON DELETE CASCADE,
    INDEX idx_booking_assets_booking (booking_id),
    INDEX idx_booking_assets_asset (asset_id)
);
```

### Migration 3: Create `booking_inspections` table

```sql
CREATE TABLE booking_inspections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    type ENUM('checkout', 'return') NOT NULL,
    photos JSON NOT NULL DEFAULT '[]',
    condition_notes TEXT NULL,
    damage_flagged BOOLEAN NOT NULL DEFAULT FALSE,
    inspected_by BIGINT UNSIGNED NOT NULL,
    inspected_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (inspected_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_inspections_booking (booking_id),
    INDEX idx_inspections_type (booking_id, type)
);
```

Photos stored as JSON array of relative paths: `["inspections/42/checkout_1.jpg", "inspections/42/checkout_2.jpg"]`

### Migration 4: Add `fulfilment_stage` to `bookings` table

```sql
ALTER TABLE bookings ADD COLUMN fulfilment_stage VARCHAR(20) NOT NULL DEFAULT 'ordered';
ALTER TABLE bookings ADD INDEX idx_bookings_fulfilment_stage (fulfilment_stage);
```

Valid values: `ordered`, `packing`, `ready`, `checked_out`, `returned`, `inspected`

### Migration 5: Add `track_individual_assets` to `products` table

```sql
ALTER TABLE products ADD COLUMN track_individual_assets BOOLEAN NOT NULL DEFAULT FALSE;
```

When true, the product's availability is derived from linked assets rather than the manual `stock_quantity` field.

## Models

### BookingAsset (new)

```php
class BookingAsset extends Model
{
    protected $fillable = ['booking_id', 'asset_id', 'assigned_at', 'released_at'];
    protected $casts = ['assigned_at' => 'datetime', 'released_at' => 'datetime'];

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class, 'asset_id', 'device_id'); }
}
```

### BookingInspection (new)

```php
class BookingInspection extends Model
{
    protected $fillable = ['booking_id', 'type', 'photos', 'condition_notes', 'damage_flagged', 'inspected_by', 'inspected_at'];
    protected $casts = ['photos' => 'array', 'damage_flagged' => 'boolean', 'inspected_at' => 'datetime'];

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspected_by'); }
}
```

### Asset (updated)

Add relationships:
```php
public function product(): BelongsTo { return $this->belongsTo(Product::class); }
public function bookingAssets(): HasMany { return $this->hasMany(BookingAsset::class, 'asset_id', 'device_id'); }
```

### Booking (updated)

Add relationships and attribute:
```php
public function assets(): HasMany { return $this->hasMany(BookingAsset::class); }
public function inspections(): HasMany { return $this->hasMany(BookingInspection::class); }
public function checkoutInspection(): HasOne { return $this->hasOne(BookingInspection::class)->where('type', 'checkout'); }
public function returnInspection(): HasOne { return $this->hasOne(BookingInspection::class)->where('type', 'return'); }
```

Add `fulfilment_stage` to fillable and casts.

### Product (updated)

Add relationship and helper:
```php
public function assets(): HasMany { return $this->hasMany(Asset::class); }

public function getAvailableAssetCount(): int
{
    if (!$this->track_individual_assets) {
        return $this->stock_quantity ?? 0;
    }
    return $this->assets()->where('asset_status', 'Available')->count();
}
```

Add `track_individual_assets` to fillable/casts.

## Services

### AssetAssignmentService

```php
class AssetAssignmentService
{
    /**
     * Assign specific assets to a booking. Updates asset status to Reserved.
     * Validates all assets belong to the booking's product and are Available.
     */
    public function assignAssets(Booking $booking, array $assetIds): void;

    /**
     * Release all assets from a booking. Updates asset status back to Available
     * (or In Repair if damage_flagged on return inspection).
     */
    public function releaseAssets(Booking $booking, array $damagedAssetIds = []): void;
}
```

### BookingInspectionService

```php
class BookingInspectionService
{
    /**
     * Create a checkout inspection with uploaded photos.
     * Stores photos to storage/app/inspections/{booking_id}/checkout_*.
     * Returns the created BookingInspection.
     */
    public function createCheckoutInspection(Booking $booking, array $photos, ?string $notes, int $adminId): BookingInspection;

    /**
     * Create a return inspection with uploaded photos.
     * Stores photos to storage/app/inspections/{booking_id}/return_*.
     * Returns the created BookingInspection.
     */
    public function createReturnInspection(Booking $booking, array $photos, ?string $notes, bool $damageFlagged, int $adminId): BookingInspection;
}
```

### FulfilmentStageService

Manages the state machine transitions with validation:

```php
class FulfilmentStageService
{
    const STAGES = ['ordered', 'packing', 'ready', 'checked_out', 'returned', 'inspected'];

    /**
     * Advance a booking to the next stage. Validates:
     * - Transition is sequential (no skipping)
     * - Pre-conditions met (e.g. assets assigned before "ready")
     * Throws InvalidArgumentException on invalid transition.
     */
    public function advance(Booking $booking, string $targetStage): void;

    /**
     * Get the allowed next stage for a booking (or null if at final stage).
     */
    public function getNextStage(Booking $booking): ?string;

    /**
     * Check pre-conditions for advancing to a target stage.
     * Returns array of unmet conditions (empty = ready to advance).
     */
    public function checkPreConditions(Booking $booking, string $targetStage): array;
}
```

**Stage pre-conditions:**
| Target Stage | Pre-conditions |
|---|---|
| packing | Booking paid (payment_status = paid/paid_offline) |
| ready | At least 1 asset assigned (booking_assets count > 0) |
| checked_out | Checkout inspection exists |
| returned | — (just records the return timestamp) |
| inspected | Return inspection exists |

## Controllers

### Admin\FulfilmentQueueController

```
GET  /admin/fulfilment                    → index (grouped by stage, filterable)
GET  /admin/fulfilment/{booking}          → show (booking detail with inspections, assets, timeline)
POST /admin/fulfilment/{booking}/assign   → assignAssets (form with asset checkboxes)
POST /admin/fulfilment/{booking}/advance  → advance to next stage
POST /admin/fulfilment/{booking}/inspect  → store inspection (multipart with photos)
```

### Admin\CustomerShopController

```
GET  /admin/customers/{customer}/shop     → index (KPIs, orders, bookings, documents)
```

### Portal\CartController (enhanced)

- `updateQuantity(Request, int $index)` — new method for adjusting quantity on cart page

### Portal\ShopController (enhanced)

- Product detail view gets quantity input for one-off products

## Views

### Admin Views

**`admin.fulfilment.index`** — Kanban-style or tabbed view showing bookings by stage:
- Tabs: Ordered | Packing | Ready | Checked Out | Returned | Inspected
- Each card shows: customer name, product, dates, quantity, action button for next stage

**`admin.fulfilment.show`** — Booking detail with:
- Timeline showing all stage transitions with timestamps
- Asset assignment panel (checkboxes of available assets with serial/name)
- Inspection panels: checkout photos + notes, return photos + notes (side-by-side)
- Packing list summary

**`admin.customers.shop`** — Customer shop & rental tab:
- KPI cards row: Total Rental Spend | Total Purchase Spend | Orders | Rentals | Avg Order Value
- Loyalty summary: customer since, lifetime spend, tier badge
- Orders table (filterable by type/date)
- Active bookings table with stage badges
- Past bookings table with inspection notes preview
- Documents section: invoices + agreements grouped by order

### Portal Views (minimal changes)

**`portal.shop.show`** — Add quantity input for one-off products (identical pattern to rental quantity picker)

**`portal.cart.index`** — Add quantity adjustment (+/- buttons or number input) for one-off items

## File Storage Structure

```
storage/app/
├── inspections/
│   └── {booking_id}/
│       ├── checkout_1.jpg
│       ├── checkout_2.jpg
│       ├── return_1.jpg
│       └── return_2.jpg
├── invoices/
│   └── order-{id}.pdf          (existing)
└── ...
```

Photos are stored as uploaded (JPEG/PNG), resized server-side to max 1920px width to save space. Paths stored in the `booking_inspections.photos` JSON column as relative paths from the storage root.

## State Machine Diagram

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌─────────────┐    ┌──────────┐    ┌───────────┐
│  Ordered │───►│ Packing  │───►│  Ready   │───►│ Checked Out │───►│ Returned │───►│ Inspected │
└──────────┘    └──────────┘    └──────────┘    └─────────────┘    └──────────┘    └───────────┘
                 assign assets   validate        photo upload        record          photo upload
                 from pool       assets exist    + condition notes   timestamp       + condition
                                                 update assets                       release assets
                                                 → Rented Out                        → Available
                                                                                     (or In Repair)
```

## Route Registration

```php
// Admin fulfilment routes
Route::prefix('admin/fulfilment')->name('admin.fulfilment.')->middleware(['auth', 'verified', EnsureIsAdmin::class])->group(function () {
    Route::get('/', [Admin\FulfilmentQueueController::class, 'index'])->name('index');
    Route::get('/{booking}', [Admin\FulfilmentQueueController::class, 'show'])->name('show');
    Route::post('/{booking}/assign-assets', [Admin\FulfilmentQueueController::class, 'assignAssets'])->name('assignAssets');
    Route::post('/{booking}/advance', [Admin\FulfilmentQueueController::class, 'advance'])->name('advance');
    Route::post('/{booking}/inspect', [Admin\FulfilmentQueueController::class, 'inspect'])->name('inspect');
});

// Admin customer shop page
Route::get('/admin/customers/{customer}/shop', [Admin\CustomerShopController::class, 'index'])
    ->middleware(['auth', 'verified', EnsureIsAdmin::class])
    ->name('admin.customers.shop');

// Portal cart quantity update
Route::put('/portal/cart/{index}/quantity', [Portal\CartController::class, 'updateQuantity'])
    ->name('portal.cart.updateQuantity');
```

## Security Considerations

- Photo uploads validated: max 10MB per file, only JPEG/PNG, max 10 files per inspection
- Asset assignment validates assets belong to the correct product and are in "Available" status
- Stage transitions are server-validated (no client-side skipping)
- Customer portal never exposes asset serial numbers, inspection photos, or internal fulfilment stage details
- File paths use booking IDs (not user-controllable names) to prevent path traversal
- All admin routes behind `EnsureIsAdmin` middleware
