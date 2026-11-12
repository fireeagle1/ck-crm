<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Abandoned hosting orders
    |--------------------------------------------------------------------------
    | Hours after which a pending order containing hosting items is treated as
    | abandoned by the cleanup command.
    */
    'abandoned_hosting_hours' => (int) env('SHOP_ABANDONED_HOSTING_HOURS', 48),

];
