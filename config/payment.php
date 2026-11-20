<?php

use App\Services\Payments\FakeGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    |
    | The gateway used for online payments (FPX / card / e-wallet). The "fake"
    | driver simulates a hosted bank page so the whole order flow can be used
    | without a merchant account. Register real drivers in "drivers" below.
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'fake'),

    'drivers' => [
        'fake' => FakeGateway::class,
    ],

    /*
    | The fake gateway approves any payment with one click. On a live site
    | (APP_ENV=production) online payment is therefore switched off while it
    | is the gateway, unless this is set on purpose for a public demo.
    */
    'fake_in_production' => (bool) env('PAYMENT_FAKE_IN_PRODUCTION', false),

];
