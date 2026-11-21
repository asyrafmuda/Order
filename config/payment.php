<?php

use App\Services\Payments\BayarcashGateway;
use App\Services\Payments\BillplzGateway;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\StripeGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | The shop owner picks the gateway in Admin → Payment Gateway. This is only
    | the starting value before they do (PAYMENT_GATEWAY in .env).
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'fake'),

    'drivers' => [
        'billplz' => BillplzGateway::class,
        'bayarcash' => BayarcashGateway::class,
        'stripe' => StripeGateway::class,
        'fake' => FakeGateway::class,
    ],

    /*
    | The fake gateway approves any payment with one click. On a live site
    | (APP_ENV=production) online payment is therefore switched off while it
    | is the gateway, unless this is set on purpose for a public demo.
    */
    'fake_in_production' => (bool) env('PAYMENT_FAKE_IN_PRODUCTION', false),

];
