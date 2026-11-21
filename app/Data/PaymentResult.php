<?php

namespace App\Data;

/**
 * What the gateway says about one payment.
 */
final readonly class PaymentResult
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    /** Not finished yet (still on the bank's page, or the bank has not answered). */
    public const PENDING = 'pending';

    /**
     * @param  array<string, mixed>  $payload  what the gateway returned, kept on the payment
     */
    public function __construct(
        public string $status,
        public ?string $transactionId = null,
        public array $payload = [],
    ) {}

    public static function paid(?string $transactionId, array $payload = []): self
    {
        return new self(self::PAID, $transactionId, $payload);
    }

    public static function failed(array $payload = []): self
    {
        return new self(self::FAILED, null, $payload);
    }

    public static function pending(array $payload = []): self
    {
        return new self(self::PENDING, null, $payload);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }
}
