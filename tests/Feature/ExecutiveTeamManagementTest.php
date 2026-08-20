<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\ExecutiveTeam;
use App\Models\ExecutiveTeamMember;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveTeamManagementTest extends TestCase
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

    public function test_admin_can_remove_an_executive_team_and_it_disappears_from_the_index(): void
    {
        $admin = $this->admin();
        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-001', 'name' => 'Team A', 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/executive-teams')->assertOk()->assertSee('Team A');
        $this->actingAs($admin)->get("/executive-teams/{$team->id}")->assertOk()->assertSee('Remove');

        $this->actingAs($admin)->delete("/executive-teams/{$team->id}")->assertRedirect('/executive-teams');
        $this->assertNotNull($team->fresh()->deleted_at);

        $this->actingAs($admin)->get('/executive-teams')->assertOk()->assertDontSee('Team A');
    }

    public function test_removing_an_executive_team_still_assigned_to_a_work_order_is_blocked(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-002', 'name' => 'Team B', 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);
        WorkOrderExecutiveTeam::create([
            'work_order_id' => $workOrder->id, 'executive_team_id' => $team->id,
            'assigned_by' => $admin->id, 'assigned_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete("/executive-teams/{$team->id}");
        $response->assertStatus(422)->assertSee('This team is still assigned to 1 work order(s)', false);
        $this->assertNull($team->fresh()->deleted_at);
    }

    public function test_deleting_a_work_order_frees_up_its_executive_team_for_removal(): void
    {
        // Regression test: deleting a work order used to leave its team
        // assignment "active" (unassigned_at null) forever, since the only
        // page that could unassign it - the work order's own Team tab - is
        // gone along with the work order. That silently blocked the team
        // from ever being removed, surfacing as a bare 422 error page.
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-005', 'name' => 'Team E', 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);
        $assignment = WorkOrderExecutiveTeam::create([
            'work_order_id' => $workOrder->id, 'executive_team_id' => $team->id,
            'assigned_by' => $admin->id, 'assigned_at' => now(),
        ]);

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}")->assertRedirect();
        $this->assertNotNull($assignment->fresh()->unassigned_at);

        $this->actingAs($admin)->delete("/executive-teams/{$team->id}")->assertRedirect('/executive-teams');
        $this->assertNotNull($team->fresh()->deleted_at);
    }

    public function test_removing_a_team_with_a_legacy_dangling_assignment_to_an_already_deleted_work_order_still_works(): void
    {
        // Covers data that went bad before the fix above existed: an
        // assignment still marked active whose work order was deleted
        // directly (not through the controller), so unassigned_at was
        // never set. The removal guard must not count it.
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-006', 'name' => 'Team F', 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);
        WorkOrderExecutiveTeam::create([
            'work_order_id' => $workOrder->id, 'executive_team_id' => $team->id,
            'assigned_by' => $admin->id, 'assigned_at' => now(),
        ]);
        $workOrder->delete();

        $this->actingAs($admin)->delete("/executive-teams/{$team->id}")->assertRedirect('/executive-teams');
        $this->assertNotNull($team->fresh()->deleted_at);
    }

    public function test_a_teams_show_page_survives_a_deleted_work_order_assignment(): void
    {
        // Regression test: WorkOrderController::destroy() soft-deletes the work
        // order but leaves the executive team's assignment row in place, so
        // $assignment->workOrder resolves to null - the show page crashed
        // building a route() with it instead of showing a "Deleted" fallback.
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-004', 'name' => 'Team D', 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);
        WorkOrderExecutiveTeam::create([
            'work_order_id' => $workOrder->id, 'executive_team_id' => $team->id,
            'assigned_by' => $admin->id, 'assigned_at' => now(),
        ]);

        $workOrder->delete();

        $this->withoutExceptionHandling();
        $this->actingAs($admin)->get("/executive-teams/{$team->id}")->assertOk()->assertSee('Deleted work order');
    }

    public function test_a_removed_teams_members_no_longer_500_the_employee_page(): void
    {
        $admin = $this->admin();
        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-003', 'name' => 'Team C', 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);
        $employee = Employee::create([
            'name' => 'Worker One', 'employee_code' => 'EMP-001', 'department_id' => Department::first()->id,
            'designation' => 'Mason', 'employment_type' => 'permanent', 'status' => 'active', 'created_by' => $admin->id,
        ]);
        ExecutiveTeamMember::create(['executive_team_id' => $team->id, 'employee_id' => $employee->id, 'joined_at' => now()]);

        $this->actingAs($admin)->delete("/executive-teams/{$team->id}")->assertRedirect();

        $this->withoutExceptionHandling();
        $this->actingAs($admin)->get("/employees/{$employee->id}")->assertOk()->assertSee('Removed team');
    }
}
