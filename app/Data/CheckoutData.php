<?php

namespace App\Data;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Models\DiningTable;
use App\Models\User;

/**
 * Validated order details, from the online checkout form or the counter POS.
 */
final readonly class CheckoutData
{
    public function __construct(
        public string $customerName,
        public ?string $customerPhone,
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
        public OrderSource $source = OrderSource::Online,
        /** Staff member keying the order in at the counter. */
        public ?User $placedBy = null,
        /** Counter sales only: the customer already paid at the till. */
        public bool $paidAtCounter = false,
        /** Dine-in: the table the food goes to. */
        public ?DiningTable $table = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            customerName: $input['customer_name'] ?? '',
            customerPhone: $input['customer_phone'] ?? null,
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
            table: $input['table'] ?? null,
        );
    }
}
