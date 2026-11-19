<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Available = 'available';
    case SoldOut = 'sold_out';
    case ComingSoon = 'coming_soon';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::SoldOut => 'Sold Out',
            self::ComingSoon => 'Coming Soon',
            self::Hidden => 'Hidden',
        };
    }

    public function isOrderable(): bool
    {
        return $this === self::Available;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
