<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\ModifierGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prices arrive as integer sen.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', Rule::exists(Category::class, 'id')],
            'kitchen_station_id' => ['nullable', Rule::exists(KitchenStation::class, 'id')],
            'code' => ['nullable', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'badge' => ['nullable', 'string', 'max:40'],
            'tags' => ['array', 'max:8'],
            'tags.*' => ['string', 'max:80'],
            'is_combo_eligible' => ['boolean'],
            'is_upsell' => ['boolean'],
            'prep_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],

            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['boolean'],

            'variants' => ['array', 'max:12'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'variants.*.price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'variants.*.is_default' => ['boolean'],

            'modifier_group_ids' => ['array'],
            'modifier_group_ids.*' => ['integer', Rule::exists(ModifierGroup::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'kitchen_station_id' => 'kitchen station',
            'variants.*.name' => 'variant name',
            'variants.*.price' => 'variant price',
        ];
    }

    /**
     * Column values for the products table.
     *
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        return [
            ...$this->safe()->only([
                'category_id', 'kitchen_station_id', 'code', 'name', 'description', 'price', 'status', 'badge',
                'prep_minutes', 'sort_order',
            ]),
            'kitchen_station_id' => $this->validated('kitchen_station_id'),
            'tags' => array_values(array_filter(array_map('trim', $this->validated('tags', [])))),
            'is_combo_eligible' => $this->boolean('is_combo_eligible'),
            'is_upsell' => $this->boolean('is_upsell'),
        ];
    }
}
