<?php

use App\Providers\AppServiceProvider;
use App\Providers\McpServiceProvider;
use App\Providers\PassportServiceProvider;
use App\Providers\StripeServiceProvider;

return [
    AppServiceProvider::class,
    McpServiceProvider::class,
    PassportServiceProvider::class,
    StripeServiceProvider::class,
    \Mews\Purifier\PurifierServiceProvider::class,
    \Barryvdh\DomPDF\ServiceProvider::class,
];
