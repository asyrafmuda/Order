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

];
