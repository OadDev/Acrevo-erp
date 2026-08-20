<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExecutiveTeam;
use App\Models\ExecutiveTeamMember;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
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

    public function test_a_worker_with_no_history_can_be_permanently_removed(): void
    {
        $admin = $this->admin();
        $employee = Employee::create([
            'employee_code' => 'EMP-'.uniqid(), 'name' => 'Fresh Worker', 'status' => 'active',
            'department_id' => Department::first()->id, 'designation' => 'Helper',
            'employment_type' => 'permanent', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/employees/{$employee->id}")->assertOk()->assertSee('Remove');

        $this->actingAs($admin)->delete("/employees/{$employee->id}/remove")->assertRedirect('/employees');
        $this->assertNotNull($employee->fresh()->deleted_at);

        $this->actingAs($admin)->get('/employees')->assertOk()->assertDontSee('Fresh Worker');
    }

    public function test_a_worker_with_attendance_or_payroll_history_cannot_be_permanently_removed(): void
    {
        $admin = $this->admin();
        $employee = Employee::create([
            'employee_code' => 'EMP-'.uniqid(), 'name' => 'Veteran Worker', 'status' => 'active',
            'department_id' => Department::first()->id, 'designation' => 'Mason',
            'employment_type' => 'permanent', 'created_by' => $admin->id,
        ]);
        Attendance::create(['employee_id' => $employee->id, 'date' => now(), 'status' => 'present']);

        $response = $this->actingAs($admin)->delete("/employees/{$employee->id}/remove");
        $response->assertStatus(422)->assertSee('attendance, payroll, or benefit history', false);
        $this->assertNull($employee->fresh()->deleted_at);
    }

    public function test_removing_a_worker_clears_their_active_executive_team_membership_without_crashing_the_team_page(): void
    {
        $admin = $this->admin();
        $employee = Employee::create([
            'employee_code' => 'EMP-'.uniqid(), 'name' => 'Team Member', 'status' => 'active',
            'department_id' => Department::first()->id, 'designation' => 'Mason',
            'employment_type' => 'permanent', 'created_by' => $admin->id,
        ]);
        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-901', 'name' => 'Squad', 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);
        ExecutiveTeamMember::create(['executive_team_id' => $team->id, 'employee_id' => $employee->id, 'joined_at' => now()]);

        $this->actingAs($admin)->delete("/employees/{$employee->id}/remove")->assertRedirect('/employees');
        $this->assertNotNull($employee->fresh()->deleted_at);

        $this->withoutExceptionHandling();
        $this->actingAs($admin)->get("/executive-teams/{$team->id}")->assertOk()->assertSee('No members assigned yet.');
    }
}
