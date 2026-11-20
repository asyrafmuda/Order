<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use Illuminate\Database\Seeder;

class DiningTableSeeder extends Seeder
{
    /**
     * Eight tables to start with. Each gets its own QR code; print them from
     * Admin → Tables & QR.
     */
    public function run(): void
    {
        foreach (range(1, 8) as $number) {
            DiningTable::query()->firstOrCreate(['name' => 'Table '.$number], [
                'seats' => $number <= 6 ? 4 : 6,
                'sort_order' => $number,
                'is_active' => true,
            ]);
        }
    }
}
