<?php

namespace App\Data;

/**
 * A gateway callback normalised to what the application needs to know.
 */
final readonly class PaymentCallback
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $reference,
        public bool $successful,
        public ?string $transactionId = null,
        public array $payload = [],
    ) {}
}
