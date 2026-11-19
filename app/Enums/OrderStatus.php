<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting Payment',
            self::Confirmed => 'Confirmed',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::OutForDelivery => 'Out for Delivery',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Statuses this one may move to. Every status change in the application
     * goes through App\Actions\Orders\TransitionOrder, which enforces this map.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Ready, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::OutForDelivery, self::Completed, self::Cancelled],
            self::OutForDelivery => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * Orders the kitchen still has to work on.
     *
     * @return array<int, self>
     */
    public static function kitchenQueue(): array
    {
        return [self::Confirmed, self::Preparing];
    }

    /**
     * Orders that are paid for / accepted and not yet finished.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Confirmed, self::Preparing, self::Ready, self::OutForDelivery];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
