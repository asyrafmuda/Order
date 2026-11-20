<?php

namespace App\Support;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\User;

/**
 * Which payment methods an online customer may use for a given order, and
 * why a method is unavailable when it is not.
 */
class PaymentOptions
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array<int, array{value: string, label: string, available: bool, reason: ?string}>
     */
    public function for(OrderType $type, ?User $customer, int $total): array
    {
        $options = [];

        foreach (PaymentMethod::cases() as $method) {
            if (! $this->settings->bool("payments.{$method->value}_enabled")) {
                continue;
            }

            $reason = $this->unavailableReason($method, $type, $customer, $total);

            $options[] = [
                'value' => $method->value,
                'label' => $method->label(),
                'available' => $reason === null,
                'reason' => $reason,
            ];
        }

        return $options;
    }

    /**
     * Null when the method can be used, otherwise a sentence for the customer.
     */
    public function unavailableReason(PaymentMethod $method, OrderType $type, ?User $customer, int $total): ?string
    {
        if (! $this->settings->bool("payments.{$method->value}_enabled")) {
            return "{$method->label()} is not available right now.";
        }

        if ($method->isOnline()) {
            return null;
        }

        if ($type === OrderType::Delivery) {
            return 'Pickup orders only';
        }

        // Seated customers pay at the counter like any walk-in.
        if ($type === OrderType::DineIn) {
            return $this->settings->bool('payments.dine_in_pay_at_counter') ? null : 'Please pay online for table orders';
        }

        if ($this->settings->bool('payments.cash_requires_account') && ! $customer) {
            return 'Sign in to pay at the counter';
        }

        $limit = $this->settings->int('payments.cash_max_amount');

        if ($limit > 0 && $total > $limit) {
            return 'Orders above '.Money::format($limit).' must be paid online';
        }

        return null;
    }
}
