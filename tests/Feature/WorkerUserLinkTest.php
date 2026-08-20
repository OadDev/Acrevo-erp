<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\ExecutiveTeam;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User accounts (login + role) and Employee records (HR/Worker profile,
 * attendance, executive team membership) were two entirely separate models
 * with only an optional link (Employee::user_id) that nothing ever set.
 * That broke three things at once: a User created with the Worker role
 * never showed up in HR > Workers (Employee-based list), Executive Team
 * membership could add any Employee regardless of role, and a Worker's own
 * dashboard could never show their attendance since $user->employee was
 * always null. These tests cover the fix: creating/updating a Worker User
 * now syncs a linked Employee record automatically.
 */
class WorkerUserLinkTest extends TestCase
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

    public function test_creating_a_worker_user_shows_them_in_the_hr_workers_list(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Ravi Kumar',
            'email' => 'ravi+'.uniqid().'@example.com',
            'phone' => '9999900000',
            'department_id' => Department::first()->id,
            'designation' => 'Mason',
            'role' => 'Worker',
        ])->assertRedirect();

        $user = User::where('name', 'Ravi Kumar')->firstOrFail();
        $employee = $user->employee;

        $this->assertNotNull($employee, 'Creating a Worker User should auto-create a linked Employee record.');
        $this->assertSame('active', $employee->status);
        $this->assertSame('Mason', $employee->designation);

        $this->actingAs($admin)->get('/employees')->assertOk()->assertSee('Ravi Kumar');
    }

    public function test_creating_a_user_with_a_role_outside_hr_tracking_does_not_create_an_employee(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Marketing Person',
            'email' => 'marketing+'.uniqid().'@example.com',
            'role' => 'Marketing',
        ])->assertRedirect();

        $user = User::where('name', 'Marketing Person')->firstOrFail();
        $this->assertNull($user->employee);
    }

    /**
     * HR > Attendance is for Sales/HR/Finance/Executive Team Leader/QC
     * Officer staff (Worker attendance goes through a work order's M.Book
     * instead), so those five roles get a linked Employee record too, the
     * same as Worker.
     */
    public function test_creating_a_user_with_a_staff_role_also_creates_a_linked_employee(): void
    {
        $admin = $this->admin();

        foreach (['Sales', 'HR', 'Finance', 'Executive Team Leader', 'QC Officer'] as $role) {
            $this->actingAs($admin)->post('/admin/users', [
                'name' => "{$role} Person",
                'email' => strtolower(str_replace(' ', '', $role)).'+'.uniqid().'@example.com',
                'role' => $role,
            ])->assertRedirect();

            $user = User::where('name', "{$role} Person")->firstOrFail();
            $this->assertNotNull($user->employee, "Creating a {$role} User should auto-create a linked Employee record.");
        }
    }

    public function test_editing_a_worker_user_keeps_the_linked_employee_in_sync(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Ravi Kumar', 'email' => 'ravi+'.uniqid().'@example.com', 'role' => 'Worker',
        ])->assertRedirect();
        $user = User::where('name', 'Ravi Kumar')->firstOrFail();

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => 'Ravi Kumar', 'email' => $user->email, 'designation' => 'Senior Mason',
            'department_id' => Department::first()->id, 'role' => 'Worker', 'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('Senior Mason', $user->employee->fresh()->designation);
        $this->assertSame(1, Employee::count(), 'Editing should update the same Employee row, not create a second one.');
    }

    public function test_executive_team_member_picker_offers_both_registered_and_unregistered_workers_but_no_other_roles(): void
    {
        $admin = $this->admin();

        // A registered worker (Worker-role User, auto-linked Employee) - eligible.
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Worker One', 'email' => 'w1+'.uniqid().'@example.com', 'role' => 'Worker',
        ])->assertRedirect();
        $workerUser = User::where('name', 'Worker One')->firstOrFail();

        // An unregistered worker - added via "Add Worker" only, no login at all - still eligible.
        $unregisteredWorker = Employee::create([
            'name' => 'Unregistered Worker', 'department_id' => Department::first()->id,
            'employment_type' => 'permanent', 'status' => 'active', 'salary_type' => 'monthly', 'created_by' => $admin->id,
        ]);

        // A User linked to an Employee but without the Worker role - not eligible.
        $hrUser = User::create([
            'name' => 'HR Person', 'email' => 'hr+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $hrUser->syncRoles(['HR']);
        Employee::create([
            'user_id' => $hrUser->id, 'name' => 'HR Person', 'department_id' => Department::first()->id,
            'employment_type' => 'permanent', 'status' => 'active', 'salary_type' => 'monthly', 'created_by' => $admin->id,
        ]);

        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-001', 'name' => 'Team A', 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/executive-teams/{$team->id}");
        $response->assertOk()->assertSee('Worker One')->assertSee('Unregistered Worker')->assertDontSee('HR Person');

        // Server-side guard: the HR-linked employee is rejected even via direct POST.
        $hrEmployee = Employee::where('name', 'HR Person')->firstOrFail();
        $this->actingAs($admin)->post("/executive-teams/{$team->id}/members", [
            'employee_id' => $hrEmployee->id,
        ])->assertStatus(422);

        $this->actingAs($admin)->post("/executive-teams/{$team->id}/members", [
            'employee_id' => $unregisteredWorker->id,
        ])->assertRedirect();
        $this->assertTrue($team->fresh()->members()->where('employee_id', $unregisteredWorker->id)->exists());

        $this->actingAs($admin)->post("/executive-teams/{$team->id}/members", [
            'employee_id' => $workerUser->employee->id,
        ])->assertRedirect();
        $this->assertTrue($team->fresh()->members()->where('employee_id', $workerUser->employee->id)->exists());
    }

    public function test_any_employee_linked_login_sees_payroll_on_their_dashboard(): void
    {
        $admin = $this->admin();

        // Any login linked to an Employee record - not just Workers - sees
        // their own attendance/payroll widget, since Sales/HR/Finance/QC
        // Officer now get Employee Payroll from HR Attendance too.
        $hrUser = User::create([
            'name' => 'HR Person', 'email' => 'hr+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $hrUser->syncRoles(['HR']);
        Employee::create([
            'user_id' => $hrUser->id, 'name' => 'HR Person', 'department_id' => Department::first()->id,
            'employment_type' => 'permanent', 'status' => 'active', 'salary_type' => 'monthly', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($hrUser)->get('/dashboard');
        $response->assertOk()->assertSee('My Attendance')->assertSee("This Month's Payroll", false);
    }

    public function test_a_workers_attendance_entered_via_the_wo_measurement_book_shows_on_their_own_dashboard(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Ravi Kumar', 'email' => 'ravi+'.uniqid().'@example.com', 'role' => 'Worker',
        ])->assertRedirect();
        $workerUser = User::where('name', 'Ravi Kumar')->firstOrFail();
        $employee = $workerUser->employee;

        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        Attendance::create([
            'employee_id' => $employee->id, 'work_order_id' => $workOrder->id, 'date' => now()->toDateString(),
            'status' => 'present', 'hours_worked' => 8, 'marked_by' => $admin->id,
        ]);

        $response = $this->actingAs($workerUser)->get('/dashboard');
        $response->assertOk()->assertSee($workOrder->work_order_no);
    }
}
