<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Licensing
    |--------------------------------------------------------------------------
    |
    | Each copy sold is activated with a purchase code on the license server
    | (your own copy of this app with LICENSE_SERVER_ENABLED=true). The server
    | ties the code to one domain and returns a certificate signed with your
    | private key; the app checks it with the public key below.
    |
    | Run `php artisan license:keygen --server=https://your-license-server`
    | once to fill in server_url and public_key. While public_key is empty the
    | app runs without any license check (development, your own demos).
    |
    */

    'server_url' => env('LICENSE_SERVER_URL', ''),

    // Written by license:keygen. Never put this in .env: anyone could swap it.
    'public_key' => '',

    'product' => 'order-system',

    // A fresh certificate is fetched this often…
    'check_every_days' => 7,
    // …the admin is warned when the server has not answered for this long…
    'warn_after_days' => 14,
    // …and the site stops when it has not answered for this long.
    'lock_after_days' => 30,

    // Where the certificate is kept (it is signed, so a file is fine).
    'file' => storage_path('app/license.json'),

    'server' => [
        // Only on your own license server. The client ZIP has no server code,
        // so setting this there does nothing.
        'enabled' => (bool) env('LICENSE_SERVER_ENABLED', false),
        // base64 of the PEM private key printed by license:keygen.
        'signing_key' => env('LICENSE_SIGNING_KEY'),
    ],

];
