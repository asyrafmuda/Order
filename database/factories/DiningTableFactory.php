<?php

namespace Database\Factories;

use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    private static int $number = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = ++self::$number;

        return [
            'name' => 'Table '.$number,
            'seats' => 4,
            'sort_order' => $number,
            'is_active' => true,
        ];
    }
}
