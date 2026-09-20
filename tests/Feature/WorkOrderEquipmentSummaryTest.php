<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetStock;
use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The asset-wise breakdown (Asset Name / Total Allocated / In Use / Damaged
 * / Missing) added to WO Equipment so Admin can trace a damaged or missing
 * quantity back to which asset it belongs to and recover its cost, without
 * opening each asset's own Stock by Location card one at a time.
 */
class WorkOrderEquipmentSummaryTest extends TestCase
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

    private function allocate(WorkOrder $workOrder, User $admin, string $name, int $available, int $damaged = 0, int $missing = 0): Asset
    {
        $asset = Asset::create([
            'name' => $name, 'status' => 'available', 'current_location' => 'work_order',
            'current_work_order_id' => $workOrder->id, 'quantity' => $available + $damaged + $missing,
            'created_by' => $admin->id,
        ]);

        if ($available > 0) {
            AssetStock::adjust($asset, 'work_order', $workOrder->id, 'available', $available);
        }
        if ($damaged > 0) {
            AssetStock::adjust($asset, 'work_order', $workOrder->id, 'damaged', $damaged);
        }
        if ($missing > 0) {
            AssetStock::adjust($asset, 'work_order', $workOrder->id, 'missing', $missing);
        }

        return $asset;
    }

    public function test_the_equipment_page_shows_an_asset_wise_breakdown(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->allocate($workOrder, $admin, 'Ladder', 6, 2, 2);
        $this->allocate($workOrder, $admin, 'Drill Machine', 3, 1, 1);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment");

        $response->assertOk()
            ->assertSeeInOrder(['Drill Machine', 'Ladder'])
            ->assertSee('Asset-wise Summary');
    }

    public function test_totals_and_statuses_are_aggregated_correctly_per_asset(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $this->allocate($workOrder, $admin, 'Ladder', 6, 2, 2);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment");

        $response->assertOk();
        $summary = $response->viewData('assetWiseSummary')->firstWhere('asset.name', 'Ladder');

        $this->assertSame(10, $summary->total);
        $this->assertSame(6, $summary->in_use);
        $this->assertSame(2, $summary->damaged);
        $this->assertSame(2, $summary->missing);
    }

    public function test_removing_an_asset_from_the_master_list_still_shows_its_historical_quantities(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $asset = $this->allocate($workOrder, $admin, 'Safety Helmet', 15, 3, 2);

        $asset->delete();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment");
        $response->assertOk()->assertSee('Safety Helmet');
    }

    public function test_the_asset_wise_report_can_be_downloaded_as_a_pdf(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $this->allocate($workOrder, $admin, 'Ladder', 6, 2, 2);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment/summary/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_role_without_asset_permissions_cannot_download_the_asset_wise_report(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $email = 'auditor+'.uniqid().'@example.com';
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Auditor Person', 'email' => $email, 'role' => 'Auditor',
        ])->assertRedirect();
        $auditor = User::where('email', $email)->firstOrFail();

        $this->actingAs($auditor)->get("/work-orders/{$workOrder->id}/equipment/summary/pdf")->assertForbidden();
    }
}
