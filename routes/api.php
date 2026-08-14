<?php

use App\Http\Controllers\Api\Admin\AssetController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DeviceTokenController;
use App\Http\Controllers\Api\Admin\InvoiceController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\Admin\TicketController;
use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    // Public (unauthenticated) route
    Route::post('/auth/login', [AuthController::class, 'login']);

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

        // Tickets — full CRUD + replies
        Route::apiResource('tickets', TicketController::class)->except(['destroy']);
        Route::post('/tickets/{ticket}/replies', [TicketController::class, 'reply']);

        // Assets (CMDB) — full CRUD
        Route::apiResource('assets', AssetController::class);

        // Invoices (read-only + remind)
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::post('/invoices/{invoice}/remind', [InvoiceController::class, 'remind']);

        // Device tokens (push notifications)
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
    });
});
