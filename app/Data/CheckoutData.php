<?php

namespace App\Data;

use App\Enums\PaymentMethod;

/**
 * Validated checkout form input.
 */
final readonly class CheckoutData
{
    public function __construct(
        public string $customerName,
        public string $customerPhone,
        public ?string $customerEmail,
        public PaymentMethod $paymentMethod,
        public ?string $notes = null,
        public ?string $recipientName = null,
        public ?string $recipientPhone = null,
        public ?string $line1 = null,
        public ?string $line2 = null,
        public ?string $city = null,
        public ?string $postcode = null,
        public ?string $deliveryNotes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            customerName: $input['customer_name'],
            customerPhone: $input['customer_phone'],
            customerEmail: $input['customer_email'] ?? null,
            paymentMethod: PaymentMethod::from($input['payment_method']),
            notes: $input['notes'] ?? null,
            recipientName: $input['recipient_name'] ?? null,
            recipientPhone: $input['recipient_phone'] ?? null,
            line1: $input['line1'] ?? null,
            line2: $input['line2'] ?? null,
            city: $input['city'] ?? null,
            postcode: $input['postcode'] ?? null,
            deliveryNotes: $input['delivery_notes'] ?? null,
        );
    }
}
