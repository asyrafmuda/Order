<?php

namespace App\Services\Cart;

use App\Models\ComboOffer;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * A cart line resolved against the current menu, with prices in sen.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PricedLine implements Arrayable
{
    /**
     * @param  Collection<int, Modifier>  $modifiers
     */
    public function __construct(
        public string $id,
        public Product $product,
        public ?ProductVariant $variant,
        public Collection $modifiers,
        public ?ComboOffer $combo,
        public ?string $comboChoice,
        public int $quantity,
        public ?string $notes,
    ) {}

    public function basePrice(): int
    {
        return $this->variant?->price ?? $this->product->price;
    }

    public function modifiersTotal(): int
    {
        return (int) $this->modifiers->sum('price');
    }

    public function comboPrice(): int
    {
        return $this->combo?->price ?? 0;
    }

    public function unitPrice(): int
    {
        return $this->basePrice() + $this->modifiersTotal() + $this->comboPrice();
    }

    public function lineTotal(): int
    {
        return $this->unitPrice() * $this->quantity;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product->id,
            'code' => $this->product->code,
            'name' => $this->product->name,
            'image_url' => $this->product->image_url,
            'variant_name' => $this->variant?->name,
            'modifiers' => $this->modifiers
                ->map(fn (Modifier $modifier) => ['name' => $modifier->name, 'price' => $modifier->price])
                ->values()
                ->all(),
            'combo' => $this->combo ? [
                'name' => $this->combo->name,
                'choice' => $this->comboChoice,
                'price' => $this->combo->price,
            ] : null,
            'notes' => $this->notes,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice(),
            'line_total' => $this->lineTotal(),
        ];
    }
}
