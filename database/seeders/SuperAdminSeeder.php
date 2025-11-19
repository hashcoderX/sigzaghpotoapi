<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'sudharma.droid@gmail.com';
        $password = 'ChangeMeSecure123';

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            $user = new User();
            $user->email = $email;
        }

        $user->name = $user->name ?: 'Super Admin';
        $user->role = 'super';
        $user->active = true;
        // Always set password to provided one (idempotent but resets on re-seed)
        $user->password = Hash::make($password);

        if (!$user->currency) {
            $user->currency = 'USD';
        }

        $user->save();
    }
}
