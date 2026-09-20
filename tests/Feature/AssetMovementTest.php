<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\ExecutiveTeam;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 of the Equipment & Asset Management module: the complete
 * movement timeline (Purchase, Site Allocation, Transfer, Return, etc.),
 * with Create/Confirm/Edit split into separate permissions and Executive
 * Team Leader access scoped to their own site on both ends.
 */
class AssetMovementTest extends TestCase
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

    public function test_creating_an_asset_auto_records_a_confirmed_purchase_movement(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Concrete Mixer', 'purchase_date' => '2026-06-01'])->assertRedirect();
        $asset = Asset::where('name', 'Concrete Mixer')->firstOrFail();

        $movement = AssetMovement::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('purchase', $movement->type);
        $this->assertSame('supplier', $movement->from_location);
        $this->assertSame('company_store', $movement->to_location);
        $this->assertSame('confirmed', $movement->status);
        $this->assertSame('2026-06-01', $movement->moved_at->format('Y-m-d'));
    }

    public function test_admin_can_dispatch_and_confirm_a_transfer_to_a_work_order(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($admin)->post('/assets', ['name' => 'Wall Cutter'])->assertRedirect();
        $asset = Asset::where('name', 'Wall Cutter')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/movements", [
            'type' => 'site_allocation', 'to_location' => 'work_order', 'to_work_order_id' => $workOrder->id,
            'moved_at' => now()->toDateString(), 'remarks' => 'Site requirement',
        ])->assertRedirect();

        $movement = AssetMovement::where('asset_id', $asset->id)->where('type', 'site_allocation')->firstOrFail();
        $this->assertSame('pending', $movement->status);
        $this->assertSame('company_store', $movement->from_location);
        // Not applied yet - the asset stays at its old location until confirmed.
        $this->assertSame('company_store', $asset->fresh()->current_location);

        $this->actingAs($teamLeader)->post("/asset-movements/{$movement->id}/confirm")->assertRedirect();

        $fresh = $asset->fresh();
        $this->assertSame('work_order', $fresh->current_location);
        $this->assertSame($workOrder->id, $fresh->current_work_order_id);
        $this->assertSame('confirmed', $movement->fresh()->status);
        $this->assertSame($teamLeader->id, $movement->fresh()->confirmed_by);
    }

    public function test_a_team_leader_cannot_confirm_a_movement_arriving_at_a_site_they_do_not_lead(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $otherLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($admin)->post('/assets', ['name' => 'Grinder'])->assertRedirect();
        $asset = Asset::where('name', 'Grinder')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/movements", [
            'type' => 'site_allocation', 'to_location' => 'work_order', 'to_work_order_id' => $workOrder->id,
            'moved_at' => now()->toDateString(),
        ])->assertRedirect();
        $movement = AssetMovement::where('asset_id', $asset->id)->where('type', 'site_allocation')->firstOrFail();

        $this->actingAs($otherLeader)->post("/asset-movements/{$movement->id}/confirm")->assertForbidden();
        $this->assertSame('pending', $movement->fresh()->status);
        $this->assertSame('company_store', $asset->fresh()->current_location);
    }

    public function test_a_team_leader_can_only_dispatch_assets_currently_at_a_site_they_lead(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);
        $otherWorkOrder = $this->workOrderLedBy($admin, $this->staffUser($admin, 'Executive Team Leader'));

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Trolley', 'current_location' => 'work_order', 'current_work_order_id' => $otherWorkOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Trolley')->firstOrFail();

        // Not their site - blocked.
        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/movements", [
            'type' => 'site_to_site_transfer', 'to_location' => 'work_order', 'to_work_order_id' => $workOrder->id,
            'moved_at' => now()->toDateString(),
        ])->assertForbidden();

        // Confirm it's at their own site instead, then they can dispatch it onward.
        $asset->update(['current_work_order_id' => $workOrder->id]);
        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/movements", [
            'type' => 'site_return', 'to_location' => 'company_store',
            'moved_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('asset_movements', ['asset_id' => $asset->id, 'type' => 'site_return', 'status' => 'pending']);
    }

    public function test_a_pending_movement_can_be_edited_and_cancelled_but_not_after_confirmation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Welding Machine'])->assertRedirect();
        $asset = Asset::where('name', 'Welding Machine')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/movements", [
            'type' => 'other', 'to_location' => 'in_transit', 'moved_at' => now()->toDateString(),
        ])->assertRedirect();
        $movement = AssetMovement::where('asset_id', $asset->id)->where('type', 'other')->firstOrFail();

        $this->actingAs($admin)->put("/asset-movements/{$movement->id}", [
            'type' => 'other', 'to_location' => 'other', 'moved_at' => now()->toDateString(), 'remarks' => 'Corrected',
        ])->assertRedirect();
        $this->assertSame('other', $movement->fresh()->to_location);
        $this->assertSame('Corrected', $movement->fresh()->remarks);

        $this->actingAs($admin)->post("/asset-movements/{$movement->id}/confirm")->assertRedirect();
        $this->assertSame('confirmed', $movement->fresh()->status);

        // Now that it's confirmed, it's locked - no further edits or cancellation.
        $this->actingAs($admin)->put("/asset-movements/{$movement->id}", [
            'type' => 'other', 'to_location' => 'company_store', 'moved_at' => now()->toDateString(),
        ])->assertStatus(422);
        $this->actingAs($admin)->post("/asset-movements/{$movement->id}/cancel")->assertStatus(422);
    }

    public function test_movement_history_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Vibrator Machine'])->assertRedirect();
        $asset = Asset::where('name', 'Vibrator Machine')->firstOrFail();

        $response = $this->actingAs($admin)->get("/assets/{$asset->id}/movements/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_movement_list_search_and_filters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Bosch Drill'])->assertRedirect();
        $drill = Asset::where('name', 'Bosch Drill')->firstOrFail();

        $this->actingAs($admin)->get('/asset-movements?q=Drill')->assertOk()->assertSee('Bosch Drill');
        $this->actingAs($admin)->get('/asset-movements?type=purchase')->assertOk()->assertSee('Bosch Drill');
        $this->actingAs($admin)->get('/asset-movements?type=site_allocation')->assertOk()->assertDontSee('Bosch Drill');
    }

    public function test_a_management_user_can_create_and_edit_movements_but_not_confirm_them(): void
    {
        $admin = $this->admin();
        $management = $this->staffUser($admin, 'Management');

        $this->actingAs($admin)->post('/assets', ['name' => 'Cutting Machine'])->assertRedirect();
        $asset = Asset::where('name', 'Cutting Machine')->firstOrFail();

        $this->actingAs($management)->post("/assets/{$asset->id}/movements", [
            'type' => 'other', 'to_location' => 'in_transit', 'moved_at' => now()->toDateString(),
        ])->assertRedirect();
        $movement = AssetMovement::where('asset_id', $asset->id)->where('type', 'other')->firstOrFail();

        $this->actingAs($management)->post("/asset-movements/{$movement->id}/confirm")->assertForbidden();
    }

    public function test_movement_history_still_loads_after_the_referenced_asset_is_removed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Doomed Asset'])->assertRedirect();
        $asset = Asset::where('name', 'Doomed Asset')->firstOrFail();

        // Removing an asset soft-deletes it, but its movement history (the
        // auto-recorded purchase entry, at least) stays in the global list.
        $this->actingAs($admin)->delete("/assets/{$asset->id}")->assertRedirect();

        $this->actingAs($admin)->get('/asset-movements')
            ->assertOk()
            ->assertSee('Doomed Asset');
    }
}
