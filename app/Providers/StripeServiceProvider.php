<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class StripeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $secret = config('services.stripe.secret');

        if ($secret) {
            \Stripe\Stripe::setApiKey($secret);
        }
    }
}
