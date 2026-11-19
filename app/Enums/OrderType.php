<?php

namespace App\Enums;

enum OrderType: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';
    case DineIn = 'dine_in';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Self Pickup',
            self::Delivery => 'Delivery',
            self::DineIn => 'Dine-in',
        };
    }

    /**
     * Types a customer can choose when ordering online. Dine-in orders are
     * only keyed in by staff at the counter.
     *
     * @return array<int, self>
     */
    public static function online(): array
    {
        return [self::Pickup, self::Delivery];
    }
}
