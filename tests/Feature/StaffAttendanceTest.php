<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HR > Attendance is for staff roles (Sales/HR/Finance/Executive Team
 * Leader/QC Officer) - Worker attendance is entered separately, via a work
 * order's Measurement Book. Before this, the Attendance page listed ALL
 * active employees, and none of these staff roles had a linked Employee
 * record in the first place (only Worker did), so the page had nothing to
 * mark attendance against for the people it's actually meant for - which is
 * what surfaced as "attendance not saving."
 */
class StaffAttendanceTest extends TestCase
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

    public function test_the_attendance_page_lists_staff_but_not_workers(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Worker Guy', 'email' => 'worker+'.uniqid().'@example.com', 'role' => 'Worker',
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get('/attendance');
        $response->assertOk()->assertSee('HR Person')->assertDontSee('Worker Guy');
    }

    public function test_marking_staff_attendance_saves_status_and_daily_work_details(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');

        $response = $this->actingAs($admin)->post('/attendance', [
            'date' => now()->toDateString(),
            'attendance' => [$hr->employee->id => 'present'],
            'work_details' => [$hr->employee->id => 'Processed payroll for the month.'],
            'salary' => [$hr->employee->id => 800],
            'advance' => [$hr->employee->id => 100],
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $hr->employee->id, 'status' => 'present',
            'work_details' => 'Processed payroll for the month.',
            'salary' => 800, 'advance' => 100,
        ]);
    }

    public function test_a_worker_employee_id_cannot_be_marked_through_the_staff_attendance_endpoint(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Worker Guy', 'email' => 'worker+'.uniqid().'@example.com', 'role' => 'Worker',
        ])->assertRedirect();
        $worker = User::where('name', 'Worker Guy')->firstOrFail();

        $this->actingAs($admin)->post('/attendance', [
            'date' => now()->toDateString(),
            'attendance' => [$worker->employee->id => 'present'],
        ])->assertRedirect();

        $this->assertDatabaseMissing('attendances', ['employee_id' => $worker->employee->id]);
    }
}
