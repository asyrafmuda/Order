<?php

namespace App\Services\Cart;

use App\Enums\OrderType;
use App\Models\DeliveryZone;
use App\Support\Money;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * The cart with every total calculated server-side. This is the single source
 * of truth for what an order costs.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PricedCart implements Arrayable
{
    /**
     * @param  Collection<int, PricedLine>  $lines
     */
    public function __construct(
        public Collection $lines,
        public OrderType $type,
        public ?DeliveryZone $zone,
        public float $taxRate,
        public bool $taxInclusive,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines->isEmpty();
    }

    public function count(): int
    {
        return (int) $this->lines->sum('quantity');
    }

    public function subtotal(): int
    {
        return (int) $this->lines->sum(fn (PricedLine $line) => $line->lineTotal());
    }

    public function deliveryFee(): int
    {
        return $this->type === OrderType::Delivery ? (int) ($this->zone?->fee ?? 0) : 0;
    }

    /**
     * SST is charged on food only, not on the delivery fee.
     */
    public function taxAmount(): int
    {
        return Money::tax($this->subtotal(), $this->taxRate, $this->taxInclusive);
    }

    public function total(): int
    {
        return $this->subtotal()
            + $this->deliveryFee()
            + ($this->taxInclusive ? 0 : $this->taxAmount());
    }

    /**
     * How much more must be added to reach the delivery zone's minimum order.
     */
    public function minOrderShortfall(): int
    {
        if ($this->type !== OrderType::Delivery || ! $this->zone) {
            return 0;
        }

        return max(0, $this->zone->min_order - $this->subtotal());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => $this->lines->map->toArray()->values()->all(),
            'count' => $this->count(),
            'type' => $this->type->value,
            'zone' => $this->zone ? [
                'id' => $this->zone->id,
                'name' => $this->zone->name,
                'fee' => $this->zone->fee,
                'min_order' => $this->zone->min_order,
                'eta_minutes' => $this->zone->eta_minutes,
            ] : null,
            'subtotal' => $this->subtotal(),
            'delivery_fee' => $this->deliveryFee(),
            'tax_rate' => $this->taxRate,
            'tax_inclusive' => $this->taxInclusive,
            'tax_amount' => $this->taxAmount(),
            'total' => $this->total(),
            'min_order_shortfall' => $this->minOrderShortfall(),
        ];
    }
}
