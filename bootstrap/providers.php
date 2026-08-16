<?php

use App\Providers\AppServiceProvider;
use App\Providers\StripeServiceProvider;

return [
    AppServiceProvider::class,
    StripeServiceProvider::class,
    \Mews\Purifier\PurifierServiceProvider::class,
    \Barryvdh\DomPDF\ServiceProvider::class,
];
