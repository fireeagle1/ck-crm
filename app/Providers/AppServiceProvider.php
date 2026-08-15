<?php

namespace App\Providers;

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

        // Share cart item count with all portal views (header cart icon)
        View::composer('layouts.partials.portal-header', function ($view) {
            $cartItems = session()->get('shop_cart', []);
            $view->with('cartItemCount', count($cartItems));
        });
    }
}
