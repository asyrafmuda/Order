<?php

namespace App\Enums;

enum OrderType: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Self Pickup',
            self::Delivery => 'Delivery',
        };
    }
}
