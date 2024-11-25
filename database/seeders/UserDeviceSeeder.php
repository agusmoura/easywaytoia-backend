<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /* crea un usuario con el rol de superadmin */
        User::create([
            'username' => config('auth.admin.username'),
            'email' => config('auth.admin.email'),
            'password' => Hash::make(config('auth.admin.password')),
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);
    }
}
