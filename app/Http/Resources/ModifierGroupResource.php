<?php

namespace App\Http\Resources;

use App\Models\ModifierGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModifierGroup
 */
class ModifierGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'sort_order' => $this->sort_order,
            'products_count' => $this->whenCounted('products'),
            'modifiers' => $this->whenLoaded('modifiers', fn () => $this->modifiers->map(fn ($modifier) => [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'price' => $modifier->price,
                'is_available' => $modifier->is_available,
            ])->all()),
        ];
    }
}
