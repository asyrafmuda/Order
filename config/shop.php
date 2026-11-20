<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shop Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for the settings an admin can change at runtime under
    | Admin → Settings. Stored overrides live in the "settings" table and are
    | read through App\Support\Settings.
    |
    */

    'defaults' => [
        'shop.name' => 'Boss Burger',
        'shop.tagline' => 'Solo Grill',
        'shop.address' => 'Jl. Veteran No. 88, Serengan',
        'shop.phone' => '+60 12-345 6789',
        'shop.whatsapp' => '60123456789',
        'shop.maps_url' => 'https://maps.google.com/?q=Boss+Burger',
        'shop.counter' => 'Counter 2',

        // Branding: everything a different shop would want to change to
        // make the storefront its own. Edited under Admin → Settings.
        'brand.icon' => '🍔',
        'brand.logo_path' => null,
        'brand.primary_color' => '#ee2b32',
        'brand.accent_color' => '#ffb938',
        'brand.footer_text' => 'Sizzling smash patties, melted cheddar and bold midnight street grill vibes, crafted fresh daily.',
        'brand.badge_text' => '100% Halal kitchen',

        'hours.open' => '12:00',
        'hours.close' => '23:30',
        'hours.last_order' => '23:00',

        'ordering.enabled' => true,
        'ordering.pickup_enabled' => true,
        'ordering.delivery_enabled' => true,
        // Customers scan the QR code on their table and order from their seat.
        'ordering.dine_in_enabled' => true,
        'ordering.pickup_eta_min' => 15,
        'ordering.pickup_eta_max' => 20,
        'ordering.queue_prefix' => 'A',
        // Ready pickup orders nobody marked as collected are closed after this many hours.
        'ordering.auto_close_hours' => 6,

        // Payment methods offered at online checkout.
        'payments.fpx_enabled' => true,
        'payments.card_enabled' => true,
        'payments.ewallet_enabled' => true,
        'payments.cash_enabled' => true,
        // Pay-at-counter orders reach the kitchen unpaid, so they are the
        // easiest to abuse. These two limits keep prank orders small.
        'payments.cash_requires_account' => true,
        'payments.cash_max_amount' => 15000,
        // Table orders may be paid at the counter without an account: the
        // customer is sitting in the shop.
        'payments.dine_in_pay_at_counter' => true,

        // One-tap kitchen instructions on the counter POS.
        'pos.quick_notes' => ['Pedas', 'Kurang pedas', 'Potong dua', 'Sos asing', 'Bungkus asing', 'Tak nak ais'],

        'tax.rate' => 6,
        'tax.inclusive' => true,

        'notifications.enabled' => true,
    ],

];
