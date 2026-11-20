<?php

namespace App\Models;

use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One branch of the business. Orders, tables, delivery areas, staff and
 * sold-out items belong to an outlet; the menu and customers are shared.
 */
#[Fillable(['name', 'slug', 'is_default', 'is_active', 'sort_order'])]
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
