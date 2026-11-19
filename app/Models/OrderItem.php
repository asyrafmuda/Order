<?php

namespace App\Models;

use App\Enums\KitchenItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id', 'product_id', 'product_variant_id', 'kitchen_station_id', 'product_code', 'name',
    'variant_name', 'combo_name', 'combo_choice', 'combo_price', 'base_price', 'unit_price', 'quantity',
    'line_total', 'notes', 'kitchen_status', 'started_at', 'done_at',
])]
class OrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kitchen_status' => KitchenItemStatus::class,
            'started_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<KitchenStation, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }

    /** @return HasMany<OrderItemModifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
