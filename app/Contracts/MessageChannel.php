<?php

namespace App\Contracts;

/**
 * A way of sending a short text message to a customer's phone.
 */
interface MessageChannel
{
    public function name(): string;

    /**
     * @param  string  $phone  Phone number in international format without "+", e.g. 60123456789
     *
     * @throws \RuntimeException when the message could not be sent
     */
    public function send(string $phone, string $message): void;
}
