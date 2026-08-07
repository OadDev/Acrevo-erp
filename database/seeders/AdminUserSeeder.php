<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminDepartment = Department::where('code', 'ADMIN')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@acrevo.test'],
            [
                'employee_code' => 'EMP-0001',
                'name' => 'System Administrator',
                'department_id' => $adminDepartment?->id,
                'designation' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $admin->syncRoles(['Admin']);
    }
}
