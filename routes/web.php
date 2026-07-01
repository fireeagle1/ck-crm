<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Portal;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Customer Portal (authenticated users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/dashboard', [Portal\DashboardController::class, 'index'])->name('dashboard');

    // Services
    Route::get('/services', [Portal\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [Portal\ServiceController::class, 'show'])->name('services.show');

    // Tickets
    Route::get('/tickets', [Portal\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [Portal\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [Portal\TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [Portal\TicketController::class, 'show'])->name('tickets.show');

    // Domains
    Route::get('/domains', [Portal\DomainController::class, 'index'])->name('domains.index');

    // Knowledgebase
    Route::get('/knowledgebase', [Portal\KnowledgebaseController::class, 'index'])->name('knowledgebase.index');
    Route::get('/knowledgebase/{article}', [Portal\KnowledgebaseController::class, 'show'])->name('knowledgebase.show');

    // Account
    Route::get('/account', [Portal\AccountController::class, 'show'])->name('account.show');
    Route::put('/account', [Portal\AccountController::class, 'update'])->name('account.update');

    // Billing
    Route::post('/billing/portal', [Portal\BillingController::class, 'portal'])->name('billing.portal');
});

/*
|--------------------------------------------------------------------------
| Admin Panel (admin-only users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', EnsureIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Customers
    Route::resource('customers', Admin\CustomerController::class);

    // Services
    Route::get('/services', [Admin\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/create', [Admin\ServiceController::class, 'create'])->name('services.create');
    Route::post('/services', [Admin\ServiceController::class, 'store'])->name('services.store');

    // Tickets
    Route::get('/tickets', [Admin\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [Admin\TicketController::class, 'show'])->name('tickets.show');
    Route::put('/tickets/{ticket}', [Admin\TicketController::class, 'update'])->name('tickets.update');

    // Assets (CMDB)
    Route::resource('assets', Admin\AssetController::class);

    // Knowledgebase Articles
    Route::resource('articles', Admin\ArticleController::class)->except('show');

    // Users
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/impersonate', [Admin\UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/impersonate/stop', [Admin\UserController::class, 'stopImpersonating'])->name('impersonate.stop');

    // Settings
    Route::get('/settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/logo', [Admin\SettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
});

/*
|--------------------------------------------------------------------------
| Breeze Profile Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
