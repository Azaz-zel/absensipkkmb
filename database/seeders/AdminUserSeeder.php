<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@pkkmb.test'],
            [
                'name' => 'Admin PKKMB',
                'username' => 'admin',
                'password' => 'admin123',
                'role' => 'admin',
                'status' => 'aktif',
            ]
        );
    }
}
