# Design Document: Public Shop Storefront

## Architecture Overview

The Public Shop Storefront introduces an unauthenticated product browsing layer that sits outside the existing Portal (authenticated customer) and Admin namespaces. It reuses the existing `Product` and `ProductVisibility` models, extending the `visibility_type` enum with a new `public` value. A dedicated controller, query scope, and Blade views handle public requests using the existing guest layout.

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                          Routes (web.php)                            │
├────────────────┬───────────────────────┬────────────────────────────┤
│  Public Routes │   Portal Routes       │   Admin Routes             │
│  (no auth)     │   (auth + verified)   │   (auth + EnsureIsAdmin)   │
│  /shop         │   /portal/shop        │   /admin/shop/products     │
│  /shop/{slug}  │   /portal/shop/{id}   │                            │
└───────┬────────┴──────────┬────────────┴──────────────┬─────────────┘
        │                   │                           │
        ▼                   ▼                           ▼
 PublicShopController  Portal\ShopController   Admin\ShopProductController
        │                   │                           │
        ▼                   ▼                           ▼
 Product::publiclyVisible  Product::visible($customer)  Product::query()
        │                   │                           │
        └───────────────────┴───────────────────────────┘
                            │
                      Product Model
                      ProductVisibility Model
```

## Components

### 1. Database Migration

A migration to extend the `visibility_type` enum column on the `product_visibilities` table:

```php
// database/migrations/xxxx_xx_xx_add_public_to_product_visibility_type.php

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE product_visibilities MODIFY COLUMN visibility_type ENUM('all', 'customers', 'tiers', 'public') DEFAULT 'all'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE product_visibilities MODIFY COLUMN visibility_type ENUM('all', 'customers', 'tiers') DEFAULT 'all'");
    }
};
```

### 2. Product Model — New Scope

A new `scopePubliclyVisible` scope added to `App\Models\Product`:

```php
/**
 * Scope to filter products visible on the public storefront.
 * Only includes non-archived products with visibility_type = 'public'.
 */
