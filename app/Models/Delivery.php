<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'delivery_zone_id', 'rider_id', 'status', 'recipient_name', 'phone', 'line1', 'line2',
    'city', 'postcode', 'notes', 'fee', 'assigned_at', 'picked_up_at', 'delivered_at',
])]
class Delivery extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<DeliveryZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    /** @return BelongsTo<Rider, $this> */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::get(fn (): string => implode(', ', array_filter([
            $this->line1,
            $this->line2,
            trim($this->postcode.' '.$this->city),
        ])));
    }
}
