<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@eltech.local'],
            [
                'name'      => 'System Administrator',
                'password'  => Hash::make('Admin@123'),
                'is_active' => true,
            ]
        );
        $admin->assignRole('super_admin');
    }
}
