<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payroll used to be one undifferentiated list. It's now split into
 * "WO Workers Payroll" (unregistered workers or Worker-role users,
 * attendance from a work order's M.Book - unchanged) and "Employee
 * Payroll" (Sales/HR/Finance/Executive Team Leader/QC Officer, attendance
 * from HR > Attendance) - same page, same generate/pay/PDF mechanics,
 * scoped to a different set of employees.
 */
class EmployeePayrollTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $admin->syncRoles(['Admin']);

        return $admin;
    }

    private function staffUser(User $admin, string $role): User
    {
        $this->actingAs($admin)->post('/admin/users', [
            'name' => "{$role} Person", 'email' => strtolower(str_replace(' ', '', $role)).'+'.uniqid().'@example.com', 'role' => $role,
        ])->assertRedirect();

        return User::where('name', "{$role} Person")->firstOrFail();
    }

    public function test_employee_payroll_page_lists_staff_but_not_wo_workers(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');
        $worker = Employee::create([
            'employee_code' => 'EMP-'.uniqid(), 'name' => 'Site Worker', 'status' => 'active',
            'department_id' => Department::first()->id, 'employment_type' => 'permanent',
            'salary_type' => 'daily', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/payroll/employees');
        $response->assertOk()->assertSee('Employee Payroll')->assertSee('HR Person')->assertDontSee('Site Worker');

        $workerResponse = $this->actingAs($admin)->get('/payroll');
        $workerResponse->assertOk()->assertSee('Site Worker')->assertDontSee('HR Person');
    }

    public function test_payroll_can_be_generated_from_hr_attendance_for_a_staff_employee(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');

        Attendance::create([
            'employee_id' => $hr->employee->id, 'date' => now(), 'status' => 'present',
            'work_details' => 'Reviewed applications', 'salary' => 1000, 'advance' => 50, 'marked_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post('/payroll/generate-from-attendance', [
            'employee_id' => $hr->employee->id, 'month' => now()->month, 'year' => now()->year,
        ])->assertRedirect();

        $payroll = Payroll::where('employee_id', $hr->employee->id)
            ->where('month', now()->month)->where('year', now()->year)->firstOrFail();
        $this->assertEquals(1000, $payroll->basic_salary);
        $this->assertEquals(950, $payroll->net_salary);

        $response = $this->actingAs($admin)->get('/payroll/employees?month='.now()->month.'&year='.now()->year);
        $response->assertOk()->assertSee('Reviewed applications')->assertSee('HR Person');
    }
}
