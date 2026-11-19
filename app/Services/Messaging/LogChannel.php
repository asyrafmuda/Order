<?php

namespace App\Services\Messaging;

use App\Contracts\MessageChannel;
use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the application log instead of sending them.
 */
class LogChannel implements MessageChannel
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phone, string $message): void
    {
        Log::info("[message to {$phone}] {$message}");
    }
}
