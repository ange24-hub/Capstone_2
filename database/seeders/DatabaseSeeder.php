<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (Barangay::TOMAS_OPPUS_BARANGAYS as $name) {
            Barangay::updateOrCreate(
                ['name' => $name],
                ['municipality' => Barangay::MUNICIPALITY]
            );
        }

        $barangay = Barangay::where('name', 'San Isidro')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'municipal@example.com'],
            [
                'name' => 'Municipal Admin',
                'role' => User::ROLE_MUNICIPAL_LGU,
                'password' => Hash::make('Password123!'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'barangay@example.com'],
            [
                'name' => 'Barangay Staff',
                'staff_id' => 'TO-SI-001',
                'role' => User::ROLE_BARANGAY,
                'barangay_id' => $barangay->id,
                'password' => Hash::make('Password123!'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'resident@example.com'],
            [
                'name' => 'Resident User',
                'role' => User::ROLE_RESIDENT,
                'password' => Hash::make('Password123!'),
            ]
        );
    }
}
