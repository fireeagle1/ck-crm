<?php

use App\Http\Controllers\Api\Admin\AssetController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\BookingController;
use App\Http\Controllers\Api\Admin\CustomerAppController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DeviceTokenController;
use App\Http\Controllers\Api\Admin\InvoiceController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\Admin\ShopOrderController;
use App\Http\Controllers\Api\Admin\ShopProductController;
use App\Http\Controllers\Api\Admin\TicketController;
use App\Http\Controllers\Api\FindingsController;
use App\Http\Controllers\Mcp\McpController;
use App\Http\Middleware\EnsureHttps;
use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    // Public (unauthenticated) route — rate limited to prevent brute-force
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // Authenticated admin routes
    Route::middleware(['auth:sanctum', EnsureIsAdmin::class])->group(function () {
        // Auth management
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Customer context — must be BEFORE the resource route
        Route::get('/ticket-context', [TicketController::class, 'customerContext']);

        // Customers
        Route::apiResource('customers', CustomerController::class);

        // Services (read-only)
        Route::apiResource('services', ServiceController::class)->only(['index', 'show']);

        // Customer apps (technical records) — full CRUD
        Route::apiResource('customer-apps', CustomerAppController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        // Tickets — full CRUD + replies
        Route::apiResource('tickets', TicketController::class)->except(['destroy']);
        Route::post('/tickets/{ticket}/replies', [TicketController::class, 'reply']);

        // Assets (CMDB) — full CRUD
        Route::apiResource('assets', AssetController::class);

        // Invoices (read-only + remind)
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::post('/invoices/{invoice}/remind', [InvoiceController::class, 'remind']);

        // Shop — Orders (full management)
        Route::get('/shop/orders', [ShopOrderController::class, 'index']);
        Route::get('/shop/orders/{order}', [ShopOrderController::class, 'show']);
        Route::post('/shop/orders/{order}/fulfil', [ShopOrderController::class, 'fulfil']);
        Route::post('/shop/orders/{order}/cancel', [ShopOrderController::class, 'cancel']);
        Route::post('/shop/orders/{order}/mark-paid-offline', [ShopOrderController::class, 'markPaidOffline']);
        Route::post('/shop/orders/{order}/note', [ShopOrderController::class, 'addNote']);
        Route::post('/shop/orders/{order}/bookings/{booking}/advance-stage', [ShopOrderController::class, 'advanceStage']);
        Route::post('/shop/orders/{order}/bookings/{booking}/set-stage', [ShopOrderController::class, 'setStage']);
        Route::post('/shop/orders/{order}/bookings/{booking}/assign-assets', [ShopOrderController::class, 'assignAssets']);
        Route::post('/shop/orders/{order}/bookings/{booking}/inspect', [ShopOrderController::class, 'inspect']);
        Route::delete('/shop/orders/{order}/bookings/{booking}/inspection/{type}', [ShopOrderController::class, 'deleteInspection']);
        Route::post('/shop/orders/{order}/bookings/{booking}/mark-returned', [ShopOrderController::class, 'markReturned']);

        // Inspection photo serving
        Route::get('/shop/bookings/inspection-photo/{path}', [ShopOrderController::class, 'inspectionPhoto'])->where('path', '.*');

        // Shop — Products (read-only)
        Route::get('/shop/products', [ShopProductController::class, 'index']);

        // Shop — Rentals/Bookings
        Route::get('/shop/rentals/calendar', [BookingController::class, 'calendar']);
        Route::get('/shop/rentals', [BookingController::class, 'index']);
        Route::get('/shop/rentals/{booking}', [BookingController::class, 'show']);

        // Device tokens (push notifications)
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

        // QR Code Scanner — resolve a scanned code to an entity
        Route::get('/scan/{code}', [\App\Http\Controllers\Api\Admin\ScanController::class, 'resolve']);
    });
});

/*
|--------------------------------------------------------------------------
| MCP (Model Context Protocol) route group
|--------------------------------------------------------------------------
|
| Additive route group for the ChatGPT MCP integration. Appended AFTER the
| admin group above so nothing in the existing `/api/admin/*` Sanctum flow
| changes (Req 14.1). Served over streamable HTTP so it is compatible with
| cPanel cron hosting — no long-lived process (Req 14.3).
|
| Middleware order (applied outer -> inner, Req 1.6 / design "Middleware order"):
|   1. EnsureHttps   — reject non-HTTPS before anything else (Req 1.6), honours
|                      X-Forwarded-Proto behind the cPanel/Cloudflare proxy.
|   2. throttle:mcp  — named per-token rate limiter (Req 13.2).
|   3. auth:api      — Passport access-token guard (Req 1.5, 1.7).
|   4. mcp.scope     — per-tool scope enforcement on `tools/call` (CheckMcpScope,
|                      Req 1.4, 2.1, 2.4); lifecycle methods pass through.
|
| DEPENDENCY: the `mcp` named limiter referenced by `throttle:mcp` is registered
| by task 11.2. A defensive placeholder is registered in AppServiceProvider so
| this group resolves and functions until 11.2 supplies the configurable one.
*/
Route::prefix('mcp')
    ->middleware([
        EnsureHttps::class,
        'throttle:mcp',
        'auth:api',
        'mcp.scope',
    ])
    ->group(function () {
        // JSON-RPC entrypoint: initialize, tools/list, tools/call.
        Route::post('/', [McpController::class, 'handle']);

        // Streamable HTTP (SSE) channel.
        Route::get('/', [McpController::class, 'handleStream']);

        // Findings receiving endpoint stub (Req 3.1–3.5, 3.7). Inherits
        // EnsureHttps + throttle:mcp + auth:api from this group. NOTE: the
        // group's `mcp.scope` (CheckMcpScope) only gates JSON-RPC `tools/call`
        // bodies and passes through this plain REST POST, so FindingsController
        // enforces the `apps:write` scope explicitly (Req 3.5).
        Route::post('/apps/{identifier}/findings', [FindingsController::class, 'store']);
    });
