<?php

namespace Database\Factories;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city().' Outlet';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
