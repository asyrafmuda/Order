<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'price', 'choices', 'is_active'])]
class ComboOffer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'choices' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The combo currently offered on the storefront, if any.
     */
    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first();
    }
}
