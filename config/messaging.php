<?php

use App\Services\Messaging\LogChannel;
use App\Services\Messaging\WhatsAppChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Customer Messaging Channel
    |--------------------------------------------------------------------------
    |
    | Order updates are sent to the customer's phone through this channel.
    | "log" writes the message to the application log (and notification_logs);
    | "whatsapp" sends through the WhatsApp Cloud API.
    |
    */

    'default' => env('MESSAGING_CHANNEL', 'log'),

    'drivers' => [
        'log' => LogChannel::class,
        'whatsapp' => WhatsAppChannel::class,
    ],

    'whatsapp' => [
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com/v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'timeout' => 15,
    ],

];
