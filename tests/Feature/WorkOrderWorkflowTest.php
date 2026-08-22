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
    private function sales(): User
    {
        $user = User::create([
            'name' => 'Sales User', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Sales']);

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

    // Call only after admin() has already seeded roles/permissions in the
    // same test - reseeding here would duplicate the Role rows.
    private function qcOfficer(): User
    {
        $user = User::create([
            'name' => 'QC Officer', 'email' => 'qc+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['QC Officer']);

        return $user;
    }

    // Call only after admin() has already seeded roles/permissions in the
    // same test - reseeding here would duplicate the Role rows.
    private function subContractor(string $name = 'Sub Contractor User'): User
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Sub Contractor']);

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
            'date' => now()->toDateString(),
            'title' => 'Plastering - Ground floor',
            'items' => "Mix cement\nApply first coat",
        ])->assertRedirect();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'executive_team_id' => $team->id,
            'date' => now()->toDateString(),
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

    public function test_work_order_creation_supports_itemized_equipment_transport_and_misc_budget_entry(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get("/work-orders/create?quotation_id={$quotation->id}")
            ->assertOk()->assertSee('Equipment / Machinery')->assertSee('Transport')->assertSee('Miscellaneous / Contingency');

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id,
            'site_id' => $site->id,
            'client_id' => $client->id,
            'title' => 'WO with full budget breakdown',
            'execution_way' => 'way_2',
            'priority' => 'medium',
            'materials' => [['material_name' => 'Cement', 'unit' => 'Bag', 'quantity' => 10, 'rate' => 400]],
            'labour' => [['labour_type' => 'Mason', 'count' => 2, 'wage_rate' => 800]],
            'equipment' => [['item_name' => 'Concrete Mixer', 'unit' => 'Days', 'quantity' => 5, 'rate' => 1000]],
            'transport' => [['item_name' => 'Material delivery', 'unit' => 'Trip', 'quantity' => 3, 'rate' => 1500]],
            'misc' => [['item_name' => 'Contingency', 'unit' => 'Lump', 'quantity' => 1, 'rate' => 2000]],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with full budget breakdown')->firstOrFail();
        $this->assertSame('4000.00', $workOrder->estimated_material_budget);
        $this->assertSame('1600.00', $workOrder->estimated_labour_budget);
        $this->assertSame('5000.00', $workOrder->estimated_equipment_budget);
        $this->assertSame('4500.00', $workOrder->estimated_transport_budget);
        $this->assertSame('2000.00', $workOrder->estimated_misc_budget);
        $this->assertSame('17100.00', $workOrder->budget_amount);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Equipment / Machinery')
            ->assertSee('Transport')
            ->assertSee('Miscellaneous / Contingency');

        // Regression: Overview used to show only the aggregate totals, not
        // the itemized material/manpower/etc. rows entered at creation.
        $response->assertSee('Planned Budget Details')
            ->assertSee('Cement')->assertSee('Mason')->assertSee('Concrete Mixer')
            ->assertSee('Material delivery')->assertSee('Contingency');
    }

    public function test_work_order_create_page_keeps_itemized_budget_rows_after_a_validation_error(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        // Regression: the create page's Alpine state for materials/labour/
        // equipment/transport/misc/procedures/time schedules was hardcoded to
        // a single blank row and never read old() input. Any unrelated
        // validation failure elsewhere in the same submission (e.g. a
        // missing priority) silently wiped every itemized row the user had
        // typed, so a resubmit would create the work order without them.
        $this->actingAs($admin)
            ->from(route('work-orders.create', ['quotation_id' => $quotation->id]))
            ->post('/work-orders', [
                'quotation_id' => $quotation->id,
                'site_id' => $site->id,
                'client_id' => $client->id,
                'title' => 'WO missing priority',
                'execution_way' => 'way_2',
                // 'priority' intentionally omitted to trigger a validation failure.
                'materials' => [['material_name' => 'Cement', 'unit' => 'Bag', 'quantity' => 10, 'rate' => 400]],
                'labour' => [['labour_type' => 'Mason', 'count' => 2, 'wage_rate' => 800]],
                'equipment' => [['item_name' => 'Concrete Mixer', 'unit' => 'Days', 'quantity' => 5, 'rate' => 1000]],
                'transport' => [['item_name' => 'Material delivery', 'unit' => 'Trip', 'quantity' => 3, 'rate' => 1500]],
                'misc' => [['item_name' => 'Contingency', 'unit' => 'Lump', 'quantity' => 1, 'rate' => 2000]],
            ])
            ->assertRedirect(route('work-orders.create', ['quotation_id' => $quotation->id]))
            ->assertSessionHasErrors('priority');

        $this->assertSame(0, WorkOrder::count());

        $this->actingAs($admin)->get("/work-orders/create?quotation_id={$quotation->id}")
            ->assertOk()
            ->assertSee('Cement')->assertSee('Mason')
            ->assertSee('Concrete Mixer')->assertSee('Material delivery')->assertSee('Contingency');
    }

    public function test_work_order_pdf_includes_the_same_itemized_budget_details_as_the_overview_tab(): void
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
            'title' => 'WO for PDF budget check',
            'execution_way' => 'way_2',
            'priority' => 'medium',
            'scope' => 'கட்டிட வேலை - Foundation and slab work',
            'materials' => [['material_name' => 'Cement', 'unit' => 'Bag', 'quantity' => 10, 'rate' => 400]],
            'labour' => [['labour_type' => 'Mason', 'count' => 2, 'wage_rate' => 800]],
            'time_schedules' => [['time_to_finish' => '15', 'unit' => 'days', 'remark' => 'Includes curing time']],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO for PDF budget check')->firstOrFail();

        // Regression: the PDF export template used to only show the aggregate
        // budget total, dropping the itemized rows the live Overview tab shows.
        $html = view('work-orders.pdf.full', ['workOrder' => $workOrder, 'sections' => ['overview']])->render();
        $this->assertStringContainsString('Planned Budget Details', $html);
        $this->assertStringContainsString('Cement', $html);
        $this->assertStringContainsString('Mason', $html);
        $this->assertStringContainsString('Includes curing time', $html);

        // Regression: Tamil scope text rendered as "?????" in the PDF because
        // dompdf has no complex-script text shaping engine at all - no font
        // fixes that. Rendering now goes through mPDF (autoScriptToLang /
        // autoLangToFont), which shapes Tamil correctly.
        $this->assertStringContainsString('கட்டிட வேலை', $html);

        $bytes = \App\Support\Pdf::loadView('work-orders.pdf.full', ['workOrder' => $workOrder, 'sections' => ['overview']])->output();
        $this->assertStringStartsWith('%PDF-', $bytes);

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/overview")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_work_order_pdf_shows_ist_timestamps_not_raw_utc(): void
    {
        // Regression: app.timezone is UTC (Carbon::now() and stored datetimes
        // are UTC), but the PDF's "Generated" line and status-log timestamps
        // were formatted without converting to IST first, so they displayed
        // times ~5.5 hours behind the real Indian creation time.
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-08-21 16:00:00', 'UTC'));

        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $html = view('work-orders.pdf.full', ['workOrder' => $workOrder, 'sections' => ['overview']])->render();

        // 16:00 UTC = 21:30 IST (09:30 PM), not 04:00 PM.
        $this->assertStringContainsString('09:30 PM', $html);
        $this->assertStringNotContainsString('04:00 PM', $html);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_work_order_overview_shows_the_planned_time_schedule(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id, 'site_id' => $site->id, 'client_id' => $client->id,
            'title' => 'WO with time schedule', 'execution_way' => 'way_2', 'priority' => 'medium',
            'time_schedules' => [['time_to_finish' => '10', 'unit' => 'Days', 'remark' => 'Foundation to roofing']],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with time schedule')->firstOrFail();

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}")
            ->assertOk()->assertSee('Time Schedule')->assertSee('Foundation to roofing');
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

    public function test_a_registered_worker_can_see_their_own_payroll_history_and_download_a_payslip(): void
    {
        // Regression: the Worker role's sidebar had no menu at all for
        // payroll - only an easy-to-miss current-month widget on the
        // Dashboard, with no way to see past months or download a payslip.
        $admin = $this->admin();
        $workerUser = User::create([
            'name' => 'Worker Login', 'email' => 'worker+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $workerUser->syncRoles(['Worker']);
        $employee = \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Worker Five', 'status' => 'active', 'user_id' => $workerUser->id]);

        $payroll = \App\Models\Payroll::create([
            'employee_id' => $employee->id, 'month' => now()->month, 'year' => now()->year,
            'basic_salary' => 20000, 'net_salary' => 20000, 'paid_amount' => 5000, 'status' => 'partial',
            'processed_by' => $admin->id,
        ]);

        $this->actingAs($workerUser)->get('/my-payroll')
            ->assertOk()
            ->assertSee(now()->format('F Y'))
            ->assertSee('₹20,000.00')
            ->assertSee('Payslip PDF');

        $this->actingAs($workerUser)->get("/my-payroll/{$payroll->id}/pdf")->assertOk();

        // Another worker cannot download someone else's payslip via this route.
        $otherWorker = User::create([
            'name' => 'Other Worker', 'email' => 'worker2+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $otherWorker->syncRoles(['Worker']);
        \App\Models\Employee::create(['employee_code' => 'EMP-'.uniqid(), 'name' => 'Other Employee', 'status' => 'active', 'user_id' => $otherWorker->id]);

        $this->actingAs($otherWorker)->get("/my-payroll/{$payroll->id}/pdf")->assertForbidden();
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

    public function test_create_next_work_order_links_to_the_full_create_form_instead_of_auto_creating(): void
    {
        // Regression: "Create Next Work Order" used to submit a mini form
        // (title only) straight to a dedicated endpoint that auto-created the
        // work order, skipping the full Create Work Order page entirely
        // (quotation/site details, execution method, itemized budget). It
        // must now link to that full page so nothing is created until the
        // admin actually reviews and submits it.
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'quotation_id' => $quotation->id, 'site_id' => $site->id,
            'title' => 'Phase 1', 'priority' => 'medium', 'execution_way' => 'way_1',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'completed', 'created_by' => $admin->id,
        ]);

        $expectedUrl = route('work-orders.create', ['quotation_id' => $quotation->id, 'parent_work_order_id' => $workOrder->id]);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=overview");
        $response->assertOk()
            ->assertSee(e($expectedUrl), false)
            ->assertDontSee('Start a Next Work Order for this client');

        $response = $this->actingAs($admin)->get($expectedUrl);
        $response->assertOk()
            ->assertSee('Next work order for')
            ->assertSee($workOrder->work_order_no)
            ->assertSee('name="parent_work_order_id" value="'.$workOrder->id.'"', false);

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id, 'site_id' => $site->id, 'client_id' => $client->id,
            'parent_work_order_id' => $workOrder->id,
            'title' => 'Phase 2', 'execution_way' => 'way_2', 'priority' => 'medium',
        ])->assertRedirect();

        $next = WorkOrder::where('title', 'Phase 2')->firstOrFail();
        $this->assertSame('next', $next->type);
        $this->assertSame($workOrder->id, $next->parent_work_order_id);
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

    public function test_editing_a_work_order_via_itemized_material_and_labour_rows_updates_the_budget(): void
    {
        // The Edit page used to only accept flat total-budget numbers; it now
        // uses the same itemized entry tables as Create, so quantity x rate
        // rows compute and persist the allocated budget totals instead.
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put("/work-orders/{$workOrder->id}", [
            'title' => 'WO', 'priority' => 'medium',
            'materials' => [['material_name' => 'Cement', 'quantity' => 500, 'rate' => 100]],
            'labour' => [['labour_type' => 'Mason', 'count' => 10, 'wage_rate' => 2500.05]],
        ]);
        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $fresh = $workOrder->fresh();
        $this->assertSame('50000.00', $fresh->estimated_material_budget);
        $this->assertSame('25000.50', $fresh->estimated_labour_budget);
        $this->assertSame('75000.50', $fresh->budget_amount);
        $this->assertSame(1, $fresh->budgetItems()->where('category', 'material')->count());
        $this->assertSame(1, $fresh->budgetItems()->where('category', 'labour')->count());
    }

    public function test_editing_a_work_order_updates_equipment_transport_and_misc_budget_too(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/edit")
            ->assertOk()
            ->assertSee('Equipment / Machinery')
            ->assertSee('Transport')
            ->assertSee('Miscellaneous / Contingency');

        $response = $this->actingAs($admin)->put("/work-orders/{$workOrder->id}", [
            'title' => 'WO', 'priority' => 'medium',
            'equipment' => [['item_name' => 'Mixer', 'quantity' => 1, 'rate' => 3000]],
            'transport' => [['item_name' => 'Delivery', 'quantity' => 1, 'rate' => 1500]],
            'misc' => [['item_name' => 'Contingency', 'quantity' => 1, 'rate' => 500]],
        ]);
        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $fresh = $workOrder->fresh();
        $this->assertSame('3000.00', $fresh->estimated_equipment_budget);
        $this->assertSame('1500.00', $fresh->estimated_transport_budget);
        $this->assertSame('500.00', $fresh->estimated_misc_budget);
        $this->assertSame('5000.00', $fresh->budget_amount);
    }

    public function test_editing_a_work_order_with_existing_labour_rows_does_not_fail_integer_validation(): void
    {
        // Regression: WorkOrderBudgetItem::quantity is cast 'decimal:2', so
        // reloading a saved labour row's count on the Edit page produced
        // "20.00" instead of "20". The count input is validated as a strict
        // integer, so re-saving the form without touching that field failed
        // with "The labour.0.count field must be an integer."
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/work-orders', [
            'quotation_id' => $quotation->id, 'site_id' => $site->id, 'client_id' => $client->id,
            'title' => 'WO with labour', 'execution_way' => 'way_2', 'priority' => 'medium',
            'labour' => [
                ['labour_type' => 'technician', 'count' => 20, 'wage_rate' => 1200],
                ['labour_type' => 'helper', 'count' => 80, 'wage_rate' => 1000],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with labour')->firstOrFail();

        $editPage = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/edit")->assertOk();
        $editPage->assertDontSee('20.00')->assertDontSee('80.00');

        $response = $this->actingAs($admin)->put("/work-orders/{$workOrder->id}", [
            'title' => $workOrder->title, 'priority' => $workOrder->priority,
            'labour' => [
                ['labour_type' => 'technician', 'count' => 20, 'wage_rate' => 1200],
                ['labour_type' => 'helper', 'count' => 80, 'wage_rate' => 1000],
            ],
        ]);
        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_editing_a_work_order_preserves_and_updates_the_work_procedure_and_time_schedule_rows(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/work-orders/{$workOrder->id}", [
            'title' => 'WO', 'priority' => 'medium',
            'procedures' => [['item_description' => 'Foundation', 'length' => 10, 'breadth' => 5, 'quantity' => 50, 'unit' => 'Sqft']],
            'time_schedules' => [['time_to_finish' => '10', 'unit' => 'Days']],
        ])->assertRedirect();

        $scheduleBook = $workOrder->fresh()->measurementBooks()->where('type', 'schedule')->first();
        $this->assertNotNull($scheduleBook);
        $this->assertSame(1, $scheduleBook->items()->count());
        $this->assertSame('Foundation', $scheduleBook->items()->first()->item_description);
        $this->assertSame(1, $workOrder->fresh()->timeSchedules()->count());

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/edit")
            ->assertOk()->assertSee('Foundation');

        // Submitting again with the procedure row removed clears it, rather
        // than leaving the old row stuck with no page left to remove it from.
        $this->actingAs($admin)->put("/work-orders/{$workOrder->id}", [
            'title' => 'WO', 'priority' => 'medium',
        ])->assertRedirect();

        $this->assertSame(0, $scheduleBook->fresh()->items()->count());
        $this->assertSame(0, $workOrder->fresh()->timeSchedules()->count());
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
            'title' => 'Day 1 - Corrected', 'executive_team_id' => $team->id, 'date' => now()->toDateString(),
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
            'inspection_type' => 'daily', 'status' => 'failed', 'inspection_date' => now()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($nonAdmin)->delete("/qc/{$inspection->id}")->assertForbidden();

        $this->actingAs($admin)->get("/qc/{$inspection->id}/edit")->assertOk();

        $this->actingAs($admin)->put("/qc/{$inspection->id}", [
            'inspection_type' => 'final', 'status' => 'failed', 'remarks' => 'Corrected', 'inspection_date' => now()->toDateString(),
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

    public function test_only_sales_hr_admin_and_the_team_leader_can_edit_the_work_order_summary_and_client_can_view_it(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $sales = $this->sales();
        $teamLeader = $this->executiveTeamLeader();
        $nonEditor = $this->qcOfficer();

        $this->actingAs($nonEditor)->post("/work-orders/{$workOrder->id}/summary", [
            'entry_date' => '2026-08-01', 'status' => 'done', 'responsibility' => 'company', 'work_detail' => 'Marking and Excavation',
        ])->assertForbidden();

        $this->actingAs($sales)->post("/work-orders/{$workOrder->id}/summary", [
            'entry_date' => '2026-08-01', 'status' => 'done', 'responsibility' => 'company',
            'work_detail' => 'Marking and Excavation', 'client_bear_days' => 0, 'remaining_construction_days' => 179,
        ])->assertRedirect();

        $entry = $workOrder->fresh()->summaries()->firstOrFail();
        $this->assertSame('Marking and Excavation', $entry->work_detail);

        $this->actingAs($nonEditor)->put("/work-orders/{$workOrder->id}/summary/{$entry->id}", [
            'entry_date' => '2026-08-01', 'status' => 'done', 'responsibility' => 'company',
        ])->assertForbidden();
        $this->actingAs($nonEditor)->delete("/work-orders/{$workOrder->id}/summary/{$entry->id}")->assertForbidden();

        // The Executive Team Leader is also responsible for site-level entries now.
        $this->actingAs($teamLeader)->put("/work-orders/{$workOrder->id}/summary/{$entry->id}", [
            'entry_date' => '2026-08-02', 'status' => 'not_done', 'responsibility' => 'client', 'work_detail' => 'Rain',
        ])->assertRedirect();
        $entry->refresh();
        $this->assertSame('not_done', $entry->status);
        $this->assertSame('client', $entry->responsibility);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=summary");
        $response->assertOk()->assertSee('Rain');

        // Client portal shows the summary read-only.
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();
        $login = \App\Models\ClientLogin::where('client_id', $client->id)->firstOrFail();

        $portalResponse = $this->actingAs($login->user)->get("/portal/work-orders/{$workOrder->id}");
        $portalResponse->assertOk()->assertSee('Monthly Summary')->assertSee('Rain');

        $this->actingAs($teamLeader)->delete("/work-orders/{$workOrder->id}/summary/{$entry->id}")->assertRedirect();
        $this->assertSame(0, $workOrder->summaries()->count());
    }

    public function test_internal_approval_request_shows_raised_by_sent_to_and_downloads_as_pdf(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'Natana Kamaraj', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/approval-requests", [
            'title' => 'Approve elevation change', 'description' => 'Client requested a design tweak.',
        ])->assertRedirect();
        $approval = $workOrder->fresh()->approvalRequests()->firstOrFail();

        $this->assertSame('Admin', $approval->raisedByName());
        $this->assertSame('Natana Kamaraj', $approval->sentToName());

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=approvals");
        $response->assertOk()
            ->assertSee('Raised By:')
            ->assertSee('Sent To:')
            ->assertSee('Admin')
            ->assertSee('Natana Kamaraj');

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/approval-requests/{$approval->id}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // No approved requests yet - the bulk link shouldn't appear.
        $response->assertDontSee('Download All Approved');

        $approval->update(['status' => 'approved', 'responded_by' => $admin->id, 'responded_at' => now()]);

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/approval-requests/approved-pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_client_portal_shows_approval_request_details_and_pdf_downloads(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'Natana Kamaraj', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();
        $clientUser = ClientLogin::where('client_id', $client->id)->firstOrFail()->user;

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/approval-requests", [
            'title' => 'Approve elevation change', 'description' => 'Client requested a design tweak.',
        ])->assertRedirect();
        $approval = $workOrder->fresh()->approvalRequests()->firstOrFail();

        $response = $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Raised By:')
            ->assertSee('Sent To:')
            ->assertSee('Admin')
            ->assertSee('Natana Kamaraj');

        $this->actingAs($clientUser)->post("/portal/work-orders/{$workOrder->id}/approval-requests/{$approval->id}/respond", [
            'status' => 'approved', 'response_note' => 'Looks good.',
        ])->assertRedirect();

        $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}/approval-requests/{$approval->id}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}/approval-requests/approved-pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // A client can't reach another client's work order PDFs.
        $otherClient = Client::create(['name' => 'Other', 'email' => 'other@example.com', 'phone' => '2', 'is_active' => true, 'created_by' => $admin->id]);
        $this->actingAs($admin)->post("/clients/{$otherClient->id}/portal-access")->assertRedirect();
        $otherClientUser = ClientLogin::where('client_id', $otherClient->id)->firstOrFail()->user;

        $this->actingAs($otherClientUser)->get("/portal/work-orders/{$workOrder->id}/approval-requests/{$approval->id}/pdf")
            ->assertForbidden();
        $this->actingAs($otherClientUser)->get("/portal/work-orders/{$workOrder->id}/approval-requests/approved-pdf")
            ->assertForbidden();
    }

    public function test_work_order_section_and_full_pdfs_can_be_downloaded(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        foreach (['site', 'overview', 'team', 'checklist', 'progress', 'materials', 'manpower', 'mb', 'summary', 'ledger', 'company-ledger', 'qc', 'approvals', 'tickets'] as $section) {
            $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/{$section}")
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/not-a-real-section")->assertNotFound();
    }

    public function test_company_ledger_pdf_section_is_hidden_from_non_finance_admin(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $sales = $this->sales();

        $this->actingAs($sales)->get("/work-orders/{$workOrder->id}/pdf/company-ledger")->assertNotFound();
        $this->actingAs($sales)->get("/work-orders/{$workOrder->id}/pdf/overview")->assertOk();
        $this->actingAs($sales)->get("/work-orders/{$workOrder->id}/pdf")->assertOk();
    }

    public function test_a_user_without_view_access_cannot_download_a_work_order_pdf(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $worker = User::create([
            'name' => 'Worker', 'email' => 'worker+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $worker->syncRoles(['Worker']);

        $this->actingAs($worker)->get("/work-orders/{$workOrder->id}/pdf")->assertForbidden();
        $this->actingAs($worker)->get("/work-orders/{$workOrder->id}/pdf/overview")->assertForbidden();
    }

    public function test_site_pdf_downloads_all_its_work_orders_together(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $site = Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        WorkOrder::create([
            'client_id' => $client->id, 'site_id' => $site->id, 'title' => 'WO 1', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        WorkOrder::create([
            'client_id' => $client->id, 'site_id' => $site->id, 'title' => 'WO 2', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/sites/{$site->id}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_ledger_and_checklist_pdf_sections_embed_uploaded_images_without_erroring(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'debit', 'category' => 'Materials', 'amount' => '500',
            'bill' => \Illuminate\Http\UploadedFile::fake()->image('bill.jpg', 200, 200),
        ])->assertRedirect();

        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $admin->id, 'is_active' => true]);
        WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'executive_team_id' => $team->id, 'date' => now()->toDateString(), 'title' => 'Day 1', 'items' => "Lay bricks",
        ])->assertRedirect();
        $item = \App\Models\DailyChecklistItem::firstOrFail();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklist-items/{$item->id}/done", [
            'proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg', 200, 200),
        ])->assertRedirect();

        // A large fake image (base64-embedded) shouldn't blow up rendering,
        // and the sections must still come back as valid, sizeable PDFs.
        $ledgerPdf = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/ledger");
        $ledgerPdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(1000, strlen($ledgerPdf->getContent()));

        $checklistPdf = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/checklist");
        $checklistPdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(1000, strlen($checklistPdf->getContent()));

        $fullPdf = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf");
        $fullPdf->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_work_order_zip_download_includes_details_and_every_attachment_organised_by_type(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/media", [
            'collection' => 'images', 'file' => \Illuminate\Http\UploadedFile::fake()->image('site-photo.jpg', 200, 200),
        ])->assertRedirect();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/media", [
            'collection' => 'documents', 'file' => \Illuminate\Http\UploadedFile::fake()->create('contract.pdf', 50),
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/zip");
        $response->assertOk()->assertHeader('content-type', 'application/zip');

        $tmpZip = tempnam(sys_get_temp_dir(), 'zip-test-').'.zip';
        file_put_contents($tmpZip, $response->streamedContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmpZip) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($tmpZip);

        $this->assertContains('WO Details.pdf', $names);
        $this->assertTrue(collect($names)->contains(fn ($n) => str_starts_with($n, 'Images/')));
        $this->assertTrue(collect($names)->contains(fn ($n) => str_starts_with($n, 'Documents/')));
    }

    public function test_a_site_zip_download_bundles_every_work_order_in_its_own_folder(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $site = Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        $workOrder1 = WorkOrder::create([
            'client_id' => $client->id, 'site_id' => $site->id, 'title' => 'WO 1', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $workOrder2 = WorkOrder::create([
            'client_id' => $client->id, 'site_id' => $site->id, 'title' => 'WO 2', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/sites/{$site->id}/zip");
        $response->assertOk()->assertHeader('content-type', 'application/zip');

        $tmpZip = tempnam(sys_get_temp_dir(), 'zip-test-').'.zip';
        file_put_contents($tmpZip, $response->streamedContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmpZip) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        unlink($tmpZip);

        $this->assertTrue(collect($names)->contains("{$workOrder1->work_order_no}/WO Details.pdf"));
        $this->assertTrue(collect($names)->contains("{$workOrder2->work_order_no}/WO Details.pdf"));
    }

    public function test_a_way_2_work_order_can_have_sub_contractors_assigned_and_they_gain_access(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium', 'execution_way' => 'way_2',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'pending_hr_assignment', 'created_by' => $admin->id,
        ]);

        $subContractor = $this->subContractor();
        $stranger = $this->executiveTeamLeader();

        // Not yet assigned - no access to the work order, and it should not
        // yet show up in their "My Work Orders" list.
        $this->actingAs($subContractor)->get("/work-orders/{$workOrder->id}")->assertForbidden();
        $this->actingAs($subContractor)->get('/my-work-orders')->assertOk()->assertDontSee($workOrder->work_order_no);

        // Only Sales/HR/Admin (worker_assignment.manage|work_orders.edit) can assign.
        $this->actingAs($stranger)->post("/work-orders/{$workOrder->id}/assign-sub-contractor", [
            'sub_contractor_user_id' => $subContractor->id,
        ])->assertForbidden();

        // Assigning a non-Sub-Contractor user is rejected.
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/assign-sub-contractor", [
            'sub_contractor_user_id' => $stranger->id,
        ])->assertStatus(422);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/assign-sub-contractor", [
            'sub_contractor_user_id' => $subContractor->id,
        ])->assertRedirect();

        $workOrder->refresh();
        $this->assertSame('team_assigned', $workOrder->status);
        $assignment = \App\Models\WorkOrderSubContractor::firstOrFail();
        $this->assertSame($subContractor->id, $assignment->user_id);

        // Now assigned - full view access, appears in their dashboard list,
        // and the dashboard route redirects them straight there.
        $this->actingAs($subContractor)->get("/work-orders/{$workOrder->id}")->assertOk();
        $this->actingAs($subContractor)->get('/my-work-orders')->assertOk()->assertSee($workOrder->work_order_no);
        $this->actingAs($subContractor)->get('/dashboard')->assertRedirect('/my-work-orders');

        // Unassigning revokes access again.
        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/unassign-sub-contractor/{$assignment->id}")->assertRedirect();
        $this->actingAs($subContractor)->get("/work-orders/{$workOrder->id}")->assertForbidden();
    }

    public function test_daily_work_with_checklist_can_be_added_on_a_way_2_work_order_with_no_team_assigned(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium', 'execution_way' => 'way_2',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $subContractor = $this->subContractor();
        \App\Models\WorkOrderSubContractor::create([
            'work_order_id' => $workOrder->id, 'user_id' => $subContractor->id, 'assigned_by' => $admin->id, 'assigned_at' => now(),
        ]);

        $this->assertTrue($workOrder->executiveTeams->isEmpty());

        $response = $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'date' => now()->toDateString(), 'title' => 'Day 1', 'items' => "Lay bricks\nMix cement",
        ]);
        $response->assertRedirect();

        $checklist = \App\Models\DailyChecklist::firstOrFail();
        $this->assertNull($checklist->executive_team_id);
        $this->assertSame(2, $checklist->checklistItems()->count());

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}")->assertOk()->assertSee('Day 1');
    }

    public function test_a_sub_contractor_can_submit_daily_progress_on_a_way_2_work_order_with_no_team_assigned(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium', 'execution_way' => 'way_2',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $subContractor = $this->subContractor();
        \App\Models\WorkOrderSubContractor::create([
            'work_order_id' => $workOrder->id, 'user_id' => $subContractor->id, 'assigned_by' => $admin->id, 'assigned_at' => now(),
        ]);

        $response = $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/progress", [
            'date' => now()->toDateString(), 'completed_work' => 'Plastering done',
        ]);
        $response->assertRedirect();

        $report = \App\Models\DailyProgressReport::firstOrFail();
        $this->assertNull($report->executive_team_id);
        $this->assertSame('Plastering done', $report->completed_work);
    }

    public function test_qc_officer_cannot_see_the_site_ledger_tab_but_other_roles_still_can(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $qc = $this->qcOfficer();

        $this->actingAs($qc)->get("/work-orders/{$workOrder->id}")->assertOk()->assertDontSee('Site Ledger');
        $this->actingAs($qc)->get("/work-orders/{$workOrder->id}/pdf/ledger")->assertNotFound();

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}")->assertOk()->assertSee('Site Ledger');
        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/ledger")->assertOk();
    }

    public function test_checklist_proof_is_viewable_by_qc_after_marking_an_item_done(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $admin->id, 'is_active' => true]);
        WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'executive_team_id' => $team->id, 'date' => now()->toDateString(), 'title' => 'Day 1', 'items' => "Lay bricks",
        ])->assertRedirect();
        $item = \App\Models\DailyChecklistItem::firstOrFail();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklist-items/{$item->id}/done", [
            'proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg', 200, 200),
        ])->assertRedirect();

        $qc = $this->qcOfficer();
        $this->actingAs($qc)->get("/work-orders/{$workOrder->id}")->assertOk()->assertSee('View Proof');
    }

    public function test_client_portal_shows_daily_checklist_and_clickable_progress_media(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $admin->id, 'is_active' => true]);
        WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'executive_team_id' => $team->id, 'date' => now()->toDateString(), 'title' => 'Day 1', 'items' => "Lay bricks",
        ])->assertRedirect();
        $item = \App\Models\DailyChecklistItem::firstOrFail();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklist-items/{$item->id}/done", [
            'proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg', 200, 200),
        ])->assertRedirect();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/media", [
            'collection' => 'images', 'file' => \Illuminate\Http\UploadedFile::fake()->image('site.jpg', 200, 200),
        ])->assertRedirect();

        $clientUser = User::create([
            'name' => $client->name, 'email' => $client->email,
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $clientUser->syncRoles(['Client']);
        ClientLogin::create(['client_id' => $client->id, 'user_id' => $clientUser->id]);

        $response = $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Daily Work &amp; Checklist', false)
            ->assertSee('View Proof')
            ->assertSee('Lay bricks');

        $mediaUrl = $workOrder->fresh()->getFirstMedia('images')->getUrl();
        $response->assertSee('<a href="'.$mediaUrl.'"', false);
    }

    public function test_sub_contractor_is_limited_to_progress_and_media_while_team_leader_can_enter_everything_else(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium', 'execution_way' => 'way_2',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $admin->id, 'is_active' => true]);
        WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);

        $subContractor = $this->subContractor();
        $teamLeader = $this->executiveTeamLeader();

        // Sub Contractor: allowed - Progress & Media only.
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/progress", [
            'executive_team_id' => $team->id, 'date' => now()->toDateString(), 'completed_work' => 'Plastering done',
        ])->assertRedirect();
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/media", [
            'collection' => 'images', 'file' => \Illuminate\Http\UploadedFile::fake()->image('site.jpg', 200, 200),
        ])->assertRedirect();

        // Sub Contractor: blocked from every other entry point.
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/checklists", [
            'executive_team_id' => $team->id, 'date' => now()->toDateString(), 'title' => 'Day 1', 'items' => 'Lay bricks',
        ])->assertForbidden();
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/materials", [
            'material_name' => 'Cement', 'quantity' => 10, 'rate' => 400,
        ])->assertForbidden();
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/material-usage", [
            'material_name' => 'Cement', 'quantity' => 5,
        ])->assertForbidden();
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/labour", [
            'labour_type' => 'Mason', 'count' => 2, 'wage_rate' => 800,
        ])->assertForbidden();
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/measurement-books", [
            'type' => 'actual', 'description' => 'Foundation',
        ])->assertForbidden();
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'debit', 'category' => 'Materials', 'amount' => '500',
        ])->assertForbidden();
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/summary", [
            'entry_date' => now()->toDateString(), 'status' => 'done', 'responsibility' => 'company',
        ])->assertForbidden();
        $this->actingAs($subContractor)->post("/work-orders/{$workOrder->id}/approval-requests", [
            'title' => 'Need extra material',
        ])->assertForbidden();

        // Executive Team Leader: can enter everything the Sub Contractor is blocked from.
        $this->actingAs($teamLeader)->post("/work-orders/{$workOrder->id}/checklists", [
            'executive_team_id' => $team->id, 'date' => now()->toDateString(), 'title' => 'Day 1', 'items' => 'Lay bricks',
        ])->assertRedirect();
        $this->actingAs($teamLeader)->post("/work-orders/{$workOrder->id}/approval-requests", [
            'title' => 'Need extra material',
        ])->assertRedirect();
        $this->assertSame(1, \App\Models\ApprovalRequest::where('title', 'Need extra material')->count());
    }
}
