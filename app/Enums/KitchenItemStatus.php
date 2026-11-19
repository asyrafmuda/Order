<?php

namespace App\Enums;

enum KitchenItemStatus: string
{
    case Queued = 'queued';
    case Cooking = 'cooking';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Cooking => 'Cooking',
            self::Done => 'Done',
        };
    }
}
