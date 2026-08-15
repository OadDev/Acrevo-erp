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
            'collection' => 'before_images',
            'file' => $file,
        ]);
        $response->assertRedirect();

        // media.model_id must be created wide enough to hold WorkOrder's full
        // UUID id, not just the first ~19 chars an unsignedBigInteger allows.
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_type', WorkOrder::class)
            ->where('model_id', $workOrder->id)
            ->first();
        $this->assertNotNull($media, 'Media should be attached with the work order\'s full UUID as model_id.');
        $this->assertCount(1, $workOrder->fresh()->getMedia('before_images'));
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
        $this->assertSame(1, $workOrder->materialEntries()->count());
        $this->assertSame(1, $workOrder->labourEntries()->count());

        $material = $workOrder->materialEntries()->firstOrFail();
        $this->assertSame('Cement', $material->material_name);
        $this->assertEquals(4000, $material->amount);

        $labour = $workOrder->labourEntries()->firstOrFail();
        $this->assertSame('Mason', $labour->labour_type);
        $this->assertEquals(1800, $labour->amount);

        $this->assertSame('4000.00', $workOrder->estimated_material_budget);
        $this->assertSame('1800.00', $workOrder->estimated_labour_budget);
        $this->assertSame('5800.00', $workOrder->budget_amount);
    }

    public function test_work_order_creation_accepts_time_schedule_and_work_procedure_rows(): void
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
                ['labour_type' => 'Mason', 'count' => '2', 'wage_rate' => '900', 'total_time_to_finish' => '3 days', 'remark' => 'Ground floor'],
            ],
            'procedures' => [
                ['item_description' => 'Foundation excavation', 'length' => '20', 'breadth' => '10', 'height' => '3', 'quantity' => '600', 'unit' => 'cft'],
                ['item_description' => ''],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::where('title', 'WO with schedule')->firstOrFail();

        $labour = $workOrder->labourEntries()->firstOrFail();
        $this->assertSame('3 days', $labour->total_time_to_finish);
        $this->assertSame('Ground floor', $labour->remark);

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
}
