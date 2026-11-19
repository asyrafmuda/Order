<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['Zone A — Nearby', 'Within 3 km of the shop', 500, 1500, 35],
            ['Zone B — Town', '3 – 6 km from the shop', 800, 2500, 45],
            ['Zone C — Outskirts', '6 – 10 km from the shop', 1200, 4000, 60],
        ];

        foreach ($zones as $index => [$name, $areas, $fee, $minOrder, $eta]) {
            DeliveryZone::query()->updateOrCreate(['name' => $name], [
                'areas' => $areas,
                'fee' => $fee,
                'min_order' => $minOrder,
                'eta_minutes' => $eta,
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
