<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ensure a demo user exists for testing login
        if (!User::where('email', 'demo@example.com')->exists()) {
            User::create([
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => 'Secret123!', // hashed via casts in User model
                'role' => 'photographer',
            ]);
        }
    }
}
