<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting Rider',
            self::Assigned => 'Rider Assigned',
            self::PickedUp => 'On the Way',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        };
    }
}
