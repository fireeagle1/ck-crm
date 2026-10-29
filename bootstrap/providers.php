<?php

use App\Providers\AppServiceProvider;
use App\Providers\PassportServiceProvider;
use App\Providers\StripeServiceProvider;

return [
    AppServiceProvider::class,
    PassportServiceProvider::class,
    StripeServiceProvider::class,
    \Mews\Purifier\PurifierServiceProvider::class,
    \Barryvdh\DomPDF\ServiceProvider::class,
];
