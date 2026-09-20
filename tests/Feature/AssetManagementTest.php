<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetChangeRequest;
use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\ExecutiveTeam;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Phase 1 of the Equipment & Asset Management module: Asset create/edit
 * /remove/restore, the Management-edit-requires-Admin-approval workflow,
 * Executive Team Leader site-scoped status updates with an undeletable
 * status log, attachments/reports, and the Asset Details PDF.
 */
class AssetManagementTest extends TestCase
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

    private function staffUser(User $admin, string $role, ?string $name = null): User
    {
        $name ??= "{$role} Person";
        $email = strtolower(str_replace(' ', '', $role)).'+'.uniqid().'@example.com';

        $this->actingAs($admin)->post('/admin/users', [
            'name' => $name, 'email' => $email, 'role' => $role,
        ])->assertRedirect();

        return User::where('email', $email)->firstOrFail();
    }

    private function workOrderLedBy(User $admin, User $teamLeader): WorkOrder
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team A', 'team_leader_id' => $teamLeader->id, 'is_active' => true]);
        WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);

        return $workOrder;
    }

    public function test_admin_can_create_an_asset_with_full_details(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/assets', [
            'name' => 'Concrete Mixer', 'category' => 'Mixer', 'brand' => 'ABC', 'model' => 'CM-200',
            'serial_number' => 'SN123', 'condition' => 'Good', 'status' => 'available', 'current_location' => 'company_store',
            'purchase_date' => '2026-01-10', 'purchase_cost' => '45000', 'supplier' => 'XYZ Traders', 'invoice_number' => 'INV-1',
            'warranty_start' => '2026-01-10', 'warranty_end' => '2027-01-10', 'warranty_provider' => 'ABC Ltd',
            'warranty_card_details' => 'Card #123',
        ]);

        $asset = Asset::where('name', 'Concrete Mixer')->firstOrFail();
        $response->assertRedirect(route('assets.show', $asset));
        $this->assertStringStartsWith('AST-', $asset->asset_code);
        $this->assertSame('XYZ Traders', $asset->supplier);
    }

    public function test_a_management_user_can_create_an_asset_but_executive_team_leader_cannot(): void
    {
        $admin = $this->admin();
        $management = $this->staffUser($admin, 'Management');
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');

        $this->actingAs($management)->post('/assets', ['name' => 'Drill Machine'])->assertRedirect();
        $this->assertDatabaseHas('assets', ['name' => 'Drill Machine']);

        $this->actingAs($teamLeader)->get('/assets/create')->assertForbidden();
        $this->actingAs($teamLeader)->post('/assets', ['name' => 'Should Not Save'])->assertForbidden();
        $this->assertDatabaseMissing('assets', ['name' => 'Should Not Save']);
    }

    public function test_admin_edit_applies_immediately_but_management_edit_requires_admin_approval(): void
    {
        $admin = $this->admin();
        $management = $this->staffUser($admin, 'Management');

        $this->actingAs($admin)->post('/assets', ['name' => 'Welding Machine', 'brand' => 'OldBrand'])->assertRedirect();
        $asset = Asset::where('name', 'Welding Machine')->firstOrFail();

        // Admin edits apply straight away.
        $this->actingAs($admin)->put("/assets/{$asset->id}", ['name' => 'Welding Machine', 'brand' => 'NewBrandByAdmin'])->assertRedirect();
        $this->assertSame('NewBrandByAdmin', $asset->fresh()->brand);

        // Management submits the same kind of edit - it must NOT apply immediately.
        $this->actingAs($management)->put("/assets/{$asset->id}/request-update", ['name' => 'Welding Machine', 'brand' => 'NewBrandByManagement'])->assertRedirect();
        $this->assertSame('NewBrandByAdmin', $asset->fresh()->brand, 'A Management edit must not change the asset until Admin approves it.');

        $changeRequest = AssetChangeRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('pending', $changeRequest->status);
        $this->assertSame('NewBrandByAdmin', $changeRequest->old_values['brand']);
        $this->assertSame('NewBrandByManagement', $changeRequest->new_values['brand']);
        $this->assertSame($management->id, $changeRequest->requested_by);

        // A non-Admin cannot bypass approval by posting straight to the direct-update route.
        $this->actingAs($management)->put("/assets/{$asset->id}", ['name' => 'Welding Machine', 'brand' => 'Bypass'])->assertForbidden();

        // Admin approves - now it applies, and the request records who reviewed it.
        $this->actingAs($admin)->post("/asset-change-requests/{$changeRequest->id}/approve", ['remarks' => 'Looks fine.'])->assertRedirect();
        $this->assertSame('NewBrandByManagement', $asset->fresh()->brand);
        $fresh = $changeRequest->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertSame('Looks fine.', $fresh->remarks);
    }

    public function test_admin_can_reject_a_management_change_request_and_the_asset_stays_unchanged(): void
    {
        $admin = $this->admin();
        $management = $this->staffUser($admin, 'Management');

        $this->actingAs($admin)->post('/assets', ['name' => 'Grinder', 'brand' => 'Original'])->assertRedirect();
        $asset = Asset::where('name', 'Grinder')->firstOrFail();

        $this->actingAs($management)->put("/assets/{$asset->id}/request-update", ['name' => 'Grinder', 'brand' => 'Requested'])->assertRedirect();
        $changeRequest = AssetChangeRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($admin)->post("/asset-change-requests/{$changeRequest->id}/reject", ['remarks' => 'Not needed.'])->assertRedirect();

        $this->assertSame('Original', $asset->fresh()->brand);
        $this->assertSame('rejected', $changeRequest->fresh()->status);
    }

    public function test_executive_team_leader_can_update_status_of_an_asset_at_their_site_and_it_is_logged(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $otherLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Wall Cutter', 'status' => 'available', 'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Wall Cutter')->firstOrFail();

        // The other Team Leader (not assigned to this WO) cannot update it.
        $this->actingAs($otherLeader)->post("/assets/{$asset->id}/status", ['status' => 'in_use'])->assertForbidden();

        // The assigned Team Leader can.
        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/status", [
            'status' => 'in_use', 'reason' => 'Started work today.',
        ])->assertRedirect();

        $fresh = $asset->fresh();
        $this->assertSame('in_use', $fresh->status);

        $this->assertDatabaseHas('asset_status_logs', [
            'asset_id' => $asset->id, 'previous_status' => 'available', 'new_status' => 'in_use',
            'updated_by' => $teamLeader->id, 'work_order_id' => $workOrder->id, 'reason' => 'Started work today.',
        ]);

        // No route exists to delete a status log entry at all.
        $this->assertDatabaseCount('asset_status_logs', 1);
    }

    public function test_status_history_cannot_be_deleted_and_shows_on_the_asset_page(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Trolley', 'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Trolley')->firstOrFail();

        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/status", ['status' => 'damaged', 'reason' => 'Wheel broke'])->assertRedirect();

        $this->actingAs($admin)->get("/assets/{$asset->id}")->assertOk()->assertSee('Wheel broke');
    }

    public function test_asset_attachments_and_reports_can_be_uploaded_and_downloaded(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/assets', [
            'name' => 'Cutting Machine',
            'attachments' => [UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')],
            'reports' => [UploadedFile::fake()->image('report.jpg')],
        ]);
        $response->assertRedirect();

        $asset = Asset::where('name', 'Cutting Machine')->firstOrFail();
        $this->assertSame(1, $asset->getMedia('attachments')->count());
        $this->assertSame(1, $asset->getMedia('reports')->count());

        $this->actingAs($admin)->get("/assets/{$asset->id}")->assertOk()->assertSee('invoice.pdf')->assertSee('report.jpg');
    }

    public function test_asset_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Vibrator Machine'])->assertRedirect();
        $asset = Asset::where('name', 'Vibrator Machine')->firstOrFail();

        $response = $this->actingAs($admin)->get("/assets/{$asset->id}/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_admin_can_remove_and_restore_an_asset(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Old Compressor'])->assertRedirect();
        $asset = Asset::where('name', 'Old Compressor')->firstOrFail();

        $this->actingAs($admin)->delete("/assets/{$asset->id}")->assertRedirect(route('assets.index'));
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);

        $this->actingAs($admin)->get('/assets?removed=1')->assertOk()->assertSee('Old Compressor');

        $this->actingAs($admin)->post("/assets/{$asset->id}/restore")->assertRedirect();
        $this->assertNull($asset->fresh()->deleted_at);
    }

    public function test_a_role_without_asset_permissions_cannot_reach_the_module(): void
    {
        $admin = $this->admin();
        $sales = $this->staffUser($admin, 'Sales');

        $this->actingAs($sales)->get('/assets')->assertForbidden();
        $this->actingAs($sales)->get('/assets/create')->assertForbidden();
        $this->actingAs($sales)->post('/assets', ['name' => 'Blocked'])->assertForbidden();
    }

    /**
     * "Executive TL and other roles should not be able to create an Asset
     * directly unless permission is specifically given through Roles &
     * Permissions" - and that grant needs no code change: the generic
     * Roles & Permissions UI groups permissions by their name prefix, so
     * assets.* just needs to exist as a Permission row to show up there.
     */
    public function test_an_admin_can_grant_asset_create_to_another_role_via_roles_and_permissions(): void
    {
        $admin = $this->admin();
        $sales = $this->staffUser($admin, 'Sales');

        $this->actingAs($sales)->post('/assets', ['name' => 'Not Yet Allowed'])->assertForbidden();

        $salesRole = \Spatie\Permission\Models\Role::where('name', 'Sales')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.roles.edit', $salesRole))
            ->assertOk()
            ->assertSee('assets')
            ->assertSee('assets.create');

        $this->actingAs($admin)->put(route('admin.roles.update', $salesRole), [
            'name' => 'Sales',
            'permissions' => $salesRole->permissions->pluck('name')->push('assets.view', 'assets.create')->all(),
        ])->assertRedirect();

        $this->actingAs($sales)->post('/assets', ['name' => 'Now Allowed'])->assertRedirect();
        $this->assertDatabaseHas('assets', ['name' => 'Now Allowed']);
    }

    public function test_asset_list_search_and_filters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Bosch Drill Machine', 'brand' => 'Bosch', 'category' => 'Power Tool', 'status' => 'available'])->assertRedirect();
        $this->actingAs($admin)->post('/assets', ['name' => 'Concrete Mixer', 'brand' => 'ABC', 'category' => 'Mixer', 'status' => 'under_repair'])->assertRedirect();

        $this->actingAs($admin)->get('/assets?q=Drill')->assertOk()->assertSee('Bosch Drill Machine')->assertDontSee('Concrete Mixer');
        $this->actingAs($admin)->get('/assets?status=under_repair')->assertOk()->assertSee('Concrete Mixer')->assertDontSee('Bosch Drill Machine');
        $this->actingAs($admin)->get('/assets?category=Power+Tool')->assertOk()->assertSee('Bosch Drill Machine')->assertDontSee('Concrete Mixer');
    }
}
