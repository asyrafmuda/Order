<?php

namespace Database\Factories;

use App\Models\KitchenStation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KitchenStation>
 */
class KitchenStationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' Station';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
