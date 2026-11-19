<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Point of Sale Provider
    |--------------------------------------------------------------------------
    |
    | Paid orders are pushed to the POS as receipts and the menu can be pulled
    | from it. "fake" keeps everything local and is the default for local
    | development and testing; "loyverse" talks to the Loyverse REST API.
    |
    */

    'default' => env('POS_PROVIDER', 'fake'),

    'drivers' => [
        'fake' => App\Services\Pos\FakePosProvider::class,
        'loyverse' => App\Services\Pos\LoyversePosProvider::class,
    ],

    'loyverse' => [
        'base_url' => env('LOYVERSE_BASE_URL', 'https://api.loyverse.com/v1.0'),
        'access_token' => env('LOYVERSE_ACCESS_TOKEN'),
        'store_id' => env('LOYVERSE_STORE_ID'),
        'payment_type_id' => env('LOYVERSE_PAYMENT_TYPE_ID'),
        'webhook_secret' => env('LOYVERSE_WEBHOOK_SECRET'),
        'timeout' => 15,
    ],

];
