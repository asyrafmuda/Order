<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Rider = 'rider';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Cashier => 'Cashier',
            self::Kitchen => 'Kitchen Crew',
            self::Rider => 'Rider',
            self::Customer => 'Customer',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /**
     * The route a user with this role lands on after signing in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Cashier => 'counter.index',
            self::Kitchen => 'kitchen.index',
            self::Rider => 'rider.index',
            self::Customer => 'menu.index',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()], self::cases());
    }
}
