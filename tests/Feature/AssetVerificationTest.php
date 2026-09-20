<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetStatusLog;
use App\Models\AssetVerification;
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
 * Phase 4 of the Equipment & Asset Management module: physical
 * verification of equipment, and the always-current Missing Equipment
 * list that a "not_found" verification result (or a manual status
 * update) feeds into.
 */
class AssetVerificationTest extends TestCase
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

    public function test_verifying_an_asset_as_ok_leaves_its_status_untouched(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Concrete Mixer', 'status' => 'in_use'])->assertRedirect();
        $asset = Asset::where('name', 'Concrete Mixer')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/verifications", [
            'result' => 'verified_ok', 'condition' => 'Good', 'verified_at' => now()->toDateString(),
        ])->assertRedirect();

        $verification = AssetVerification::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('verified_ok', $verification->result);
        $this->assertSame('in_use', $asset->fresh()->status);
        $this->assertDatabaseMissing('asset_status_logs', ['asset_id' => $asset->id]);
    }

    public function test_verifying_an_asset_as_not_found_marks_it_missing_and_logs_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Wall Cutter', 'status' => 'available'])->assertRedirect();
        $asset = Asset::where('name', 'Wall Cutter')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/verifications", [
            'result' => 'not_found', 'verified_at' => now()->toDateString(), 'remarks' => 'Not at the store',
        ])->assertRedirect();

        $this->assertSame('missing', $asset->fresh()->status);

        $log = AssetStatusLog::where('asset_id', $asset->id)->latest()->firstOrFail();
        $this->assertSame('available', $log->previous_status);
        $this->assertSame('missing', $log->new_status);
    }

    public function test_verifying_an_asset_as_damaged_marks_it_damaged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Grinder', 'status' => 'in_use'])->assertRedirect();
        $asset = Asset::where('name', 'Grinder')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/verifications", [
            'result' => 'damaged', 'verified_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('damaged', $asset->fresh()->status);
    }

    public function test_a_team_leader_can_only_verify_assets_at_a_site_they_lead(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $otherWorkOrder = $this->workOrderLedBy($admin, $this->staffUser($admin, 'Executive Team Leader'));

        $this->actingAs($admin)->post('/assets', [
            'name' => 'Vibrator', 'current_location' => 'work_order', 'current_work_order_id' => $otherWorkOrder->id,
        ])->assertRedirect();
        $asset = Asset::where('name', 'Vibrator')->firstOrFail();

        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/verifications", [
            'result' => 'verified_ok', 'verified_at' => now()->toDateString(),
        ])->assertForbidden();

        $workOrder = $this->workOrderLedBy($admin, $teamLeader);
        $asset->update(['current_work_order_id' => $workOrder->id]);

        $this->actingAs($teamLeader)->post("/assets/{$asset->id}/verifications", [
            'result' => 'verified_ok', 'verified_at' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertDatabaseHas('asset_verifications', ['asset_id' => $asset->id, 'result' => 'verified_ok']);
    }

    public function test_verification_history_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Bosch Drill'])->assertRedirect();
        $asset = Asset::where('name', 'Bosch Drill')->firstOrFail();

        $response = $this->actingAs($admin)->get("/assets/{$asset->id}/verifications/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_missing_equipment_list_shows_only_missing_assets_and_supports_search(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Missing Drill', 'status' => 'available'])->assertRedirect();
        $missingAsset = Asset::where('name', 'Missing Drill')->firstOrFail();
        $this->actingAs($admin)->post("/assets/{$missingAsset->id}/status", ['status' => 'missing'])->assertRedirect();

        $this->actingAs($admin)->post('/assets', ['name' => 'Present Drill', 'status' => 'available'])->assertRedirect();

        $response = $this->actingAs($admin)->get('/missing-equipment');
        $response->assertOk()->assertSee('Missing Drill')->assertDontSee('Present Drill');

        $this->actingAs($admin)->get('/missing-equipment?q=Missing')->assertOk()->assertSee('Missing Drill');
        $this->actingAs($admin)->get('/missing-equipment?q=NoSuchThing')->assertOk()->assertDontSee('Missing Drill');
    }

    public function test_missing_equipment_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Lost Saw', 'status' => 'missing'])->assertRedirect();

        $response = $this->actingAs($admin)->get('/missing-equipment/pdf');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_team_leader_only_sees_missing_equipment_at_sites_they_lead(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);
        $otherWorkOrder = $this->workOrderLedBy($admin, $this->staffUser($admin, 'Executive Team Leader'));

        $this->actingAs($admin)->post('/assets', [
            'name' => 'My Site Missing Tool', 'status' => 'missing',
            'current_location' => 'work_order', 'current_work_order_id' => $workOrder->id,
        ])->assertRedirect();
        $this->actingAs($admin)->post('/assets', [
            'name' => 'Other Site Missing Tool', 'status' => 'missing',
            'current_location' => 'work_order', 'current_work_order_id' => $otherWorkOrder->id,
        ])->assertRedirect();

        $this->actingAs($teamLeader)->get('/missing-equipment')
            ->assertOk()
            ->assertSee('My Site Missing Tool')
            ->assertDontSee('Other Site Missing Tool');
    }

    public function test_verification_history_still_loads_after_the_referenced_asset_is_removed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Doomed Grinder'])->assertRedirect();
        $asset = Asset::where('name', 'Doomed Grinder')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/verifications", [
            'result' => 'verified_ok', 'verified_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($admin)->delete("/assets/{$asset->id}")->assertRedirect();

        $this->actingAs($admin)->get('/asset-verifications')
            ->assertOk()
            ->assertSee('Doomed Grinder');
    }
}
