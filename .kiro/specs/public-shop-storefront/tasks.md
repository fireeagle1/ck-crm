# Implementation Plan: Public Shop Storefront

## Overview

This plan implements an unauthenticated product browsing storefront at `/shop` that displays products marked with a new `public` visibility type. The implementation adds database schema changes (visibility enum, slug column), a new model scope and controller, Blade views with a dedicated guest-shop layout, admin panel visibility option, SEO meta tags, and CTA links directing visitors to log in or register. The stack is Laravel (PHP), Blade views with Tailwind CSS.

## Tasks

- [ ] 1. Database migrations and model updates
  - [ ] 1.1 Create migration to add `public` to visibility_type enum
    - Create migration that ALTERs `product_visibilities.visibility_type` ENUM to include `'public'` alongside existing `'all'`, `'customers'`, `'tiers'` values
    - Include down() method to revert the enum
    - _Requirements: 1.1, 1.2_

  - [ ] 1.2 Create migration to add `slug` column to products table
    - Add nullable unique `slug` VARCHAR column after `name` on the `products` table
    - Back-fill existing products with `Str::slug($product->name) . '-' . $product->id` for uniqueness
    - _Requirements: 6.5_

  - [ ] 1.3 Add `scopePubliclyVisible` to Product model
    - Add query scope filtering `is_archived = false` and `whereHas('visibilityRule', visibility_type = 'public')`
    - Scope must not join customer, user, order, or service tables
    - _Requirements: 4.1, 4.2_

  - [ ] 1.4 Update `scopeVisible` to include public products
    - Add `orWhere('visibility_type', 'public')` inside the existing `orWhereHas` block so authenticated customers also see public products
    - _Requirements: 1.3_

  - [ ] 1.5 Add slug auto-generation to Product model
    - Add `booted()` lifecycle hook with `static::creating` that generates a unique slug from the product name using `Str::slug()`
    - Handle uniqueness conflicts by appending a numeric suffix
    - _Requirements: 6.5_

  - [ ]* 1.6 Write property test for public visibility scope correctness
    - **Property 2: Non-public or archived products are excluded from public storefront**
    - **Validates: Requirements 1.4, 3.3, 3.4**

  - [ ]* 1.7 Write property test for public products visible to authenticated customers
    - **Property 1: Public products are visible to all authenticated customers**
    - **Validates: Requirements 1.3**

- [ ] 2. Checkpoint - Run migrations and verify model scopes
  - Ensure migrations run without errors, `scopePubliclyVisible` returns only public non-archived products, `scopeVisible` includes public products for authenticated customers, and slug generation works. Ask the user if questions arise.

- [ ] 3. PublicShopController and route registration
  - [ ] 3.1 Create PublicShopController with index and show methods
    - Create `app/Http/Controllers/PublicShopController.php`
    - `index()`: query `Product::publiclyVisible()`, apply optional `type` filter (`product_type` column), apply optional `search` filter (LIKE on name and description), paginate at 12 items
    - `show(string $slug)`: query `Product::publiclyVisible()->where('slug', $slug)->firstOrFail()`, return 404 for non-public/non-existent/archived products
    - _Requirements: 2.1, 2.3, 2.4, 2.5, 2.6, 3.1, 3.2, 3.3, 3.4, 4.1, 4.6_

  - [ ] 3.2 Register public shop routes in web.php
    - Add route group with `throttle:60,1` middleware, prefix `shop`, name `public.shop.`
    - Register GET `/shop` → `index`, GET `/shop/{slug}` → `show`
    - No auth, verified, or session middleware on these routes
    - _Requirements: 2.1, 3.1, 4.3, 4.5_

  - [ ]* 3.3 Write property test for product type filter correctness
    - **Property 3: Product type filter returns only matching products**
    - **Validates: Requirements 2.4**

  - [ ]* 3.4 Write property test for search filter correctness
    - **Property 4: Search returns products matching name or description**
    - **Validates: Requirements 2.5**

  - [ ]* 3.5 Write property test for query isolation
    - **Property 7: Public queries never reference sensitive tables**
    - **Validates: Requirements 4.2**

- [ ] 4. Guest shop layout component
  - [ ] 4.1 Create guest-shop-layout Blade component
    - Create `resources/views/components/guest-shop-layout.blade.php`
    - Full-width layout with HTML head (title slot, meta description slot, OG meta tag slots), Vite assets, header with logo, nav links (Shop, Login/Register or Dashboard for authenticated users), main content area
    - Must not include `noindex` or `nofollow` directives
    - _Requirements: 2.2, 6.1, 6.4, 7.3, 7.4_

