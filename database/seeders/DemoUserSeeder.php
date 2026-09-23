<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::firstOrCreate(
            ['email' => 'demo@geethanworks.in'],
            [
                'name' => 'Demo Account',
                'designation' => 'Demo',
                'password' => Hash::make('GeethanDemo@2026'),
                'email_verified_at' => now(),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        $demo->syncRoles(['Demo']);
    }
}
