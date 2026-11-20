<?php

namespace App\Models;

use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'token', 'seats', 'sort_order', 'is_active'])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table) {
            $table->token ??= self::newToken();
        });
    }

    /**
     * The value printed in the table's QR code. Regenerating it makes old
     * printed codes stop working.
     */
    public static function newToken(): string
    {
        return Str::lower(Str::random(16));
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @param  Builder<DiningTable>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<DiningTable>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
