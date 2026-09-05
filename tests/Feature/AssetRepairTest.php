<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetRepair;
use App\Models\AssetStatusLog;
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
 * Phase 3 of the Equipment & Asset Management module: repair tracking.
 * Reporting a repair (and every status change through to completion or
 * cancellation) automatically drives the asset's own operational status
 * and writes into the same undeletable AssetStatusLog used for manual
 * status updates.
 */
class AssetRepairTest extends TestCase
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

    public function test_reporting_a_repair_transitions_asset_status_and_logs_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Concrete Mixer', 'status' => 'available'])->assertRedirect();
        $asset = Asset::where('name', 'Concrete Mixer')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'mechanical', 'issue_description' => 'Motor overheating', 'reported_date' => now()->toDateString(),
        ])->assertRedirect();

        $repair = AssetRepair::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('reported', $repair->status);
        $this->assertSame('available', $repair->asset_status_before);

        $fresh = $asset->fresh();
        $this->assertSame('under_repair', $fresh->status);

        $log = AssetStatusLog::where('asset_id', $asset->id)->latest()->firstOrFail();
        $this->assertSame('available', $log->previous_status);
        $this->assertSame('under_repair', $log->new_status);
        $this->assertSame($admin->id, $log->updated_by);
    }

    public function test_a_team_leader_cannot_report_a_repair_for_an_asset_outside_their_site(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $otherWorkOrder = $this->workOrderLedBy($admin, $this->staffUser($admin, 'Executive Team Leader'));

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Wall Cutter', 'current_location' => 'work_order', 'current_work_order_id' => $otherWorkOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Wall Cutter')->firstOrFail();

        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'mechanical', 'issue_description' => 'Blade jammed', 'reported_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseMissing('asset_repairs', ['asset_id' => $asset->id]);
    }

    public function test_a_team_leader_can_report_and_track_a_repair_at_their_own_site(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Grinder', 'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Grinder')->firstOrFail();

        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'electrical', 'issue_description' => 'Sparking', 'reported_date' => now()->toDateString(),
        ])->assertRedirect();
        $repair = AssetRepair::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($teamLeader)->post("/asset-repairs/{$repair->id}/status", ['status' => 'in_progress'])->assertRedirect();
        $this->assertSame('under_repair', $asset->fresh()->status);

        $this->actingAs($teamLeader)->post("/asset-repairs/{$repair->id}/status", ['status' => 'completed'])->assertRedirect();
        $repair->refresh();
        $this->assertSame('completed', $repair->status);
        $this->assertNotNull($repair->completed_date);
        $this->assertSame('repaired', $asset->fresh()->status);
    }

    public function test_a_team_leader_cannot_update_a_repair_for_an_asset_outside_their_site(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $otherLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Vibrator', 'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Vibrator')->firstOrFail();

        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'other', 'issue_description' => 'Not starting', 'reported_date' => now()->toDateString(),
        ])->assertRedirect();
        $repair = AssetRepair::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($otherLeader)->post("/asset-repairs/{$repair->id}/status", ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_cancelling_a_repair_reverts_the_asset_to_its_status_before_the_repair(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Welding Machine', 'status' => 'in_use'])->assertRedirect();
        $asset = Asset::where('name', 'Welding Machine')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'cosmetic', 'issue_description' => 'Dent', 'reported_date' => now()->toDateString(),
        ])->assertRedirect();
        $repair = AssetRepair::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('in_use', $repair->asset_status_before);
        $this->assertSame('under_repair', $asset->fresh()->status);

        $this->actingAs($admin)->post("/asset-repairs/{$repair->id}/status", ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame('cancelled', $repair->fresh()->status);
        $this->assertSame('in_use', $asset->fresh()->status);
    }

    public function test_a_management_user_can_create_and_edit_repairs_but_not_update_status(): void
    {
        $admin = $this->admin();
        $management = $this->staffUser($admin, 'Management');

        $this->actingAs($admin)->post('/assets', ['name' => 'Cutting Machine'])->assertRedirect();
        $asset = Asset::where('name', 'Cutting Machine')->firstOrFail();

        $this->actingAs($management)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'mechanical', 'issue_description' => 'Belt slipping', 'reported_date' => now()->toDateString(),
        ])->assertRedirect();
        $repair = AssetRepair::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($management)->put("/asset-repairs/{$repair->id}", [
            'repair_type' => 'mechanical', 'issue_description' => 'Belt replaced, still slipping', 'reported_date' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertSame('Belt replaced, still slipping', $repair->fresh()->issue_description);

        $this->actingAs($management)->post("/asset-repairs/{$repair->id}/status", ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_repair_history_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Bosch Drill'])->assertRedirect();
        $asset = Asset::where('name', 'Bosch Drill')->firstOrFail();

        $response = $this->actingAs($admin)->get("/assets/{$asset->id}/repairs/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_repair_list_search_and_filters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Power Trowel'])->assertRedirect();
        $asset = Asset::where('name', 'Power Trowel')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'mechanical', 'issue_description' => 'Engine issue', 'reported_date' => now()->toDateString(),
            'is_warranty_repair' => '1', 'technician_vendor' => 'Acme Repairs',
        ])->assertRedirect();

        $this->actingAs($admin)->get('/asset-repairs?q=Power+Trowel')->assertOk()->assertSee('Power Trowel');
        $this->actingAs($admin)->get('/asset-repairs?repair_type=electrical')->assertOk()->assertDontSee('Power Trowel');
        $this->actingAs($admin)->get('/asset-repairs?warranty=1')->assertOk()->assertSee('Power Trowel');
        $this->actingAs($admin)->get('/asset-repairs?technician_vendor=Acme')->assertOk()->assertSee('Power Trowel');
    }

    public function test_attachment_can_be_uploaded_with_a_repair_entry(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Pipe Bender'])->assertRedirect();
        $asset = Asset::where('name', 'Pipe Bender')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'mechanical', 'issue_description' => 'Bent frame', 'reported_date' => now()->toDateString(),
            'attachments' => [UploadedFile::fake()->image('damage.jpg')],
        ])->assertRedirect();

        $repair = AssetRepair::where('asset_id', $asset->id)->firstOrFail();
        $this->assertCount(1, $repair->getMedia('attachments'));
    }
}