- [ ] 5. Public shop views
  - [ ] 5.1 Create public shop index view
    - Create `resources/views/public/shop/index.blade.php` using `<x-guest-shop-layout>`
    - Include: title/meta description slots, CTA banner (log in / register / go to portal for authenticated), filter form (product type dropdown), search input, product grid (name, image, price, description, type), "unavailable" badge for zero-stock products, empty state message, pagination links
    - Login CTA links must include `intended` parameter pointing to `/portal/shop`
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 5.1, 5.3, 5.4, 5.5, 6.1_

  - [ ] 5.2 Create public shop detail view
    - Create `resources/views/public/shop/show.blade.php` using `<x-guest-shop-layout>`
    - Include: title slot with product name, meta description from product description (truncated to 160 chars), OG meta tags (og:title, og:description, og:image), full product detail (name, image, price, type, billing frequency, full description), CTA section ("Log in to purchase" / "View in Portal Shop" for authenticated)
    - Login CTA must include `intended` parameter pointing to `/portal/shop`
    - _Requirements: 3.1, 3.2, 3.5, 5.2, 5.3, 6.1, 6.2, 6.3_

  - [ ]* 5.3 Write property test for zero-stock unavailable indicator
    - **Property 5: Zero-stock products display as unavailable**
    - **Validates: Requirements 2.7**

  - [ ]* 5.4 Write property test for product detail page required fields
    - **Property 6: Product detail page displays all required fields**
    - **Validates: Requirements 3.2**

  - [ ]* 5.5 Write property test for SEO meta tags
    - **Property 9: SEO meta tags populated from product data**
    - **Validates: Requirements 6.1, 6.2, 6.4**

  - [ ]* 5.6 Write property test for login CTA intended redirect
    - **Property 8: Login CTA includes intended redirect to portal shop**
    - **Validates: Requirements 5.3, 7.1**

  - [ ]* 5.7 Write property test for slug-based URL structure
    - **Property 10: Slug-based URL structure**
    - **Validates: Requirements 6.5**

- [ ] 6. Checkpoint - Verify public storefront end-to-end
  - Ensure public shop index renders with products, filtering and search work, detail page shows correct data with SEO meta tags, 404 returned for non-public products, and CTAs link correctly. Ask the user if questions arise.

- [ ] 7. Admin panel visibility update
  - [ ] 7.1 Add "Public" radio option to admin product create/edit views
    - Add a radio button with value `public` and label "Public (visible to unauthenticated visitors)" to both `resources/views/admin/shop/products/create.blade.php` and `edit.blade.php` visibility type selection
    - _Requirements: 1.1_

  - [ ] 7.2 Update validation rule in ShopProductController
    - Update the `visibility_type` validation in store/update methods to `'nullable|in:all,customers,tiers,public'`
    - _Requirements: 1.2_

- [ ] 8. Guest layout navigation link
  - [ ] 8.1 Add "Browse Shop" link to existing guest layout
    - Add a link to `route('public.shop.index')` in the existing `resources/views/layouts/guest.blade.php` brand section
    - _Requirements: 7.3_

- [ ] 9. Final checkpoint - Full integration verification
  - Ensure admin can set visibility to "public", product appears on `/shop`, product disappears when visibility changed away from "public", authenticated users see public products in portal shop, rate limiting responds with 429 after threshold, and all views render without errors. Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- The `syncVisibility` method in ShopProductController requires no changes — it already handles any visibility_type value generically
- Public routes intentionally exclude session middleware for zero-state anonymous browsing
- The search parameter uses Laravel's parameterized LIKE binding to prevent SQL injection
- Slug-based routing prevents ID enumeration attacks

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "1.4", "1.5"] },
    { "id": 2, "tasks": ["1.6", "1.7"] },
    { "id": 3, "tasks": ["3.1", "3.2"] },
    { "id": 4, "tasks": ["3.3", "3.4", "3.5", "4.1"] },
    { "id": 5, "tasks": ["5.1", "5.2"] },
    { "id": 6, "tasks": ["5.3", "5.4", "5.5", "5.6", "5.7"] },
    { "id": 7, "tasks": ["7.1", "7.2", "8.1"] }
  ]
}
```
