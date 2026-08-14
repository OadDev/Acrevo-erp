<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\ExecutiveTeam;
use App\Models\Quotation;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderWorkflowTest extends TestCase
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

    public function test_work_orders_index_loads_with_and_without_a_site(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get('/work-orders');
        $response->assertOk();
    }

    public function test_approving_a_quotation_creates_a_site_and_work_order_requires_it(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'address' => 'Addr', 'city' => 'City', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'sent', 'total_amount' => 100, 'created_by' => $admin->id]);

        $this->assertNull($quotation->fresh()->site);

        $this->actingAs($admin)->post("/quotations/{$quotation->id}/approve")->assertRedirect();

        $site = $quotation->fresh()->site;
        $this->assertNotNull($site);
        $this->assertStringStartsWith('ST-', $site->site_no);

        $response = $this->actingAs($admin)->get("/work-orders/create?quotation_id={$quotation->id}");
        $response->assertOk();
        $response->assertSee($site->site_no);
    }

    public function test_work_order_show_renders_with_and_without_a_linked_site(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        // Legacy-style work order with no quotation/site (data created before the Site feature existed).
        $legacyWorkOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'Old WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'pending_hr_assignment', 'created_by' => $admin->id,
        ]);

        // activity_log.subject_id must be wide enough for WorkOrder's UUID primary key -
        // regression test for the "Data truncated for column 'subject_id'" MySQL error.
        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', WorkOrder::class)
            ->where('subject_id', $legacyWorkOrder->id)
            ->first();
        $this->assertNotNull($activity, 'WorkOrder creation should have been logged with its full UUID as subject_id.');

        $this->actingAs($admin)->get("/work-orders/{$legacyWorkOrder->id}")->assertOk();

        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'quotation_id' => $quotation->id, 'site_id' => $site->id, 'client_id' => $client->id, 'title' => 'WO',
            'execution_way' => 'way_1', 'priority' => 'medium', 'enquiry_id' => $enquiry->id, 'type' => 'new',
            'status' => 'pending_hr_assignment', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk();
        $response->assertSee($site->site_no);
    }

    public function test_generate_portal_access_creates_a_working_login(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'portal@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->post("/clients/{$client->id}/portal-access");
        $response->assertRedirect(route('clients.show', $client));

        $login = ClientLogin::where('client_id', $client->id)->first();
        $this->assertNotNull($login);
        $this->assertTrue($login->user->hasRole('Client'));

        // Repeat calls must not fail (e.g. re-clicking the button).
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertStatus(422);
    }

    public function test_admin_cannot_assign_the_client_role_from_the_generic_user_form(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => 'Client',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_sites_index_and_show_list_work_orders_for_that_site(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'quotation_id' => $quotation->id, 'site_id' => $site->id, 'client_id' => $client->id, 'title' => 'WO',
            'execution_way' => 'way_1', 'priority' => 'medium', 'enquiry_id' => $enquiry->id, 'type' => 'new',
            'status' => 'pending_hr_assignment', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/sites')->assertOk()->assertSee($site->site_no);

        $response = $this->actingAs($admin)->get("/sites/{$site->id}");
        $response->assertOk();
        $response->assertSee($workOrder->work_order_no);
    }

    public function test_admin_can_manually_add_a_second_site_for_a_client(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        Site::create(['client_id' => $client->id, 'address' => 'First site', 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->post('/sites', [
            'client_id' => $client->id,
            'address' => 'Second site',
        ]);

        $response->assertRedirect();
        $this->assertSame(2, $client->fresh()->sites()->count());
        $this->assertDatabaseHas('sites', ['client_id' => $client->id, 'address' => 'Second site', 'quotation_id' => null]);
    }

    public function test_admin_can_delete_a_user_but_not_themselves_or_the_last_admin(): void
    {
        $admin = $this->admin();
        $other = User::create([
            'name' => 'Other', 'email' => 'other+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $other->syncRoles(['Sales']);

        $this->actingAs($admin)->delete("/admin/users/{$other->id}")->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $other->id]);

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    public function test_work_order_show_survives_a_soft_deleted_team_leader(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'pending_hr_assignment', 'created_by' => $admin->id,
        ]);

        $leader = User::create([
            'name' => 'Leader', 'email' => 'leader+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $leader->id, 'is_active' => true]);
        WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);

        // Deleting the leader (soft delete) must not break rendering of work orders their team is on.
        $leader->delete();

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}")->assertOk();

        // Nor should it have been possible to delete them in the first place while they lead a team.
        $leader->restore();
        $this->actingAs($admin)->delete("/admin/users/{$leader->id}")->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $leader->id, 'deleted_at' => null]);
    }

    public function test_work_order_moves_to_qc_pending_when_marked_completed_by_the_team(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/submit-for-qc")->assertRedirect();

        $this->assertSame('qc_pending', $workOrder->fresh()->status);
    }

    public function test_site_can_only_be_marked_completed_once_its_work_orders_are_done(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'quotation_id' => $quotation->id, 'site_id' => $site->id, 'client_id' => $client->id, 'title' => 'WO',
            'execution_way' => 'way_1', 'priority' => 'medium', 'enquiry_id' => $enquiry->id, 'type' => 'new',
            'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/sites/{$site->id}/complete")->assertSessionHasErrors('site');
        $this->assertSame('active', $site->fresh()->status);

        $workOrder->update(['status' => 'completed']);

        $this->actingAs($admin)->post("/sites/{$site->id}/complete")->assertRedirect();
        $this->assertSame('completed', $site->fresh()->status);
        $this->assertNotNull($site->fresh()->completed_at);

        // A completed site can't have new work orders added to it.
        $this->actingAs($admin)->get("/work-orders/create?quotation_id={$quotation->id}")->assertStatus(422);
    }

    public function test_work_order_creation_can_assign_an_executive_team_leader_up_front(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $leader = User::create([
            'name' => 'Leader', 'email' => 'leader+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $leader->syncRoles(['Executive Team Leader']);
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $leader->id, 'is_active' => true]);

        $createResponse = $this->actingAs($admin)->get("/work-orders/create?quotation_id={$quotation->id}");
        $createResponse->assertOk()->assertSee($leader->name);

        $storeResponse = $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id,
            'site_id' => $site->id,
            'client_id' => $client->id,
            'title' => 'WO with team',
            'execution_way' => 'way_1',
            'team_leader_id' => $leader->id,
            'priority' => 'medium',
        ]);
        $storeResponse->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with team')->firstOrFail();
        $this->assertSame('team_assigned', $workOrder->status);
        $this->assertTrue($workOrder->executiveTeams()->where('executive_team_id', $team->id)->exists());
    }

    public function test_work_order_creation_rejects_a_team_leader_with_no_active_team(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $leader = User::create([
            'name' => 'Leaderless', 'email' => 'leaderless+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $leader->syncRoles(['Executive Team Leader']);

        $response = $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id,
            'site_id' => $site->id,
            'client_id' => $client->id,
            'title' => 'WO without a team',
            'execution_way' => 'way_1',
            'team_leader_id' => $leader->id,
            'priority' => 'medium',
        ]);

        $response->assertSessionHasErrors('team_leader_id');
        $this->assertDatabaseMissing('work_orders', ['title' => 'WO without a team']);
    }

    public function test_unassigning_a_team_works(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'team_assigned', 'created_by' => $admin->id,
        ]);
        $leader = User::create([
            'name' => 'Leader', 'email' => 'leader+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $leader->id, 'is_active' => true]);
        $assignment = WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/unassign-team/{$assignment->id}")->assertRedirect();

        $this->assertNotNull($assignment->fresh()->unassigned_at);
    }
}
