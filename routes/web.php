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
    // Onboarding (must be before the onboarding middleware gate)
    Route::get('/onboarding', [Portal\OnboardingController::class, 'show'])->name('onboarding.show');
    Route::put('/onboarding', [Portal\OnboardingController::class, 'update'])->name('onboarding.update');

    Route::get('/dashboard', [Portal\DashboardController::class, 'index'])->name('dashboard');

    // Services
    Route::get('/services', [Portal\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [Portal\ServiceController::class, 'show'])->name('services.show');

    // Tickets
    Route::get('/tickets', [Portal\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [Portal\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [Portal\TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [Portal\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [Portal\TicketController::class, 'reply'])->name('tickets.reply');

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
    Route::get('/invoices', [Portal\BillingController::class, 'invoices'])->name('invoices.index');
});

/*
|--------------------------------------------------------------------------
| Admin Panel (admin-only users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', EnsureIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', Admin\SearchController::class)->name('search');

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
    Route::post('/tickets/{ticket}/reply', [Admin\TicketController::class, 'reply'])->name('tickets.reply');

    // Invoices
    Route::get('/invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');

    // Assets (CMDB)
    Route::resource('assets', Admin\AssetController::class);

    // Domains
    Route::get('/domains', [Admin\DomainController::class, 'index'])->name('domains.index');
    Route::get('/domains/create', [Admin\DomainController::class, 'create'])->name('domains.create');
    Route::post('/domains', [Admin\DomainController::class, 'store'])->name('domains.store');
    Route::get('/domains/{domain}/edit', [Admin\DomainController::class, 'edit'])->name('domains.edit');
    Route::put('/domains/{domain}', [Admin\DomainController::class, 'update'])->name('domains.update');

    // Knowledgebase Articles
    Route::resource('articles', Admin\ArticleController::class)->except('show');

    // Users
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/impersonate', [Admin\UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/impersonate/stop', [Admin\UserController::class, 'stopImpersonating'])->name('impersonate.stop');

    // Import
    Route::post('/settings/import', [Admin\ImportController::class, 'run'])->name('import.run');

    // Settings (with sub-pages)
    Route::get('/settings', [Admin\SettingsController::class, 'general'])->name('settings.index');
    Route::get('/settings/general', [Admin\SettingsController::class, 'general'])->name('settings.general');
    Route::put('/settings/general', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/logo', [Admin\SettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
    Route::get('/settings/import', [Admin\SettingsController::class, 'import'])->name('settings.import');
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
