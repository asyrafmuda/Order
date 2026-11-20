<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed everything needed to run the shop. Sample order history is
     * separate: php artisan db:seed --class=DemoOrderSeeder
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MenuSeeder::class,
            DeliveryZoneSeeder::class,
            DiningTableSeeder::class,
        ]);
    }
}
