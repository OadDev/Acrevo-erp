<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Administration', 'code' => 'ADMIN'],
            ['name' => 'Sales', 'code' => 'SALES'],
            ['name' => 'Marketing', 'code' => 'MARKETING'],
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Executive Operations', 'code' => 'EXECUTIVE'],
            ['name' => 'Quality Control', 'code' => 'QC'],
            ['name' => 'Finance', 'code' => 'FINANCE'],
            ['name' => 'Management', 'code' => 'MANAGEMENT'],
            ['name' => 'Legal', 'code' => 'LEGAL'],
            ['name' => 'Auditing', 'code' => 'AUDIT'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(['code' => $department['code']], $department);
        }
    }
}
