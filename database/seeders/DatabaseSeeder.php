<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create the first admin only if there are no users yet.
        // Override defaults with BOOTSTRAP_ADMIN_USER / BOOTSTRAP_ADMIN_PASS env vars.
        if (User::count() === 0) {
            User::create([
                'username' => env('BOOTSTRAP_ADMIN_USER', 'admin'),
                'password' => Hash::make(env('BOOTSTRAP_ADMIN_PASS', 'Admin1234')),
                'role'     => 'admin',
            ]);
        }
    }
}
