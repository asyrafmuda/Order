<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'kitchen_station_id' => $this->kitchen_station_id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'price' => $this->price,
            'from_price' => $this->from_price,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_orderable' => $this->status->isOrderable(),
            'badge' => $this->badge,
            'tags' => $this->tags ?? [],
            'is_combo_eligible' => $this->is_combo_eligible,
            'is_upsell' => $this->is_upsell,
            'prep_minutes' => $this->prep_minutes,
            'sort_order' => $this->sort_order,
            'pos_linked' => filled($this->pos_item_id),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'price' => $variant->price,
                'is_default' => $variant->is_default,
            ])->all()),
            'modifier_groups' => $this->whenLoaded('modifierGroups', fn () => ModifierGroupResource::collection($this->modifierGroups)->resolve($request)),
        ];
    }
}
