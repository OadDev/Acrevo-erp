<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderAssetTest extends TestCase
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
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_add_and_remove_an_asset_from_the_master_list(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/admin/assets', ['name' => 'Ladder'])->assertRedirect();
        $asset = Asset::where('name', 'Ladder')->firstOrFail();

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=equipment")->assertOk()->assertSee('Ladder');

        $this->actingAs($admin)->delete("/admin/assets/{$asset->id}")->assertRedirect();
        $this->assertNull(Asset::find($asset->id));
    }

    public function test_a_duplicate_asset_name_is_rejected(): void
    {
        $admin = $this->admin();
        Asset::create(['name' => 'Ladder', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/admin/assets', ['name' => 'Ladder'])->assertSessionHasErrors('name');
        $this->assertSame(1, Asset::where('name', 'Ladder')->count());
    }

    public function test_recording_an_equipment_entry_computes_in_use_quantity(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/equipment", [
            'asset_name' => 'Ladder', 'allocated_quantity' => 10, 'damaged_quantity' => 2, 'missing_quantity' => 2,
        ])->assertRedirect();

        $entry = WorkOrderAsset::firstOrFail();
        $this->assertSame(10, $entry->allocated_quantity);
        $this->assertSame(6, $entry->inUseQuantity());

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=equipment")->assertOk()
            ->assertSee('Ladder')->assertSee('10')->assertSee('6')->assertSee('2');
    }

    public function test_damaged_plus_missing_cannot_exceed_the_allocated_quantity(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/equipment", [
            'asset_name' => 'Ladder', 'allocated_quantity' => 5, 'damaged_quantity' => 3, 'missing_quantity' => 3,
        ])->assertStatus(422);

        $this->assertSame(0, WorkOrderAsset::count());
    }

    public function test_removing_an_asset_from_the_master_list_does_not_affect_entries_that_already_used_it(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $asset = Asset::create(['name' => 'Drill Machine', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/equipment", [
            'asset_name' => 'Drill Machine', 'allocated_quantity' => 5, 'damaged_quantity' => 1, 'missing_quantity' => 1,
        ])->assertRedirect();

        $asset->delete();

        $entry = WorkOrder::find($workOrder->id)->assets()->firstOrFail();
        $this->assertSame('Drill Machine', $entry->asset_name);
        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=equipment")->assertOk()->assertSee('Drill Machine');
    }

    public function test_non_admin_cannot_edit_or_remove_an_equipment_entry(): void
    {
        $admin = $this->admin();
        $sales = User::create([
            'name' => 'Sales', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);
        $workOrder = $this->workOrder($admin);
        $entry = $workOrder->assets()->create(['asset_name' => 'Ladder', 'allocated_quantity' => 10, 'created_by' => $admin->id]);

        $this->actingAs($sales)->put("/work-orders/{$workOrder->id}/equipment/{$entry->id}", ['asset_name' => 'Ladder', 'allocated_quantity' => 4])->assertForbidden();
        $this->actingAs($sales)->delete("/work-orders/{$workOrder->id}/equipment/{$entry->id}")->assertForbidden();
        $this->assertSame(10, $entry->fresh()->allocated_quantity);
    }

    public function test_the_equipment_report_can_be_downloaded_as_a_pdf(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $workOrder->assets()->create(['asset_name' => 'Ladder', 'allocated_quantity' => 10, 'damaged_quantity' => 2, 'missing_quantity' => 2, 'created_by' => $admin->id]);

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/equipment")->assertOk();
    }
}
