<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Product;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\View\Composers\PortalNavigationComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Trust Cloudflare proxies so Laravel sees the correct protocol
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Register model observers
        Product::observe(ProductObserver::class);
        Order::observe(OrderObserver::class);

        // Share cart item count with all portal views (header cart icon)
        View::composer('layouts.partials.portal-header', function ($view) {
            $cartItems = session()->get('shop_cart', []);
            $view->with('cartItemCount', count($cartItems));
        });

        // Share portal navigation visibility flags based on customer data
        View::composer('layouts.partials.portal-header', PortalNavigationComposer::class);
    }
}
