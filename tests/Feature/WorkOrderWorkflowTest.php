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

    // Has full site_records.manage / daily_checklist.manage / etc. access to
    // create entries, but is not Admin - used to prove edit/delete is refused.
    // Call this only after admin() has already seeded roles/permissions in
    // the same test - reseeding here would duplicate the Role rows.
    private function executiveTeamLeader(): User
    {
        $user = User::create([
            'name' => 'Team Leader', 'email' => 'leader+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Executive Team Leader']);

        return $user;
    }

    // Call only after admin() has already seeded roles/permissions in the
    // same test - reseeding here would duplicate the Role rows.
    private function finance(): User
    {
        $user = User::create([
            'name' => 'Finance User', 'email' => 'finance+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Finance']);

        return $user;
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

    public function test_creating_a_user_with_a_previously_deleted_email_restores_that_account(): void
    {
        $admin = $this->admin();
        $old = User::create([
            'name' => 'Old Name', 'email' => 'reused@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $old->syncRoles(['Sales']);
        $old->delete();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Name', 'email' => 'reused@example.com', 'role' => 'HR',
        ]);
        $response->assertRedirect(route('admin.users.index'));

        $restored = User::where('email', 'reused@example.com')->firstOrFail();
        $this->assertSame($old->id, $restored->id);
        $this->assertSame('New Name', $restored->name);
        $this->assertTrue($restored->hasRole('HR'));
        $this->assertFalse($restored->hasRole('Sales'));
        $this->assertNull($restored->deleted_at);
    }

    public function test_creating_a_user_with_an_email_already_in_active_use_is_blocked(): void
    {
        $admin = $this->admin();
        User::create([
            'name' => 'Existing', 'email' => 'taken@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Duplicate', 'email' => 'taken@example.com', 'role' => 'HR',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_generating_portal_access_with_a_previously_deleted_email_restores_that_account(): void
    {
        $admin = $this->admin();
        $old = User::create([
            'name' => 'Old Employee', 'email' => 'reused-client@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $old->delete();

        $client = Client::create(['name' => 'C', 'email' => 'reused-client@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->post("/clients/{$client->id}/portal-access");
        $response->assertRedirect(route('clients.show', $client));

        $login = ClientLogin::where('client_id', $client->id)->first();
        $this->assertNotNull($login);
        $this->assertSame($old->id, $login->user_id);
        $this->assertTrue($login->user->hasRole('Client'));
        $this->assertNull($login->user->fresh()->deleted_at);
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

    public function test_qc_index_and_show_survive_a_soft_deleted_inspector(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'qc_pending', 'created_by' => $admin->id,
        ]);

        $inspector = User::create([
            'name' => 'Inspector', 'email' => 'inspector+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $inspection = \App\Models\QcInspection::create([
            'work_order_id' => $workOrder->id, 'inspection_type' => 'daily', 'status' => 'passed',
            'inspection_date' => now()->toDateString(), 'inspected_by' => $inspector->id,
        ]);

        // The inspector account is later deleted - the QC list/detail pages must
        // not 500 just because a historical inspector no longer resolves.
        $inspector->delete();

        $this->actingAs($admin)->get('/qc')->assertOk();
        $this->actingAs($admin)->get("/qc/{$inspection->id}")->assertOk();
    }

    public function test_uploading_media_to_a_work_order_stores_the_full_uuid_model_id(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('site.jpg')->size(500);

        $response = $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/media", [
            'collection' => 'images',
            'file' => $file,
        ]);
        $response->assertRedirect();

        // media.model_id must be created wide enough to hold WorkOrder's full
        // UUID id, not just the first ~19 chars an unsignedBigInteger allows.
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_type', WorkOrder::class)
            ->where('model_id', $workOrder->id)
            ->first();
        $this->assertNotNull($media, 'Media should be attached with the work order\'s full UUID as model_id.');
        $this->assertCount(1, $workOrder->fresh()->getMedia('images'));
    }

    public function test_daily_work_entries_can_be_added_multiple_times_a_day_with_tickable_items(): void
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

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'executive_team_id' => $team->id,
            'title' => 'Plastering - Ground floor',
            'items' => "Mix cement\nApply first coat",
        ])->assertRedirect();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'executive_team_id' => $team->id,
            'title' => 'Painting - First floor',
            'items' => "Prime the wall",
        ])->assertRedirect();

        $this->assertSame(2, $workOrder->fresh()->dailyChecklists()->count());
        $this->assertSame('in_progress', $workOrder->fresh()->status);

        $plastering = \App\Models\DailyChecklist::where('title', 'Plastering - Ground floor')->firstOrFail();
        $this->assertCount(2, $plastering->checklistItems);
        $this->assertTrue($plastering->checklistItems->every(fn ($item) => ! $item->is_done));
    }

    public function test_marking_a_checklist_item_done_requires_a_proof_upload(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $leader = User::create([
            'name' => 'Leader', 'email' => 'leader+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $leader->id, 'is_active' => true]);
        $checklist = $workOrder->dailyChecklists()->create(['executive_team_id' => $team->id, 'date' => now()->toDateString(), 'title' => 'Task', 'created_by' => $admin->id]);
        $item = $checklist->checklistItems()->create(['description' => 'Do the thing', 'sort_order' => 0]);

        // No file - must fail validation and leave the item undone.
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklist-items/{$item->id}/done")
            ->assertSessionHasErrors('proof');
        $this->assertFalse($item->fresh()->is_done);

        $file = \Illuminate\Http\UploadedFile::fake()->image('proof.jpg');
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklist-items/{$item->id}/done", [
            'proof' => $file,
        ])->assertRedirect();

        $item->refresh();
        $this->assertTrue($item->is_done);
        $this->assertSame($admin->id, $item->done_by);
        $this->assertNotNull($item->getFirstMedia('proof'));

        // Already-done items can't be marked done again.
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklist-items/{$item->id}/done", [
            'proof' => \Illuminate\Http\UploadedFile::fake()->image('again.jpg'),
        ])->assertStatus(422);
    }

    public function test_details_upload_section_lists_uploaded_files_by_name(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/media", [
            'collection' => 'documents',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('site-plan.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk()->assertSee('site-plan.pdf');
    }

    public function test_work_order_creation_computes_budget_from_material_and_labour_estimates(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id,
            'site_id' => $site->id,
            'client_id' => $client->id,
            'title' => 'WO with split budget',
            'execution_way' => 'way_2',
            'priority' => 'medium',
            'estimated_material_budget' => '15000.50',
            'estimated_labour_budget' => '4500',
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with split budget')->firstOrFail();
        $this->assertSame('15000.50', $workOrder->estimated_material_budget);
        $this->assertSame('4500.00', $workOrder->estimated_labour_budget);
        $this->assertSame('19500.50', $workOrder->budget_amount);
    }

    public function test_a_ledger_entry_can_be_recorded_with_a_bill_attachment(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'debit',
            'category' => 'Materials',
            'description' => 'Cement bags',
            'amount' => '2500.00',
            'bill' => \Illuminate\Http\UploadedFile::fake()->create('bill.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $ledger = $workOrder->fresh()->ledgers()->firstOrFail();
        $this->assertEquals(2500.00, $ledger->amount);
        $this->assertNotNull($ledger->getFirstMedia('bill'));

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk()->assertSee('Bill');
    }

    public function test_a_measurement_book_item_can_be_added_and_computes_its_amount(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $mb = $workOrder->measurementBooks()->create([
            'description' => 'Ground floor slab', 'date' => now()->toDateString(), 'recorded_by' => $admin->id, 'status' => 'draft',
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books/{$mb->id}/items", [
            'item_description' => 'RCC slab casting',
            'unit' => 'Sqft',
            'length' => '10',
            'breadth' => '5',
            'quantity' => '50',
            'rate' => '120',
        ])->assertRedirect();

        $item = $mb->fresh()->items()->firstOrFail();
        $this->assertEquals(10, $item->length);
        $this->assertEquals(50, $item->quantity);
        $this->assertEquals(6000, $item->amount);
    }

    public function test_worker_attendance_can_be_recorded_for_a_work_order_and_computes_hours(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Worker One', 'status' => 'active']);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/attendance", [
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'check_in' => '09:00',
            'check_out' => '17:30',
            'salary' => '800',
            'advance' => '200',
        ])->assertRedirect();

        $attendance = \App\Models\Attendance::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame($workOrder->id, $attendance->work_order_id);
        $this->assertEquals(8.5, $attendance->hours_worked);
        $this->assertSame('800.00', $attendance->salary);
        $this->assertSame('200.00', $attendance->advance);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk()->assertSee('Worker One');
    }

    public function test_work_order_creation_accepts_itemized_material_and_labour_rows(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id,
            'site_id' => $site->id,
            'client_id' => $client->id,
            'title' => 'WO with itemized budget',
            'execution_way' => 'way_2',
            'priority' => 'medium',
            'materials' => [
                ['material_name' => 'Cement', 'brand' => 'ACC', 'unit' => 'Bags', 'quantity' => '10', 'rate' => '400'],
                ['material_name' => '', 'quantity' => '', 'rate' => ''],
            ],
            'labour' => [
                ['labour_type' => 'Mason', 'count' => '2', 'wage_rate' => '900'],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with itemized budget')->firstOrFail();

        // The itemized rows on the create page are budget allocation only -
        // they must NOT create real MaterialEntry/LabourEntry rows, or they'd
        // pollute the Material Inward / Used Man Power actuals tabs.
        $this->assertSame(0, $workOrder->materialEntries()->count());
        $this->assertSame(0, $workOrder->labourEntries()->count());

        $this->assertSame('4000.00', $workOrder->estimated_material_budget);
        $this->assertSame('1800.00', $workOrder->estimated_labour_budget);
        $this->assertSame('5800.00', $workOrder->budget_amount);
    }

    public function test_work_order_creation_accepts_work_procedure_rows_for_the_schedule_book(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id,
            'site_id' => $site->id,
            'client_id' => $client->id,
            'title' => 'WO with schedule',
            'execution_way' => 'way_2',
            'priority' => 'medium',
            'labour' => [
                ['labour_type' => 'Mason', 'count' => '2', 'wage_rate' => '900'],
            ],
            'procedures' => [
                ['item_description' => 'Foundation excavation', 'length' => '20', 'breadth' => '10', 'height' => '3', 'quantity' => '600', 'unit' => 'cft'],
                ['item_description' => ''],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with schedule')->firstOrFail();

        $this->assertSame(0, $workOrder->labourEntries()->count());
        $this->assertSame('1800.00', $workOrder->estimated_labour_budget);

        $this->assertSame(1, $workOrder->measurementBooks()->count());
        $scheduleBook = $workOrder->measurementBooks()->firstOrFail();
        $this->assertSame('Work Schedule (M.Book)', $scheduleBook->description);
        $this->assertSame(1, $scheduleBook->items()->count());

        $item = $scheduleBook->items()->firstOrFail();
        $this->assertSame('Foundation excavation', $item->item_description);
        $this->assertEquals(20, $item->length);
        $this->assertEquals(600, $item->quantity);
        $this->assertSame('cft', $item->unit);
    }

    public function test_material_inward_entry_can_be_recorded_with_supplier_and_scope_details(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/materials", [
            'entry_date' => now()->toDateString(),
            'material_name' => 'Steel rods',
            'quantity' => '10',
            'unit' => 'Nos',
            'rate' => '650',
            'scope' => 'client',
            'vendor' => 'Sri Lakshmi Steels',
            'delivery_vehicle_details' => 'TN 45 AB 1234',
        ])->assertRedirect();

        $entry = $workOrder->fresh()->materialEntries()->firstOrFail();
        $this->assertSame('Steel rods', $entry->material_name);
        $this->assertEquals(6500, $entry->amount);
        $this->assertSame('client', $entry->scope);
        $this->assertSame('Sri Lakshmi Steels', $entry->vendor);
        $this->assertSame('TN 45 AB 1234', $entry->delivery_vehicle_details);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk()->assertSee('Sri Lakshmi Steels');
    }

    public function test_daily_material_used_entry_can_be_recorded(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/material-usage", [
            'date' => now()->toDateString(),
            'material_name' => 'Cement',
            'quantity' => '5',
            'unit' => 'Bag',
        ])->assertRedirect();

        $entry = $workOrder->fresh()->materialUsageEntries()->firstOrFail();
        $this->assertSame('Cement', $entry->material_name);
        $this->assertEquals(5, $entry->quantity);
        $this->assertSame('Bag', $entry->unit);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk()->assertSee('Cement');
    }

    public function test_worker_attendance_deducts_break_time_from_working_hours(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Worker Two', 'status' => 'active']);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/attendance", [
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'check_in' => '09:00',
            'check_out' => '18:00',
            'break_minutes' => '90',
        ])->assertRedirect();

        $attendance = \App\Models\Attendance::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(90, $attendance->break_minutes);
        $this->assertEquals(7.5, $attendance->hours_worked);
    }

    public function test_ledger_can_be_exported_as_csv_with_filters(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'debit', 'category' => 'Materials', 'description' => 'Cement', 'amount' => '1000',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'credit', 'category' => 'Advance', 'description' => 'Client advance', 'amount' => '5000',
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/ledger/export?category=Materials");
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Cement', $content);
        $this->assertStringNotContainsString('Client advance', $content);
    }

    public function test_payroll_can_be_generated_from_attendance_records(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Worker Three', 'status' => 'active']);

        \App\Models\Attendance::create([
            'employee_id' => $employee->id, 'work_order_id' => $workOrder->id,
            'date' => now()->startOfMonth()->addDays(2), 'status' => 'present', 'salary' => 800, 'advance' => 100,
        ]);
        \App\Models\Attendance::create([
            'employee_id' => $employee->id, 'work_order_id' => $workOrder->id,
            'date' => now()->startOfMonth()->addDays(3), 'status' => 'present', 'salary' => 800, 'advance' => 0,
        ]);

        $this->actingAs($admin)->post('/payroll/generate-from-attendance', [
            'employee_id' => $employee->id,
            'month' => now()->month,
            'year' => now()->year,
        ])->assertRedirect();

        $payroll = \App\Models\Payroll::where('employee_id', $employee->id)
            ->where('month', now()->month)->where('year', now()->year)->firstOrFail();
        $this->assertEquals(1600, $payroll->basic_salary);
        $this->assertEquals(100, $payroll->advance_deducted);
        $this->assertEquals(1500, $payroll->net_salary);
        $this->assertSame('pending', $payroll->status);
    }

    public function test_dashboard_shows_my_attendance_and_payroll_for_a_linked_employee(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $workerUser = User::create([
            'name' => 'Worker Login', 'email' => 'worker+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $workerUser->syncRoles(['Worker']);
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Worker Four', 'status' => 'active', 'user_id' => $workerUser->id]);

        \App\Models\Attendance::create([
            'employee_id' => $employee->id, 'work_order_id' => $workOrder->id,
            'date' => now(), 'status' => 'present', 'salary' => 800, 'advance' => 0,
        ]);

        $response = $this->actingAs($workerUser)->get('/dashboard');
        $response->assertOk()->assertSee($workOrder->work_order_no)->assertSee('My Attendance');
    }

    public function test_material_inward_and_man_power_budget_tabs_show_allocated_actual_and_remaining(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
            'estimated_material_budget' => 10000, 'estimated_labour_budget' => 5000,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/materials", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Cement', 'quantity' => '10', 'unit' => 'Bag', 'rate' => '300',
        ])->assertRedirect();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/labour", [
            'entry_date' => now()->toDateString(), 'labour_type' => 'Mason', 'count' => '2', 'wage_rate' => '900',
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=materials");
        $response->assertOk()
            ->assertSee('Allocated Material Budget')
            ->assertSee('₹10,000.00')
            ->assertSee('₹3,000.00')
            ->assertSee('₹7,000.00');

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=manpower");
        $response->assertOk()
            ->assertSee('Allocated Man Power Budget')
            ->assertSee('₹5,000.00')
            ->assertSee('₹1,800.00')
            ->assertSee('₹3,200.00');
    }

    public function test_allocated_work_schedule_is_kept_separate_from_actual_measurement_book(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id,
            'site_id' => $site->id,
            'client_id' => $client->id,
            'title' => 'WO with allocated schedule',
            'execution_way' => 'way_2',
            'priority' => 'medium',
            'procedures' => [
                ['item_description' => 'Foundation excavation', 'length' => '20', 'breadth' => '10', 'height' => '3', 'quantity' => '600', 'unit' => 'cft'],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with allocated schedule')->firstOrFail();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books", [
            'description' => 'Actual daily work', 'date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('schedule', $workOrder->measurementBooks()->where('description', 'Work Schedule (M.Book)')->firstOrFail()->type);
        $this->assertSame('actual', $workOrder->measurementBooks()->where('description', 'Actual daily work')->firstOrFail()->type);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=mb");
        $response->assertOk()
            ->assertSee('Allocated Work Schedule')
            ->assertSee('Actual Work Done')
            ->assertSee('Foundation excavation')
            ->assertSee('Actual daily work');
    }

    public function test_ledger_supports_borrow_and_lended_types_with_remark(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'borrow', 'amount' => '2000', 'remark' => 'Borrowed from site supervisor',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'lended', 'amount' => '500', 'remark' => 'Lent to worker',
        ])->assertRedirect();

        $entries = $workOrder->fresh()->ledgers()->orderBy('id')->get();
        $this->assertSame('borrow', $entries[0]->type);
        $this->assertEquals(2000, $entries[0]->balance);
        $this->assertSame('lended', $entries[1]->type);
        $this->assertEquals(1500, $entries[1]->balance);
        $this->assertSame('Lent to worker', $entries[1]->remark);
    }

    public function test_payroll_supports_partial_payments_and_holds_remaining_balance(): void
    {
        $admin = $this->admin();
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Worker Five', 'status' => 'active']);

        $this->actingAs($admin)->post('/payroll', [
            'employee_id' => $employee->id,
            'month' => now()->month,
            'year' => now()->year,
            'basic_salary' => '10000',
        ])->assertRedirect();

        $payroll = \App\Models\Payroll::where('employee_id', $employee->id)
            ->where('month', now()->month)->where('year', now()->year)->firstOrFail();
        $this->assertSame('pending', $payroll->status);

        $this->actingAs($admin)->post("/payroll/{$payroll->id}/record-payment", ['amount' => '6000'])->assertRedirect();
        $payroll->refresh();
        $this->assertSame('partial', $payroll->status);
        $this->assertEquals(6000, $payroll->paid_amount);
        $this->assertEquals(4000, $payroll->remaining());

        $this->actingAs($admin)->post("/payroll/{$payroll->id}/record-payment", ['amount' => '4000'])->assertRedirect();
        $payroll->refresh();
        $this->assertSame('paid', $payroll->status);
        $this->assertEquals(0, $payroll->remaining());
        $this->assertNotNull($payroll->paid_at);
    }

    public function test_payroll_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Worker Six', 'status' => 'active']);

        $this->actingAs($admin)->post('/payroll', [
            'employee_id' => $employee->id,
            'month' => now()->month,
            'year' => now()->year,
            'basic_salary' => '5000',
        ])->assertRedirect();

        $payroll = \App\Models\Payroll::where('employee_id', $employee->id)->firstOrFail();

        $response = $this->actingAs($admin)->get("/payroll/{$payroll->id}/pdf");
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_work_order_creation_accepts_time_schedule_rows_shown_in_used_man_power_tab(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id,
            'site_id' => $site->id,
            'client_id' => $client->id,
            'title' => 'WO with time schedule',
            'execution_way' => 'way_2',
            'priority' => 'medium',
            'time_schedules' => [
                ['time_to_finish' => '10', 'unit' => 'Days', 'remark' => 'Overall completion target'],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with time schedule')->firstOrFail();
        $schedule = $workOrder->timeSchedules()->firstOrFail();
        $this->assertSame('10', $schedule->time_to_finish);
        $this->assertSame('Days', $schedule->unit);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=manpower");
        $response->assertOk()
            ->assertSee('Allocated Time Schedule')
            ->assertSee('Overall completion target');
    }

    public function test_used_man_power_entry_records_the_entry_date(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/labour", [
            'entry_date' => '2026-08-10', 'labour_type' => 'Mason', 'count' => '1', 'wage_rate' => '900',
        ])->assertRedirect();

        $entry = $workOrder->fresh()->labourEntries()->firstOrFail();
        $this->assertSame('2026-08-10', $entry->entry_date->format('Y-m-d'));

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=manpower");
        $response->assertOk()->assertSee('10 Aug 2026');
    }

    public function test_payroll_payment_history_is_recorded_date_wise(): void
    {
        $admin = $this->admin();
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Worker Seven', 'status' => 'active']);

        $this->actingAs($admin)->post('/payroll', [
            'employee_id' => $employee->id,
            'month' => now()->month,
            'year' => now()->year,
            'basic_salary' => '10000',
        ])->assertRedirect();

        $payroll = \App\Models\Payroll::where('employee_id', $employee->id)->firstOrFail();

        $this->actingAs($admin)->post("/payroll/{$payroll->id}/record-payment", [
            'amount' => '4000', 'paid_on' => '2026-08-05',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/payroll/{$payroll->id}/record-payment", [
            'amount' => '6000', 'paid_on' => '2026-08-20',
        ])->assertRedirect();

        $payments = $payroll->payments()->get();
        $this->assertSame(2, $payments->count());
        $this->assertSame('2026-08-05', $payments[0]->paid_on->format('Y-m-d'));
        $this->assertEquals(4000, $payments[0]->amount);
        $this->assertSame('2026-08-20', $payments[1]->paid_on->format('Y-m-d'));
        $this->assertEquals(6000, $payments[1]->amount);

        $response = $this->actingAs($admin)->get('/payroll?month='.now()->month.'&year='.now()->year);
        $response->assertOk()->assertSee('2 payment(s)');
    }

    public function test_only_admin_can_edit_or_remove_a_work_order(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $nonAdmin = $this->executiveTeamLeader();

        $this->actingAs($nonAdmin)->put("/work-orders/{$workOrder->id}", ['title' => 'Hacked', 'priority' => 'medium'])
            ->assertForbidden();
        $this->actingAs($nonAdmin)->delete("/work-orders/{$workOrder->id}")->assertForbidden();

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/edit")
            ->assertOk()->assertSee('WO');

        $this->actingAs($admin)->put("/work-orders/{$workOrder->id}", [
            'title' => 'Renamed WO', 'priority' => 'high',
        ])->assertRedirect();
        $this->assertSame('Renamed WO', $workOrder->fresh()->title);

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}")->assertRedirect();
        $this->assertNotNull($workOrder->fresh()->deleted_at);
    }

    public function test_only_admin_can_edit_or_remove_tab_entries_on_a_work_order(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $nonAdmin = $this->executiveTeamLeader();

        $material = $workOrder->materialEntries()->create([
            'entry_date' => now()->toDateString(), 'material_name' => 'Cement', 'unit' => 'Bags',
            'quantity' => 10, 'rate' => 400, 'amount' => 4000, 'added_by' => $admin->id,
        ]);

        $this->actingAs($nonAdmin)->put("/work-orders/{$workOrder->id}/materials/{$material->id}", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Hacked', 'unit' => 'Bags', 'quantity' => 1, 'rate' => 1,
        ])->assertForbidden();
        $this->actingAs($nonAdmin)->delete("/work-orders/{$workOrder->id}/materials/{$material->id}")->assertForbidden();

        $this->actingAs($admin)->put("/work-orders/{$workOrder->id}/materials/{$material->id}", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Corrected Cement', 'unit' => 'Bags', 'quantity' => 12, 'rate' => 450,
        ])->assertRedirect();
        $material->refresh();
        $this->assertSame('Corrected Cement', $material->material_name);
        $this->assertEquals(5400, $material->amount);

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/materials/{$material->id}")->assertRedirect();
        $this->assertNull($workOrder->materialEntries()->find($material->id));
    }

    public function test_admin_can_edit_and_remove_a_checklist_and_its_items(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $leader = User::create([
            'name' => 'Leader', 'email' => 'leader+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $leader->id, 'is_active' => true]);
        WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);
        $nonAdmin = $this->executiveTeamLeader();

        $checklist = $workOrder->dailyChecklists()->create([
            'executive_team_id' => $team->id, 'date' => now()->toDateString(), 'title' => 'Day 1', 'created_by' => $admin->id,
        ]);
        $item = $checklist->checklistItems()->create(['description' => 'Lay bricks', 'sort_order' => 0]);

        $this->actingAs($nonAdmin)->delete("/work-orders/{$workOrder->id}/checklist-items/{$item->id}")->assertForbidden();
        $this->actingAs($nonAdmin)->delete("/work-orders/{$workOrder->id}/checklists/{$checklist->id}")->assertForbidden();

        $this->actingAs($admin)->put("/work-orders/{$workOrder->id}/checklists/{$checklist->id}", [
            'title' => 'Day 1 - Corrected', 'executive_team_id' => $team->id,
        ])->assertRedirect();
        $this->assertSame('Day 1 - Corrected', $checklist->fresh()->title);

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/checklist-items/{$item->id}")->assertRedirect();
        $this->assertNull($checklist->checklistItems()->find($item->id));

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/checklists/{$checklist->id}")->assertRedirect();
        $this->assertNull($workOrder->dailyChecklists()->find($checklist->id));
    }

    public function test_editing_or_removing_a_ledger_entry_recalculates_all_balances(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $nonAdmin = $this->executiveTeamLeader();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'credit', 'amount' => '10000',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'debit', 'amount' => '2000',
        ])->assertRedirect();

        $entries = $workOrder->fresh()->ledgers()->orderBy('id')->get();
        $first = $entries[0];
        $second = $entries[1];
        $this->assertEquals(10000, $first->balance);
        $this->assertEquals(8000, $second->balance);

        $this->actingAs($nonAdmin)->put("/work-orders/{$workOrder->id}/ledger/{$first->id}", [
            'entry_date' => now()->toDateString(), 'type' => 'credit', 'amount' => '5000',
        ])->assertForbidden();

        // Correcting the first (credit) entry from 10000 to 5000 must ripple
        // through to the second entry's stored balance too.
        $this->actingAs($admin)->put("/work-orders/{$workOrder->id}/ledger/{$first->id}", [
            'entry_date' => now()->toDateString(), 'type' => 'credit', 'amount' => '5000',
        ])->assertRedirect();

        $this->assertEquals(5000, $first->fresh()->balance);
        $this->assertEquals(3000, $second->fresh()->balance);

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/ledger/{$first->id}")->assertRedirect();
        $this->assertEquals(-2000, $second->fresh()->balance);
    }

    public function test_admin_can_edit_and_remove_a_measurement_book_item(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $nonAdmin = $this->executiveTeamLeader();
        $mb = $workOrder->measurementBooks()->create([
            'description' => 'Ground floor slab', 'date' => now()->toDateString(), 'recorded_by' => $admin->id, 'status' => 'draft', 'type' => 'actual',
        ]);
        $item = $mb->items()->create([
            'item_description' => 'RCC slab', 'unit' => 'Sqft', 'length' => 10, 'breadth' => 5, 'quantity' => 50, 'rate' => 120, 'amount' => 6000,
        ]);

        $this->actingAs($nonAdmin)->put("/work-orders/{$workOrder->id}/measurement-books/{$mb->id}/items/{$item->id}", [
            'item_description' => 'Hacked', 'unit' => 'Sqft', 'quantity' => 1,
        ])->assertForbidden();

        $this->actingAs($admin)->put("/work-orders/{$workOrder->id}/measurement-books/{$mb->id}/items/{$item->id}", [
            'item_description' => 'RCC slab corrected', 'unit' => 'Sqft', 'quantity' => 60, 'rate' => 120,
        ])->assertRedirect();
        $item->refresh();
        $this->assertSame('RCC slab corrected', $item->item_description);
        $this->assertEquals(7200, $item->amount);

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/measurement-books/{$mb->id}/items/{$item->id}")->assertRedirect();
        $this->assertNull($mb->items()->find($item->id));
    }

    public function test_only_admin_can_edit_or_remove_a_qc_inspection(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $nonAdmin = $this->executiveTeamLeader();
        $inspection = $workOrder->qcInspections()->create([
            'inspection_type' => 'daily', 'status' => 'passed', 'inspection_date' => now()->toDateString(), 'inspected_by' => $admin->id,
        ]);

        $this->actingAs($nonAdmin)->get("/qc/{$inspection->id}/edit")->assertForbidden();
        $this->actingAs($nonAdmin)->put("/qc/{$inspection->id}", [
            'inspection_type' => 'daily', 'status' => 'failed',
        ])->assertForbidden();
        $this->actingAs($nonAdmin)->delete("/qc/{$inspection->id}")->assertForbidden();

        $this->actingAs($admin)->get("/qc/{$inspection->id}/edit")->assertOk();

        $this->actingAs($admin)->put("/qc/{$inspection->id}", [
            'inspection_type' => 'final', 'status' => 'failed', 'remarks' => 'Corrected',
        ])->assertRedirect();
        $inspection->refresh();
        $this->assertSame('final', $inspection->inspection_type);
        $this->assertSame('failed', $inspection->status);

        $this->actingAs($admin)->delete("/qc/{$inspection->id}")->assertRedirect();
        $this->assertNull(\App\Models\QcInspection::find($inspection->id));
    }

    public function test_site_documents_can_be_uploaded_and_only_admin_can_remove_them(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $site = Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $nonAdmin = $this->executiveTeamLeader();

        $this->actingAs($admin)->post("/sites/{$site->id}/documents", [
            'category' => 'kyc',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('kyc.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $document = $site->fresh()->getMedia('kyc')->first();
        $this->assertNotNull($document);
        $this->assertSame($site->id, $document->model_id);

        $response = $this->actingAs($admin)->get("/sites/{$site->id}");
        $response->assertOk()->assertSee('kyc.pdf');

        $this->actingAs($nonAdmin)->delete("/sites/{$site->id}/documents/{$document->id}")->assertForbidden();

        $this->actingAs($admin)->delete("/sites/{$site->id}/documents/{$document->id}")->assertRedirect();
        $this->assertNull($site->fresh()->getMedia('kyc')->first());
    }

    public function test_company_ledger_is_only_reachable_by_finance_and_admin(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $finance = $this->finance();
        // Worker has no work_orders.view and isn't assigned to this WO's
        // team, so WorkOrderPolicy::view() refuses it outright - unlike
        // Executive Team Leader, which already holds work_orders.view.
        $nonFinance = User::create([
            'name' => 'Worker', 'email' => 'worker+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $nonFinance->syncRoles(['Worker']);

        // A non-Finance, non-Admin user can't even open the work order page
        // to reach the tab.
        $this->actingAs($nonFinance)->get("/work-orders/{$workOrder->id}")->assertForbidden();

        $this->actingAs($nonFinance)->post("/work-orders/{$workOrder->id}/company-ledger", [
            'entry_date' => now()->toDateString(), 'type' => 'debit', 'amount' => '500',
        ])->assertForbidden();

        // Finance can open the work order and see the tab's data in the
        // response - but not the data from unrelated permission-gated tabs.
        $response = $this->actingAs($finance)->get("/work-orders/{$workOrder->id}");
        $response->assertOk()->assertSee('Company Ledger');

        $this->actingAs($finance)->post("/work-orders/{$workOrder->id}/company-ledger", [
            'entry_date' => now()->toDateString(), 'type' => 'credit', 'category' => 'Office', 'amount' => '10000',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/company-ledger", [
            'entry_date' => now()->toDateString(), 'type' => 'debit', 'category' => 'Site Visit', 'amount' => '1500',
        ])->assertRedirect();

        $entries = $workOrder->fresh()->companyLedgers()->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertEquals(10000, $entries[0]->balance);
        $this->assertEquals(8500, $entries[1]->balance);

        // The site ledger (a separate table entirely) is untouched.
        $this->assertSame(0, $workOrder->ledgers()->count());

        $this->actingAs($nonFinance)->delete("/work-orders/{$workOrder->id}/company-ledger/{$entries[0]->id}")
            ->assertForbidden();

        $this->actingAs($finance)->put("/work-orders/{$workOrder->id}/company-ledger/{$entries[0]->id}", [
            'entry_date' => now()->toDateString(), 'type' => 'credit', 'amount' => '5000',
        ])->assertRedirect();

        $this->assertEquals(5000, $entries[0]->fresh()->balance);
        $this->assertEquals(3500, $entries[1]->fresh()->balance);

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/company-ledger/{$entries[0]->id}")->assertRedirect();
        $this->assertEquals(-1500, $entries[1]->fresh()->balance);
    }
}
