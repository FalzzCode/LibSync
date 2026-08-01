<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Akun demo hanya untuk instalasi lokal, bukan production.
        if (app()->environment('local')) {
            User::query()->firstOrCreate(
                ['email' => 'admin@perpustakaan.test'],
                ['name' => 'Administrator', 'password' => 'password123', 'role' => 'admin']
            );

            User::query()->firstOrCreate(
                ['email' => 'staff@perpustakaan.test'],
                ['name' => 'Petugas Perpustakaan', 'password' => 'password123', 'role' => 'staff']
            );
        }

        foreach ([
            'fine_per_day' => '1000',
            'max_active_loans' => '3',
            'default_loan_days' => '7',
            'activation_code_days' => '14',
            'auto_unblock_enabled' => '1',
        ] as $key => $value) {
            SystemSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
