<?php

namespace App\Services\Messaging;

use App\Contracts\MessageChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp Cloud API (Meta). Free-form text can only be delivered inside the
 * 24-hour customer service window; outside it Meta requires an approved
 * message template.
 */
class WhatsAppChannel implements MessageChannel
{
    /**
     * @param  array{base_url: string, phone_number_id: ?string, access_token: ?string, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'whatsapp';
    }

    public function send(string $phone, string $message): void
    {
        if (blank($this->config['phone_number_id']) || blank($this->config['access_token'])) {
            throw new RuntimeException('WhatsApp is not configured. Set WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ACCESS_TOKEN.');
        }

        $response = Http::baseUrl($this->config['base_url'])
            ->withToken($this->config['access_token'])
            ->timeout($this->config['timeout'])
            ->post("/{$this->config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $message],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp API error: '.($response->json('error.message') ?? $response->body()));
        }
    }
}
