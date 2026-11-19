<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zone '.fake()->unique()->randomLetter(),
            'areas' => fake()->city(),
            'fee' => 500,
            'min_order' => 1500,
            'eta_minutes' => 40,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
