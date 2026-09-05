<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetRepair;
use App\Models\AssetStatusLog;
use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 of the Equipment & Asset Management module: viewing equipment
 * from the Work Order's side - the current equipment list for a site, plus
 * the broader history (movements, repairs, missing incidents) that has
 * ever touched it.
 */
class WorkOrderEquipmentTest extends TestCase
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

    private function workOrder(User $admin): WorkOrder
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    public function test_current_equipment_index_shows_only_assets_assigned_to_this_site(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $otherWorkOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Site Mixer', 'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();
        $this->actingAs($admin)->post('/assets', [
            'name' => 'Other Site Mixer', 'current_location' => 'work_order', 'current_work_order_id' => $otherWorkOrder->id,
        ])->assertRedirect();

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment")
            ->assertOk()
            ->assertSee('Site Mixer')
            ->assertDontSee('Other Site Mixer');
    }

    public function test_current_equipment_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Vibrator', 'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_equipment_history_shows_movements_repairs_and_missing_incidents_for_this_site(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Grinder', 'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Grinder')->firstOrFail();

        // Purchase movement auto-created on creation already touches this WO (to_work_order_id).
        $this->assertTrue(AssetMovement::where('asset_id', $asset->id)->where('to_work_order_id', $workOrder->id)->exists());

        $this->actingAs($admin)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'mechanical', 'issue_description' => 'Broken handle', 'reported_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($admin)->post("/assets/{$asset->id}/status", ['status' => 'missing'])->assertRedirect();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment/history");
        $response->assertOk()
            ->assertSee('Grinder')
            ->assertSee('Broken handle');

        $this->assertTrue(AssetStatusLog::where('asset_id', $asset->id)->where('work_order_id', $workOrder->id)->where('new_status', 'missing')->exists());
    }

    public function test_equipment_history_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Ladder', 'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment/history/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_equipment_history_repairs_are_scoped_to_the_work_order_the_asset_was_at_when_reported(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $otherWorkOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Cutter', 'current_location' => 'work_order', 'current_work_order_id' => $otherWorkOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Cutter')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/repairs", [
            'repair_type' => 'mechanical', 'issue_description' => 'Dull blade', 'reported_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertTrue(AssetRepair::where('asset_id', $asset->id)->where('work_order_id', $otherWorkOrder->id)->exists());

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment/history")
            ->assertOk()
            ->assertDontSee('Dull blade');
    }

    public function test_a_role_without_asset_permissions_cannot_reach_work_order_equipment(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $email = 'auditor+'.uniqid().'@example.com';
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Auditor Person', 'email' => $email, 'role' => 'Auditor',
        ])->assertRedirect();
        $auditor = User::where('email', $email)->firstOrFail();

        $this->actingAs($auditor)->get("/work-orders/{$workOrder->id}/equipment")->assertForbidden();
    }
}
