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
| cPanel Integration Redirects (legacy WHMCS links)
|--------------------------------------------------------------------------
*/
Route::get('/integration/index.html', [\App\Http\Controllers\CpanelIntegrationController::class, 'redirect'])->name('cpanel.integration');

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
    Route::post('/services/{service}/sso/cpanel', [Portal\SsoController::class, 'cpanel'])->name('services.sso.cpanel');
    Route::post('/services/{service}/sso/webmail', [Portal\SsoController::class, 'webmail'])->name('services.sso.webmail');

    // Tickets
    Route::get('/tickets', [Portal\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [Portal\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [Portal\TicketController::class, 'store'])->middleware('throttle:10,1')->name('tickets.store');
    Route::get('/tickets/{ticket}', [Portal\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [Portal\TicketController::class, 'reply'])->middleware('throttle:20,1')->name('tickets.reply');
    Route::post('/tickets/{ticket}/close', [Portal\TicketController::class, 'close'])->name('tickets.close');

    // Domains
    Route::get('/domains', [Portal\DomainController::class, 'index'])->name('domains.index');

    // Projects
    Route::get('/projects', [Portal\ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [Portal\ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/documents/{document}/download', [Portal\ProjectController::class, 'downloadDocument'])->name('projects.documents.download');
    Route::post('/projects/{project}/approvals/{approval}/approve', [Portal\ProjectApprovalController::class, 'approve'])->name('projects.approvals.approve');
    Route::post('/projects/{project}/approvals/{approval}/reject', [Portal\ProjectApprovalController::class, 'reject'])->name('projects.approvals.reject');

    // Knowledgebase
    Route::get('/knowledgebase', [Portal\KnowledgebaseController::class, 'index'])->name('knowledgebase.index');
    Route::get('/knowledgebase/{article}', [Portal\KnowledgebaseController::class, 'show'])->name('knowledgebase.show');

    // Account
    Route::get('/account', [Portal\AccountController::class, 'show'])->name('account.show');
    Route::put('/account', [Portal\AccountController::class, 'update'])->name('account.update');
    Route::put('/account/company', [Portal\AccountController::class, 'updateCompany'])->name('account.company.update');
    Route::post('/account/users', [Portal\AccountController::class, 'addUser'])->middleware('throttle:5,1')->name('account.users.add');
    Route::post('/account/users/{user}/reset-password', [Portal\AccountController::class, 'sendPasswordReset'])->middleware('throttle:5,1')->name('account.users.reset-password');

    // Upgrade / Service Requests
    Route::get('/upgrade-request', [Portal\UpgradeRequestController::class, 'show'])->name('upgrade-request.show');
    Route::post('/upgrade-request', [Portal\UpgradeRequestController::class, 'store'])->middleware('throttle:5,1')->name('upgrade-request.store');

    // Billing
    Route::post('/billing/portal', [Portal\BillingController::class, 'portal'])->name('billing.portal');
    Route::get('/invoices', [Portal\BillingController::class, 'invoices'])->name('invoices.index');

    // Shop
    Route::get('/shop', [Portal\ShopController::class, 'index'])->name('shop.index');
    Route::get('/shop/{product}', [Portal\ShopController::class, 'show'])->name('shop.show');
    Route::get('/cart', [Portal\CartController::class, 'index'])->name('cart.index');
    Route::get('/cart/checkout', [Portal\CartController::class, 'showCheckout'])->name('cart.showCheckout');
    Route::post('/cart/checkout', [Portal\CartController::class, 'checkout'])->name('cart.checkout');
    Route::post('/cart/{product}', [Portal\CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/{index}', [Portal\CartController::class, 'remove'])->name('cart.remove');
    Route::put('/cart/{index}/quantity', [Portal\CartController::class, 'updateQuantity'])->name('cart.updateQuantity');
    Route::get('/orders', [Portal\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Portal\OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/download-invoice', [Portal\OrderController::class, 'downloadInvoice'])->name('orders.downloadInvoice');
    Route::get('/orders/booking/{booking}/download-confirmation', [Portal\OrderController::class, 'downloadBookingConfirmation'])->name('orders.downloadBookingConfirmation');

    // Bookings (AJAX availability endpoints)
    Route::post('/bookings/check-availability', [Portal\BookingController::class, 'checkAvailability'])->name('bookings.checkAvailability');
    Route::get('/bookings/unavailable-dates', [Portal\BookingController::class, 'getUnavailableDates'])->name('bookings.unavailableDates');

    // Inspection photos
    Route::get('/inspection-photo/{path}', [Portal\InspectionPhotoController::class, 'show'])->where('path', '.*')->name('inspection-photo');
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
    Route::get('/customers/{customer}/scorecard', [Portal\ScorecardController::class, 'adminScorecard'])->name('customers.scorecard');

    // Services
    Route::get('/services', [Admin\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/create', [Admin\ServiceController::class, 'create'])->name('services.create');
    Route::get('/services/cpanel-mapping', [Admin\CpanelMappingController::class, 'index'])->name('services.cpanel-mapping');
    Route::put('/services/cpanel-mapping', [Admin\CpanelMappingController::class, 'update'])->name('services.cpanel-mapping.update');
    Route::get('/services/stripe-mapping', [Admin\StripeMappingController::class, 'index'])->name('services.stripe-mapping');
    Route::put('/services/stripe-mapping', [Admin\StripeMappingController::class, 'update'])->name('services.stripe-mapping.update');
    Route::put('/services/stripe-mapping/subscriptions', [Admin\StripeMappingController::class, 'updateSubscriptions'])->name('services.stripe-mapping.subscriptions');
    Route::post('/services', [Admin\ServiceController::class, 'store'])->name('services.store');
    Route::get('/services/{service}', [Admin\ServiceController::class, 'show'])->name('services.show');
    Route::get('/services/{service}/edit', [Admin\ServiceController::class, 'edit'])->name('services.edit');
    Route::put('/services/{service}', [Admin\ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{service}', [Admin\ServiceController::class, 'destroy'])->name('services.destroy');

    // Communications
    Route::get('/communications', [Admin\CommunicationController::class, 'index'])->name('communications.index');
    Route::post('/communications/preview', [Admin\CommunicationController::class, 'preview'])->name('communications.preview');
    Route::post('/communications/send', [Admin\CommunicationController::class, 'send'])->name('communications.send');

    // Tickets
    Route::get('/tickets', [Admin\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [Admin\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [Admin\TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [Admin\TicketController::class, 'show'])->name('tickets.show');
    Route::put('/tickets/{ticket}', [Admin\TicketController::class, 'update'])->name('tickets.update');
    Route::post('/tickets/{ticket}/reply', [Admin\TicketController::class, 'reply'])->name('tickets.reply');

    // Invoices
    Route::get('/invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [Admin\OneOffInvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [Admin\OneOffInvoiceController::class, 'store'])->name('invoices.store');
    Route::post('/invoices/{invoice}/remind', [Admin\InvoiceController::class, 'remind'])->name('invoices.remind');

    // Assets (CMDB)
    Route::resource('assets', Admin\AssetController::class);
    Route::get('assets/{asset}/label', [Admin\AssetController::class, 'label'])->name('assets.label');
    Route::get('assets/{asset}/label-download', [Admin\AssetController::class, 'labelDownload'])->name('assets.label-download');

    // Projects
    Route::resource('projects', Admin\ProjectController::class);
    Route::post('/projects/{project}/reopen', [Admin\ProjectController::class, 'reopen'])->name('projects.reopen');

    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::resource('tasks', Admin\ProjectTaskController::class)->except(['show']);
        Route::post('tasks/reorder', [Admin\ProjectTaskController::class, 'reorder'])->name('tasks.reorder');
        Route::resource('documents', Admin\ProjectDocumentController::class)->only(['store', 'destroy']);
        Route::post('comments', [Admin\ProjectCommentController::class, 'store'])->name('comments.store');
        Route::resource('decisions', Admin\ProjectDecisionController::class)->except(['show']);
        Route::post('approvals/document/{document}', [Admin\ProjectApprovalController::class, 'requestDocumentApproval'])->name('approvals.document');
        Route::post('approvals/completion', [Admin\ProjectApprovalController::class, 'requestCompletionApproval'])->name('approvals.completion');
    });

    // Domains
    Route::get('/domains', [Admin\DomainController::class, 'index'])->name('domains.index');
    Route::get('/domains/create', [Admin\DomainController::class, 'create'])->name('domains.create');
    Route::post('/domains', [Admin\DomainController::class, 'store'])->name('domains.store');
    Route::get('/domains/{domain}/edit', [Admin\DomainController::class, 'edit'])->name('domains.edit');
    Route::put('/domains/{domain}', [Admin\DomainController::class, 'update'])->name('domains.update');
    Route::delete('/domains/{domain}', [Admin\DomainController::class, 'destroy'])->name('domains.destroy');

    // Knowledgebase Articles
    Route::resource('articles', Admin\ArticleController::class)->except('show');

    // Users
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/impersonate', [Admin\UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{user}/toggle-lock', [Admin\UserController::class, 'toggleLock'])->name('users.toggle-lock');
    Route::post('/impersonate/stop', [Admin\UserController::class, 'stopImpersonating'])->name('impersonate.stop');

    // Import — REMOVED for security (SSRF risk from arbitrary database connections)
    // Route::post('/settings/import', [Admin\ImportController::class, 'run'])->name('import.run');

    // Cleanup
    Route::get('/cleanup', [Admin\CleanupController::class, 'index'])->name('cleanup.index');
    Route::post('/cleanup/services', [Admin\CleanupController::class, 'deleteServices'])->name('cleanup.delete-services');
    Route::post('/cleanup/domains', [Admin\CleanupController::class, 'deleteDomains'])->name('cleanup.delete-domains');
    Route::get('/cleanup/review', [Admin\ReviewWizardController::class, 'index'])->name('cleanup.review');
    Route::post('/cleanup/review/delete', [Admin\ReviewWizardController::class, 'bulkDelete'])->name('cleanup.review.delete');
    Route::post('/cleanup/review/move-to-domain', [Admin\ReviewWizardController::class, 'moveToDomain'])->name('cleanup.review.move-to-domain');

    // Settings (with sub-pages)
    Route::get('/settings', [Admin\SettingsController::class, 'general'])->name('settings.index');
    Route::get('/settings/general', [Admin\SettingsController::class, 'general'])->name('settings.general');
    Route::put('/settings/general', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/logo', [Admin\SettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
    Route::get('/settings/import', [Admin\SettingsController::class, 'import'])->name('settings.import');
    Route::get('/settings/scheduled-tasks', [Admin\SettingsController::class, 'scheduledTasks'])->name('settings.tasks');
    Route::post('/settings/scheduled-tasks/run', [Admin\SettingsController::class, 'runTask'])->name('settings.tasks.run');
    Route::get('/settings/whm', [Admin\WhmConfigController::class, 'edit'])->name('settings.whm');
    Route::post('/settings/whm', [Admin\WhmConfigController::class, 'update'])->name('settings.whm.update');

    // Hosting Provisioning
    Route::get('/hosting/provisioning', [Admin\HostingProvisionController::class, 'index'])->name('hosting.provision.index');
    Route::post('/hosting/provisioning/{service}/provision', [Admin\HostingProvisionController::class, 'provision'])->name('hosting.provision.provision');

    // Canned Responses
    Route::resource('canned-responses', Admin\CannedResponseController::class)->except(['show']);

    // Shop Management
    Route::resource('shop/products', Admin\ShopProductController::class)->except(['show', 'destroy'])->names('shop.products');
    Route::post('shop/products/{product}/archive', [Admin\ShopProductController::class, 'archive'])->name('shop.products.archive');
    Route::post('shop/products/{product}/restore', [Admin\ShopProductController::class, 'restore'])->name('shop.products.restore');
    Route::post('shop/products/{product}/link-asset', [Admin\ShopProductController::class, 'linkAsset'])->name('shop.products.link-asset');
    Route::delete('shop/products/{product}/unlink-asset/{asset}', [Admin\ShopProductController::class, 'unlinkAsset'])->name('shop.products.unlink-asset');
    Route::get('shop/orders', [Admin\ShopOrderController::class, 'index'])->name('shop.orders.index');
    Route::get('shop/orders/{order}', [Admin\ShopOrderController::class, 'show'])->name('shop.orders.show');
    Route::post('shop/orders/{order}/fulfil', [Admin\ShopOrderController::class, 'fulfil'])->name('shop.orders.fulfil');
    Route::post('shop/orders/{order}/note', [Admin\ShopOrderController::class, 'addNote'])->name('shop.orders.note');
    Route::get('shop/orders/{order}/download-pdf', [Admin\ShopOrderController::class, 'downloadPdf'])->name('shop.orders.download-pdf');
    Route::post('shop/orders/{order}/mark-paid-offline', [Admin\ShopOrderController::class, 'markPaidOffline'])->name('shop.orders.mark-paid-offline');
    Route::post('shop/orders/{order}/cancel', [Admin\ShopOrderController::class, 'cancel'])->name('shop.orders.cancel');
    Route::post('shop/orders/{order}/refund', [Admin\ShopOrderController::class, 'refund'])->name('shop.orders.refund');
    Route::post('shop/orders/{order}/bookings/{booking}/assign-assets', [Admin\ShopOrderController::class, 'assignAssets'])->name('shop.orders.assign-assets');
    Route::post('shop/orders/{order}/bookings/{booking}/advance-stage', [Admin\ShopOrderController::class, 'advanceStage'])->name('shop.orders.advance-stage');
    Route::post('shop/orders/{order}/bookings/{booking}/inspect', [Admin\ShopOrderController::class, 'inspect'])->name('shop.orders.inspect');

    // Bookings Management
    Route::get('shop/bookings', [Admin\BookingController::class, 'index'])->name('shop.bookings.index');
    Route::get('shop/bookings/calendar', [Admin\BookingController::class, 'calendar'])->name('shop.bookings.calendar');
    Route::get('shop/bookings/create', [Admin\BookingController::class, 'create'])->name('shop.bookings.create');
    Route::post('shop/bookings', [Admin\BookingController::class, 'store'])->name('shop.bookings.store');
    Route::post('shop/bookings/block-dates', [Admin\BookingController::class, 'blockDates'])->name('shop.bookings.blockDates');
    Route::put('shop/bookings/{booking}/block', [Admin\BookingController::class, 'updateBlock'])->name('shop.bookings.updateBlock');
    Route::delete('shop/bookings/{booking}/block', [Admin\BookingController::class, 'deleteBlock'])->name('shop.bookings.deleteBlock');
    Route::get('shop/bookings/{booking}', [Admin\BookingController::class, 'show'])->name('shop.bookings.show');
    Route::patch('shop/bookings/{booking}/returned', [Admin\BookingController::class, 'markReturned'])->name('shop.bookings.markReturned');
    Route::get('shop/bookings/{booking}/download-confirmation', [Admin\BookingController::class, 'downloadConfirmation'])->name('shop.bookings.downloadConfirmation');
    Route::get('shop/bookings/inspection-photo/{path}', [Admin\BookingController::class, 'inspectionPhoto'])->where('path', '.*')->name('shop.bookings.inspectionPhoto');

    // Booking Quick Actions
    Route::post('bookings/{booking}/resend-confirmation', [Admin\BookingController::class, 'resendConfirmation'])->name('bookings.resend-confirmation');
    Route::post('bookings/{booking}/advance-stage', [Admin\BookingController::class, 'advanceStage'])->name('bookings.advance-stage');
    Route::post('bookings/{booking}/mark-returned-list', [Admin\BookingController::class, 'markReturnedFromList'])->name('bookings.mark-returned-list');
    Route::get('bookings/{booking}/inspection-report', [Admin\BookingInspectionReportController::class, 'download'])->name('bookings.inspection-report');
    Route::delete('bookings/{booking}', [Admin\BookingController::class, 'destroy'])->name('bookings.destroy');

    Route::resource('shop/tiers', Admin\CustomerTierController::class)->except(['show', 'edit', 'create'])->names('shop.tiers');

    // Discount Codes Management
    Route::resource('shop/discount-codes', Admin\DiscountCodeController::class)->except(['show'])->names('shop.discount-codes');

    // Fulfilment Queue
    Route::prefix('fulfilment')->name('fulfilment.')->group(function () {
        Route::get('/', [Admin\FulfilmentQueueController::class, 'index'])->name('index');
        Route::get('/{booking}', [Admin\FulfilmentQueueController::class, 'show'])->name('show');
        Route::post('/{booking}/assign-assets', [Admin\FulfilmentQueueController::class, 'assignAssets'])->name('assignAssets');
        Route::post('/{booking}/advance', [Admin\FulfilmentQueueController::class, 'advance'])->name('advance');
        Route::post('/{booking}/inspect', [Admin\FulfilmentQueueController::class, 'inspect'])->name('inspect');
    });

    // Customer Shop & Rental History
    Route::get('/customers/{customer}/shop', [Admin\CustomerShopController::class, 'index'])->name('customers.shop');
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

/*
|--------------------------------------------------------------------------
| Stripe Webhook (no auth, signature-verified)
|--------------------------------------------------------------------------
*/
Route::post('/stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

/*
|--------------------------------------------------------------------------
| Temporary deployment helper — DELETE AFTER USE
|--------------------------------------------------------------------------
*/
Route::get('/deploy-fix', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('package:discover');
    return 'Done: config cleared, packages discovered. DELETE THIS ROUTE NOW.';
});
