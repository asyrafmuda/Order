<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kitchen_station_id' => $this->kitchen_station_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'eyebrow' => $this->eyebrow,
            'tagline' => $this->tagline,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'products_count' => $this->whenCounted('products'),
            'products' => $this->whenLoaded('products', fn () => ProductResource::collection($this->products)->resolve($request)),
        ];
    }
}
