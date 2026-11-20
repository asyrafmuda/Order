<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';
    case Rider = 'rider';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Super Admin',
            self::Manager => 'Outlet Manager',
            self::Cashier => 'Cashier',
            self::Waiter => 'Waiter',
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
     * Roles that work at one outlet: everyone but super admins and customers.
     *
     * @return array<int, self>
     */
    public static function outletStaff(): array
    {
        return [self::Manager, self::Cashier, self::Waiter, self::Kitchen, self::Rider];
    }

    /**
     * The route a user with this role lands on after signing in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin, self::Manager => 'admin.dashboard',
            self::Cashier => 'counter.index',
            self::Waiter => 'pos.index',
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
