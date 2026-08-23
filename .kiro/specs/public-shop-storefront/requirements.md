# Requirements Document

## Introduction

The Public Shop Storefront exposes a browse-only, unauthenticated product catalog at `/shop` for visitors who have not yet logged in. Only products explicitly marked with the new `public` visibility type are displayed. No purchasing, cart, or account functionality is available on the public storefront — visitors must authenticate to buy. The feature extends the existing `ProductVisibility` model with a new `public` option and adds a dedicated `PublicShopController` outside the Portal namespace, rendering views within the existing guest layout. Security is paramount: public queries must never join customer tables or expose restricted products.

## Glossary

- **Public_Storefront**: The unauthenticated product browsing interface accessible at `/shop`, rendering products with `public` visibility using the guest layout.
- **Product**: A purchasable or rentable item in the catalog, belonging to one of three types: Equipment Rental, One-Off Purchase, or Hosting.
- **ProductVisibility**: The model controlling which audiences can see a given Product, using the `visibility_type` field with values `all`, `customers`, `tiers`, and now `public`.
- **PublicShopController**: A Laravel controller outside the Portal namespace responsible for handling public storefront requests without authentication middleware.
- **Guest_Layout**: The existing Blade layout at `layouts/guest.blade.php` used for unauthenticated pages.
- **Portal_Shop**: The authenticated shop interface at `/portal/shop` accessible to logged-in customers.
- **Admin_Panel**: The administrative interface at `/admin` used by administrators to manage the product catalog and visibility settings.
- **Visitor**: An unauthenticated user browsing the Public_Storefront.
- **Customer**: An authenticated user with access to the Portal_Shop, cart, and checkout features.
- **CTA**: A call-to-action UI element prompting the Visitor to log in or register to make a purchase.

## Requirements

### Requirement 1: Public Visibility Type Configuration

**User Story:** As an admin, I want to mark products as publicly visible, so that unauthenticated visitors can browse them on the public storefront.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a "Public" option in the ProductVisibility `visibility_type` selection alongside the existing "All Customers", "Specific Customers", and "Specific Tiers" options.
2. WHEN an admin sets a Product's visibility_type to "public", THE Admin_Panel SHALL save the value `public` to the ProductVisibility record for that Product.
3. WHEN a Product has visibility_type set to "public", THE Portal_Shop SHALL display that Product to all authenticated Customers in addition to the Public_Storefront.
4. WHEN an admin changes a Product's visibility_type from "public" to another value, THE Public_Storefront SHALL cease displaying that Product on the next page load.

### Requirement 2: Public Shop Index Page

**User Story:** As a visitor, I want to browse publicly available products without logging in, so that I can evaluate offerings before creating an account.

#### Acceptance Criteria

1. THE Public_Storefront SHALL be accessible at the URL path `/shop` without authentication.
2. THE Public_Storefront SHALL render using the Guest_Layout.
3. THE Public_Storefront SHALL display Products with visibility_type "public" in a grid layout showing the product name, image, price, description, and product type.
4. THE Public_Storefront SHALL allow Visitors to filter displayed Products by product type (Equipment Rental, One-Off Purchase, Hosting).
5. THE Public_Storefront SHALL allow Visitors to search Products by name or description.
6. WHEN no Products match the applied filters or search query, THE Public_Storefront SHALL display a message indicating no products were found.
7. WHILE a Product has zero stock quantity, THE Public_Storefront SHALL display that Product as unavailable.

### Requirement 3: Public Product Detail Page

**User Story:** As a visitor, I want to view detailed information about a publicly listed product, so that I can make an informed decision before registering.

#### Acceptance Criteria

1. THE Public_Storefront SHALL provide a product detail page at `/shop/{product}` without authentication.
2. THE Public_Storefront SHALL display the full product description, image, price, product type, and billing frequency where applicable on the detail page.
3. WHEN a Visitor requests a product detail page for a Product whose visibility_type is not "public", THE Public_Storefront SHALL return a 404 response.
4. WHEN a Visitor requests a product detail page for a Product that has been archived, THE Public_Storefront SHALL return a 404 response.
5. THE Public_Storefront SHALL display a prominent CTA on the detail page directing Visitors to log in or register to purchase the Product.

### Requirement 4: Security Controls

**User Story:** As a system operator, I want the public storefront to be isolated from customer data and restricted products, so that no sensitive information is exposed to unauthenticated visitors.

#### Acceptance Criteria

1. THE PublicShopController SHALL use the `scopePubliclyVisible` query scope on the Product model, filtering exclusively on `visibility_type = 'public'`.
2. THE PublicShopController SHALL execute database queries that do not join or reference customer, user, order, or service tables.
3. THE Public_Storefront routes SHALL have no authentication or session middleware applied.
4. WHEN a Visitor attempts to access cart, checkout, or order endpoints, THE application SHALL redirect the Visitor to the login page.
5. THE Public_Storefront routes SHALL apply rate limiting middleware to prevent abuse.
6. IF a request to `/shop/{product}` references a Product ID that does not exist, THEN THE Public_Storefront SHALL return a 404 response without revealing internal identifiers or stack traces.

### Requirement 5: Login and Signup Call-to-Action

**User Story:** As a visitor, I want clear prompts to log in or register, so that I can transition to a purchasing flow when ready.

#### Acceptance Criteria

1. THE Public_Storefront index page SHALL display a persistent CTA banner or button directing Visitors to log in or create an account to purchase products.
2. THE Public_Storefront product detail page SHALL display a CTA button labelled "Log in to purchase" linking to the login page.
3. WHEN a Visitor clicks the login CTA, THE Public_Storefront SHALL redirect the Visitor to the login page with an `intended` URL parameter set to `/portal/shop`.
4. WHEN a Visitor clicks a registration CTA, THE Public_Storefront SHALL redirect the Visitor to the registration page.
5. WHEN an authenticated Customer navigates to `/shop`, THE Public_Storefront SHALL display a link to the Portal_Shop at `/portal/shop`.

### Requirement 6: SEO and Indexability

**User Story:** As a system operator, I want the public storefront pages to be indexable by search engines, so that potential customers can discover products through organic search.

#### Acceptance Criteria

1. THE Public_Storefront pages SHALL include appropriate HTML `<title>` and `<meta name="description">` tags derived from page content and Product details.
2. THE Public_Storefront product detail pages SHALL include Open Graph meta tags (`og:title`, `og:description`, `og:image`) populated from the Product record.
3. THE Public_Storefront SHALL serve pages with HTTP status 200 for valid requests, enabling search engine crawling.
4. THE Public_Storefront SHALL not include `noindex` or `nofollow` directives on public product pages.
5. THE Public_Storefront SHALL generate a sitemap-compatible URL structure using semantic, slug-based URLs where feasible.

### Requirement 7: Navigation and Transition Flow

**User Story:** As a visitor, I want a seamless transition from browsing to purchasing, so that I do not lose context when I decide to buy.

#### Acceptance Criteria

1. WHEN a Visitor clicks "Log in to purchase" on the Public_Storefront, THE application SHALL store the intended destination so that after successful login the Customer is redirected to the Portal_Shop.
2. WHEN an authenticated Customer is redirected to the Portal_Shop after login, THE Portal_Shop SHALL display the full authenticated catalog including the Product the Customer was viewing publicly.
3. THE Guest_Layout SHALL include navigation links to the Public_Storefront at `/shop` and the login page.
4. THE Public_Storefront SHALL include a site header consistent with the Guest_Layout branding and navigation structure.
5. WHEN a Visitor navigates directly to `/portal/shop` without authentication, THE application SHALL redirect the Visitor to the login page.
