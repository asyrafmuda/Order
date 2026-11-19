<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(5, 40) * 100 + 90,
            'status' => ProductStatus::Available,
            'tags' => [],
            'is_combo_eligible' => false,
            'is_upsell' => false,
            'prep_minutes' => 10,
            'sort_order' => 0,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::SoldOut]);
    }

    public function comboEligible(): static
    {
        return $this->state(fn () => ['is_combo_eligible' => true]);
    }
}
