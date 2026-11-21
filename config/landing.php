<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sales page (/web)
    |--------------------------------------------------------------------------
    |
    | A page that sells this system, for the seller's own demo site. Off by
    | default, so customers' shops never show it: set LANDING_PAGE=true in
    | the demo site's .env.
    |
    */

    'enabled' => (bool) env('LANDING_PAGE', false),

    'product' => env('LANDING_PRODUCT', 'OrderLa'),

    // Shown in the price box. Empty: "Hubungi kami untuk harga".
    'price' => env('LANDING_PRICE', 'RM 2,500'),
    'price_note' => env('LANDING_PRICE_NOTE', 'Bayar sekali · 1 domain · tiada yuran bulanan'),

    // Contact buttons appear only for what is set. WhatsApp: digits with country code, e.g. 60123456789.
    'whatsapp' => env('LANDING_WHATSAPP'),
    'email' => env('LANDING_EMAIL'),

    // Where "Cuba demo" leads. Empty: this site's own storefront.
    'demo_url' => env('LANDING_DEMO_URL'),

    // Staff accounts shown as demo logins, when they exist here with the password below.
    'demo_password' => env('LANDING_DEMO_PASSWORD', 'password'),
    'demo_accounts' => [
        'admin@bossburger.test' => 'Super admin',
        'cashier@bossburger.test' => 'Cashier (POS)',
        'kitchen@bossburger.test' => 'Dapur',
        'rider@bossburger.test' => 'Rider',
    ],

];
