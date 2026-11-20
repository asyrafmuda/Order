<?php

namespace App\Models;

use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One branch of the business. Orders, tables, delivery areas, staff and
 * sold-out items belong to an outlet; the menu and customers are shared.
 */
#[Fillable(['name', 'slug', 'qr_token', 'is_default', 'is_active', 'sort_order'])]
class Outlet extends Model
{
    /** @use HasFactory<OutletFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Outlet $outlet) {
            $outlet->qr_token ??= self::newQrToken();
        });
    }

    /**
     * The value in the outlet's shop QR code: the one on the counter that
     * customers scan to order on the spot. Regenerating it makes printed
     * copies stop working.
     */
    public static function newQrToken(): string
    {
        return Str::lower(Str::random(16));
    }

    /**
     * The outlet everything belongs to when multi-outlet is switched off.
     * One always exists: the migration creates it.
     */
    public static function default(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('id')->first()
            ?? static::query()->create(['name' => 'Main Outlet', 'slug' => 'main', 'is_default' => true]);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<DiningTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    /** @return HasMany<DeliveryZone, $this> */
    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    /** @return HasMany<User, $this> */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Menu items this outlet has run out of.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function soldOutProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'outlet_sold_out_products');
    }

    /**
     * @param  Builder<Outlet>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Outlet>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name');
    }
}