public function scopePubliclyVisible(Builder $query): Builder
{
    return $query->where('is_archived', false)
        ->whereHas('visibilityRule', function ($vr) {
            $vr->where('visibility_type', 'public');
        });
}
```

This scope intentionally avoids any joins to customer, user, order, or service tables.

### 3. Product Model — Update `scopeVisible`

The existing `scopeVisible` scope must also include `public` products for authenticated customers. Add a condition inside the existing `orWhereHas` block:

```php
->orWhere(function ($inner) {
    $inner->where('visibility_type', 'public');
});
```

### 4. PublicShopController

Location: `app/Http/Controllers/PublicShopController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicShopController extends Controller
{
    /**
     * Display the public shop index with filtering and search.
     */
    public function index(Request $request): View
    {
        $query = Product::publiclyVisible();

        if ($request->filled('type')) {
            $query->where('product_type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->paginate(12);

        return view('public.shop.index', compact('products'));
    }

    /**
     * Display a single product's detail page.
     */
    public function show(string $slug): View
    {
        $product = Product::publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.shop.show', compact('product'));
    }
}
```

Key design decisions:
- Uses `string $slug` parameter instead of route-model binding to enforce slug-based URLs and prevent ID enumeration.
- `firstOrFail()` returns a 404 for non-existent or non-public products without revealing internal state.
- No constructor dependencies on authenticated services.

### 5. Route Registration

Added to the public routes section of `routes/web.php`:

```php
/*
|--------------------------------------------------------------------------
| Public Shop (unauthenticated)
|--------------------------------------------------------------------------
*/
Route::middleware(['throttle:60,1'])->prefix('shop')->name('public.shop.')->group(function () {
    Route::get('/', [PublicShopController::class, 'index'])->name('index');
    Route::get('/{slug}', [PublicShopController::class, 'show'])->name('show');
});
```

Design decisions:
- `throttle:60,1` applies rate limiting (60 requests per minute) without authentication or session middleware.
- Routes are named `public.shop.*` to avoid collision with `portal.shop.*`.
- No `auth`, `verified`, or `web` session middleware applied.

### 6. Products Table — Slug Column

A migration to add a `slug` column to the products table for SEO-friendly URLs:

```php
// database/migrations/xxxx_xx_xx_add_slug_to_products_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Back-fill existing products with slugs
        DB::table('products')->whereNull('slug')->get()->each(function ($product) {
            DB::table('products')->where('id', $product->id)->update([
                'slug' => Str::slug($product->name) . '-' . $product->id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
```

The Product model should auto-generate slugs on creation:

```php
protected static function booted(): void
{
    static::creating(function (Product $product) {
        if (empty($product->slug)) {
            $product->slug = Str::slug($product->name);
            // Ensure uniqueness
            $count = static::where('slug', $product->slug)->count();
            if ($count > 0) {
                $product->slug .= '-' . ($count + 1);
            }
        }
    });
}
```

### 7. Blade Views

#### 7.1 Public Shop Index — `resources/views/public/shop/index.blade.php`

Uses `<x-guest-shop-layout>` (a new component extending guest layout for full-width shop pages):

```php
<x-guest-shop-layout>
    <x-slot:title>Shop - {{ config('app.name') }}</x-slot:title>
    <x-slot:metaDescription>Browse our range of hosting, equipment, and services.</x-slot:metaDescription>

    {{-- CTA Banner --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-center justify-between">
        <p class="text-sm text-blue-800">Log in or create an account to purchase products.</p>
        <div class="flex gap-2">
            @auth
                <a href="{{ route('portal.shop.index') }}" class="...">Go to Portal Shop</a>
            @else
                <a href="{{ route('login', ['intended' => route('portal.shop.index')]) }}" class="...">Log in</a>
                <a href="{{ route('register') }}" class="...">Register</a>
            @endauth
        </div>
    </div>

    {{-- Filter/Search form --}}
    {{-- Product grid (mirrors portal shop grid structure) --}}
    {{-- Unavailable badge for zero-stock products --}}
    {{-- "No products found" empty state --}}
    {{-- Pagination --}}
</x-guest-shop-layout>
```

#### 7.2 Public Shop Detail — `resources/views/public/shop/show.blade.php`

```php
<x-guest-shop-layout>
    <x-slot:title>{{ $product->name }} - {{ config('app.name') }}</x-slot:title>
    <x-slot:metaDescription>{{ Str::limit(strip_tags($product->description), 160) }}</x-slot:metaDescription>
    <x-slot:ogTitle>{{ $product->name }}</x-slot:ogTitle>
    <x-slot:ogDescription>{{ Str::limit(strip_tags($product->description), 160) }}</x-slot:ogDescription>
    <x-slot:ogImage>{{ $product->image_path ? asset('storage/' . $product->image_path) : '' }}</x-slot:ogImage>

    {{-- Product detail: image, name, price, type, billing frequency, full description --}}
    {{-- CTA: "Log in to purchase" button --}}
    @auth
        <a href="{{ route('portal.shop.show', $product) }}">View in Portal Shop</a>
    @else
        <a href="{{ route('login', ['intended' => route('portal.shop.index')]) }}">Log in to purchase</a>
        <a href="{{ route('register') }}">Create an account</a>
    @endauth
</x-guest-shop-layout>
```

#### 7.3 Guest Shop Layout Component — `resources/views/components/guest-shop-layout.blade.php`

A full-width layout extending the guest aesthetic but without the split-panel login design:

```php
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @if (isset($metaDescription))
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if (isset($ogTitle))
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDescription ?? '' }}">
        <meta property="og:image" content="{{ $ogImage ?? '' }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased bg-gray-50">
    {{-- Header with branding, nav links to /shop and login --}}
    <header class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('public.shop.index') }}"><!-- Logo --></a>
            <nav class="flex items-center gap-4">
                <a href="{{ route('public.shop.index') }}">Shop</a>
                @auth
                    <a href="{{ route('portal.dashboard') }}">Dashboard</a>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                    <a href="{{ route('register') }}">Register</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
```

### 8. Admin Panel — Visibility Form Update

Add a "Public" radio option to both `resources/views/admin/shop/products/create.blade.php` and `edit.blade.php`:

```html
<label class="flex items-center gap-2 text-sm text-gray-700">
    <input type="radio" name="visibility_type" value="public" x-model="visibilityType"
           class="text-blue-600 focus:ring-blue-500">
    Public (visible to unauthenticated visitors)
</label>
```

Update the validation rule in `Admin\ShopProductController` store/update methods:

```php
'visibility_type' => 'nullable|in:all,customers,tiers,public',
```

The `syncVisibility` method requires no changes — it already handles any `visibility_type` value generically and only syncs customer/tier pivots when those types are selected.

### 9. Guest Layout Navigation Update

Add a "Shop" navigation link to the existing `resources/views/layouts/guest.blade.php`. Since the current guest layout is a split-panel login/register design without navigation, the public shop pages use the new `guest-shop-layout` component which includes its own header. However, we can add a minimal link on the existing guest layout for consistency:

```php
{{-- In the brand section of guest.blade.php --}}
<a href="{{ route('public.shop.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
    Browse Shop
</a>
```

## Data Models

### Modified: `product_visibilities` Table

| Column | Type | Change |
|--------|------|--------|
| visibility_type | ENUM | Add `'public'` to allowed values |

### New: `products.slug` Column

| Column | Type | Notes |
|--------|------|-------|
| slug | VARCHAR(255) | Nullable, unique, auto-generated from name |

## Interfaces

### PublicShopController Routes

| Method | URI | Name | Middleware | Description |
|--------|-----|------|------------|-------------|
| GET | `/shop` | `public.shop.index` | `throttle:60,1` | Product listing with filter/search |
| GET | `/shop/{slug}` | `public.shop.show` | `throttle:60,1` | Product detail by slug |

### Query Parameters (Index)

| Parameter | Type | Description |
|-----------|------|-------------|
| `type` | string | Filter by product_type: `equipment_rental`, `one_off`, `hosting` |
| `search` | string | Search in product name and description |
| `page` | integer | Pagination page number |

## Error Handling

| Scenario | Response |
|----------|----------|
| Non-existent slug | 404 (via `firstOrFail()`) |
| Non-public product slug | 404 (excluded by `scopePubliclyVisible`) |
| Archived product slug | 404 (excluded by `is_archived` check in scope) |
| Rate limit exceeded | 429 Too Many Requests (Laravel throttle) |
| Invalid filter values | Ignored (no products returned, shows empty state) |

The `firstOrFail()` approach ensures no internal model IDs, stack traces, or visibility information leak to the visitor. Laravel's exception handler renders a generic 404 page in production.

## Security Considerations

1. **Query Isolation**: `scopePubliclyVisible` only joins `product_visibilities` — never `customers`, `users`, `orders`, or `services`.
2. **No Session State**: Public routes have no session middleware, so no CSRF token or session cookie is set for anonymous browsing.
3. **Rate Limiting**: `throttle:60,1` prevents scraping and abuse from a single IP.
4. **Slug-Based Routing**: Prevents sequential ID enumeration.
5. **No Cart/Checkout Exposure**: Cart and order endpoints remain behind `auth` middleware; unauthenticated visitors are redirected to login.
6. **Input Sanitization**: The `search` parameter is passed through Laravel's parameterized `LIKE` query binding, preventing SQL injection.

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Public products are visible to all authenticated customers

*For any* authenticated Customer and *for any* Product with `visibility_type = 'public'`, the `scopeVisible` query scope SHALL include that Product in its result set.

**Validates: Requirements 1.3**

### Property 2: Non-public or archived products are excluded from public storefront

*For any* Product whose `visibility_type` is not `'public'` OR whose `is_archived` flag is `true`, the `scopePubliclyVisible` query scope SHALL exclude that Product from its result set, and the `/shop/{slug}` endpoint SHALL return HTTP 404.

**Validates: Requirements 1.4, 3.3, 3.4**

### Property 3: Product type filter returns only matching products

*For any* valid `product_type` value used as a filter parameter on the public shop index, all Products in the response SHALL have a `product_type` equal to the filter value.

**Validates: Requirements 2.4**

### Property 4: Search returns products matching name or description

*For any* non-empty search string that is a substring of a public Product's name or description, the public shop index response SHALL include that Product in its result set.

**Validates: Requirements 2.5**

### Property 5: Zero-stock products display as unavailable

*For any* public Product with `stock_quantity` equal to zero, the public storefront index page SHALL render that Product with an "unavailable" indicator.

**Validates: Requirements 2.7**

### Property 6: Product detail page displays all required fields

*For any* public Product, the detail page at `/shop/{slug}` SHALL contain the Product's name, description, price, product type, and (where applicable) billing frequency in the response body.

**Validates: Requirements 3.2**

### Property 7: Public queries never reference sensitive tables

*For any* request handled by `PublicShopController`, the set of database queries executed SHALL NOT contain references to the `customers`, `users`, `orders`, or `services` tables.

**Validates: Requirements 4.2**

### Property 8: Login CTA includes intended redirect to portal shop

*For any* page rendered by the Public_Storefront for an unauthenticated Visitor, the login CTA link SHALL include a parameter that sets the post-authentication redirect destination to `/portal/shop`.

**Validates: Requirements 5.3, 7.1**

### Property 9: SEO meta tags populated from product data

*For any* public Product, the detail page response SHALL include a `<title>` tag containing the Product name, a `<meta name="description">` tag derived from the Product description, Open Graph tags (`og:title`, `og:description`, `og:image`) populated from the Product record, and SHALL NOT contain `noindex` or `nofollow` directives.

**Validates: Requirements 6.1, 6.2, 6.4**

### Property 10: Slug-based URL structure

*For any* public Product, the canonical URL on the public storefront SHALL be `/shop/{slug}` where `{slug}` is a URL-safe string derived from the Product name, not a numeric database ID.

**Validates: Requirements 6.5**
