<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'User',
        //     'fullname' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        User::updateOrCreate(
        ['email' => 'superadmin@lsp.com'],
        [
                'name'          => 'superadmin',
                'fullname'      => 'Super Administrator LSP',
                'place'         => 'Jakarta',
                'date_of_birth' => '1995-01-01',
                'phone'         => '081234567890',
                'role'          => 'super_admin',
                'password'      => Hash::make(env('SEED_SUPERADMIN_PASSWORD', 'ganti-password-ini')),
                'email_verified_at' => now(),
            ]
    );
    }
}
