<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Fpx = 'fpx';
    case Card = 'card';
    case Ewallet = 'ewallet';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Fpx => 'FPX Online Banking',
            self::Card => 'Credit / Debit Card',
            self::Ewallet => 'E-Wallet',
            self::Cash => 'Cash at Counter',
        };
    }

    /**
     * Online methods are paid through the payment gateway before the order
     * reaches the kitchen; cash is collected by the cashier at hand-over.
     */
    public function isOnline(): bool
    {
        return $this !== self::Cash;
    }
}
