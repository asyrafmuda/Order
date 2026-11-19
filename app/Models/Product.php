<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'category_id', 'kitchen_station_id', 'code', 'name', 'slug', 'description', 'image_path', 'price',
    'status', 'badge', 'tags', 'is_combo_eligible', 'is_upsell', 'prep_minutes', 'sort_order',
    'pos_item_id', 'pos_variant_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'tags' => 'array',
            'is_combo_eligible' => 'boolean',
            'is_upsell' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<KitchenStation, $this> */
    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsToMany<ModifierGroup, $this> */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class)->orderBy('sort_order');
    }

    /**
     * Public URL of the product photo, or null when none was uploaded.
     * Paths starting with "/" point at bundled assets in /public.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->image_path) {
                return null;
            }

            return str_starts_with($this->image_path, '/')
                ? $this->image_path
                : Storage::disk('public')->url($this->image_path);
        });
    }

    /**
     * The lowest price a customer can pay for this product.
     *
     * @return Attribute<int, never>
     */
    protected function fromPrice(): Attribute
    {
        return Attribute::get(function (): int {
            if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
                return (int) $this->variants->min('price');
            }

            return (int) $this->price;
        });
    }

    /**
     * The kitchen station that cooks this product, falling back to the
     * station configured on its category.
     */
    public function resolveStationId(): ?int
    {
        return $this->kitchen_station_id ?? $this->category?->kitchen_station_id;
    }

    /**
     * Products customers are allowed to see on the menu.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', '!=', ProductStatus::Hidden);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
