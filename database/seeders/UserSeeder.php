<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * One account per role. Every password is "password" — change them
     * before going live.
     */
    public function run(): void
    {
        $accounts = [
            ['Boss Admin', 'admin@bossburger.test', '60120000001', UserRole::Admin],
            ['Counter Crew', 'cashier@bossburger.test', '60120000002', UserRole::Cashier],
            ['Grill Master', 'kitchen@bossburger.test', '60120000003', UserRole::Kitchen],
            ['Rider Amir', 'rider@bossburger.test', '60120000004', UserRole::Rider],
            ['Rider Faiz', 'rider2@bossburger.test', '60120000005', UserRole::Rider],
            ['Ahmad Faiz', 'customer@bossburger.test', '60123456789', UserRole::Customer],
        ];

        foreach ($accounts as [$name, $email, $phone, $role]) {
            $user = User::query()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'phone' => $phone,
                'role' => $role,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            if ($role === UserRole::Rider) {
                $user->rider()->updateOrCreate([], [
                    'vehicle_type' => 'Motorcycle',
                    'plate_number' => 'WXY '.(1000 + $user->id),
                    'is_available' => true,
                ]);
            }
        }
    }
}
