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

    /**
     * Regression test: the Asset-wise Summary used to be built by grouping
     * AssetStock rows and sorting the resulting Collection in PHP, which
     * could land in a different order than the asset list below it (built
     * from a separate, database-level ORDER BY name query) - most visibly
     * after editing/adjusting an asset's stock, when the summary's PHP-side
     * order could drift out of step entirely. Both are now driven by the
     * same ORDER BY name query, so they can never disagree.
     */
    public function test_the_asset_wise_summary_matches_the_asset_list_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->allocate($workOrder, $admin, 'Zebra Winch', 2);
        $this->allocate($workOrder, $admin, 'Apple Peeler', 1);
        $asset = $this->allocate($workOrder, $admin, 'Banana Slicer', 1);

        // Simulate editing/making a change to an asset already in the
        // summary - it must stay in its alphabetical place, not jump to
        // the bottom.
        $this->actingAs($admin)->put("/assets/{$asset->id}", [
            'name' => 'Banana Slicer', 'remarks' => 'Edited.',
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment");
        $response->assertOk();

        $summaryOrder = $response->viewData('assetWiseSummary')->pluck('asset.name')->all();
        $listOrder = $response->viewData('assets')->pluck('name')->all();

        $this->assertSame(['Apple Peeler', 'Banana Slicer', 'Zebra Winch'], $summaryOrder);
        $this->assertSame($listOrder, $summaryOrder, 'Asset Summary order must match the Asset List order below it.');

        // S.No column: 1, 2, 3 in sequence.
        $response->assertSeeInOrder(['1', 'Apple Peeler', '2', 'Banana Slicer', '3', 'Zebra Winch']);
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
        $this->assertSame(6, $summary->available);
        $this->assertSame(2, $summary->damaged);
        $this->assertSame(2, $summary->missing);
    }

    /**
     * Sir reported: changing a quantity's status to In Use or Ready for
     * Return via Update Status was making it disappear from the Asset
     * List, the stat cards, and the Asset-wise Summary - and filtering by
     * that exact status found nothing either. Update Status only relabels
     * a bucket in place; it never relocates it, so none of that quantity
     * has actually left the work order. Only a Movement that actually
     * moves quantity elsewhere should make it disappear from here.
     */
    public function test_marking_quantity_in_use_or_ready_for_return_keeps_the_asset_visible_in_the_list_summary_and_filter(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $destination = $this->workOrder($admin);
        $asset = $this->allocate($workOrder, $admin, 'Generator', 10);

        $this->actingAs($admin)->post("/assets/{$asset->id}/status", [
            'location' => 'work_order', 'work_order_id' => $workOrder->id,
            'from_status' => 'available', 'quantity' => 4, 'status' => 'in_use',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/assets/{$asset->id}/status", [
            'location' => 'work_order', 'work_order_id' => $workOrder->id,
            'from_status' => 'available', 'quantity' => 3, 'status' => 'ready_for_return',
        ])->assertRedirect();
        // 3 Available, 4 In Use, 3 Ready for Return now remain at this site.

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment");
        $response->assertOk()->assertSee('Generator');
        $response->assertViewHas('stockSummary', fn ($s) => $s['total'] === 10 && $s['available'] === 3);
        $this->assertSame(4, $response->viewData('stockSummary')['in_use']);
        $this->assertSame(3, $response->viewData('stockSummary')['ready_for_return']);

        $assetIds = $response->viewData('assets')->pluck('id')->all();
        $this->assertContains($asset->id, $assetIds);

        $row = $response->viewData('assetWiseSummary')->firstWhere('asset.name', 'Generator');
        $this->assertSame(10, $row->total);
        $this->assertSame(3, $row->available);
        $this->assertSame(4, $row->in_use);
        $this->assertSame(3, $row->ready_for_return);

        // Filtering explicitly by either new status still finds the asset -
        // the filter now reads the AssetStock ledger instead of the stale
        // legacy Asset::status column.
        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment?status=in_use")
            ->assertOk()->assertSee('Generator');
        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment?status=ready_for_return")
            ->assertOk()->assertSee('Generator');

        // Moving the remaining 3 Available units out via a real Movement
        // takes Available to 0, but the asset must stay fully visible here -
        // the 4 In Use + 3 Ready for Return haven't gone anywhere.
        $this->actingAs($admin)->post("/assets/{$asset->id}/movements", [
            'type' => 'site_to_site_transfer',
            'from_location' => 'work_order', 'from_work_order_id' => $workOrder->id,
            'to_location' => 'work_order', 'to_work_order_id' => $destination->id,
            'quantity' => 3, 'moved_at' => now()->toDateString(),
        ])->assertRedirect();

        $after = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment");
        $after->assertOk()->assertSee('Generator');
        $after->assertViewHas('stockSummary', fn ($s) => $s['total'] === 7 && $s['available'] === 0);
        $this->assertSame(4, $after->viewData('stockSummary')['in_use']);
        $this->assertSame(3, $after->viewData('stockSummary')['ready_for_return']);
        $this->assertNotNull($after->viewData('assetWiseSummary')->firstWhere('asset.name', 'Generator'));
    }

    /**
     * "Total" must always reconcile to exactly Available + Damaged +
     * Missing - the only three states either page breaks out. Creating a
     * movement into this work order only reserves the quantity as
     * in_transit; it must not inflate Total here until the destination
     * actually confirms receipt.
     */
    public function test_creating_a_pending_movement_does_not_inflate_total_until_confirmed(): void
    {
        $admin = $this->admin();
        $source = $this->workOrder($admin);
        $destination = $this->workOrder($admin);
        $asset = $this->allocate($source, $admin, 'Concrete Mixer', 10);

        $this->actingAs($admin)->get("/work-orders/{$destination->id}/equipment")
            ->assertOk()
            ->assertViewHas('stockSummary', fn ($summary) => $summary['total'] === 0);

        $this->actingAs($admin)->post("/assets/{$asset->id}/movements", [
            'type' => 'site_to_site_transfer',
            'from_location' => 'work_order', 'from_work_order_id' => $source->id,
            'to_location' => 'work_order', 'to_work_order_id' => $destination->id,
            'quantity' => 4, 'moved_at' => now()->toDateString(),
        ])->assertRedirect();

        // Reserved immediately at the source - Total there drops right
        // away, since those 4 units have genuinely left the usable pool.
        $sourceResponse = $this->actingAs($admin)->get("/work-orders/{$source->id}/equipment");
        $sourceResponse->assertOk()->assertViewHas('stockSummary', fn ($summary) => $summary['total'] === 6);

        // Still only in_transit at the destination - not landed yet, so
        // Total there must stay at 0 until the movement is confirmed.
        $destinationResponse = $this->actingAs($admin)->get("/work-orders/{$destination->id}/equipment");
        $destinationResponse->assertOk()->assertViewHas('stockSummary', fn ($summary) => $summary['total'] === 0);

        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->where('to_work_order_id', $destination->id)->firstOrFail();
        $this->actingAs($admin)->post("/asset-movements/{$movement->id}/confirm")->assertRedirect();

        $this->actingAs($admin)->get("/work-orders/{$destination->id}/equipment")
            ->assertOk()
            ->assertViewHas('stockSummary', fn ($summary) => $summary['total'] === 4);
    }

    /**
     * Regression test for the "Waller" scenario reported directly: 9 units
     * at WO9 (7 available, 1 damaged, 1 missing). Moving the 7 available
     * units out to WO5 used to leave the 1 damaged + 1 missing still
     * counted in WO9's stat cards and still listed in its asset-wise
     * summary, even though nothing of that asset was actually still
     * actionable at WO9 any more. Once an asset's available quantity at a
     * location hits zero, it - and whatever damaged/missing quantity it
     * left behind there - must drop out of that location's live summary
     * entirely (Verification History and the asset's own Stock by Location
     * card remain the place to look it up), while the destination correctly
     * shows the moved quantity and Movement History keeps the full record.
     */
    public function test_an_asset_fully_moved_out_drops_its_leftover_damaged_missing_from_the_source_summary(): void
    {
        $admin = $this->admin();
        $wo9 = $this->workOrder($admin);
        $wo5 = $this->workOrder($admin);
        $asset = $this->allocate($wo9, $admin, 'Waller', 7, 1, 1);

        // Before the movement: fully visible at WO9, damaged/missing and
        // all, since 7 available units are still genuinely there.
        $before = $this->actingAs($admin)->get("/work-orders/{$wo9->id}/equipment");
        $before->assertOk()
            ->assertSee('Waller')
            ->assertViewHas('stockSummary', fn ($s) => $s['total'] === 9 && $s['available'] === 7 && $s['damaged'] === 1 && $s['missing'] === 1);

        $this->actingAs($admin)->post("/assets/{$asset->id}/movements", [
            'type' => 'site_to_site_transfer',
            'from_location' => 'work_order', 'from_work_order_id' => $wo9->id,
            'to_location' => 'work_order', 'to_work_order_id' => $wo5->id,
            'quantity' => 7, 'moved_at' => now()->toDateString(),
        ])->assertRedirect();
        $movement = \App\Models\AssetMovement::where('asset_id', $asset->id)->where('to_work_order_id', $wo5->id)->firstOrFail();
        $this->actingAs($admin)->post("/asset-movements/{$movement->id}/confirm")->assertRedirect();

        // After: WO9 has 0 available for this asset now - the leftover 1
        // damaged + 1 missing must no longer show anywhere on WO9's page.
        $after = $this->actingAs($admin)->get("/work-orders/{$wo9->id}/equipment");
        $after->assertOk()
            ->assertDontSee('Waller')
            ->assertViewHas('stockSummary', fn ($s) => $s['total'] === 0 && $s['available'] === 0 && $s['damaged'] === 0 && $s['missing'] === 0);
        $this->assertNull($after->viewData('assetWiseSummary')->firstWhere('asset.name', 'Waller'));

        // The ledger itself still has the 1 damaged + 1 missing at WO9 -
        // only the live summary stops showing it.
        $this->assertSame(1, \App\Models\AssetStock::quantityAt($asset, 'work_order', $wo9->id, 'damaged'));
        $this->assertSame(1, \App\Models\AssetStock::quantityAt($asset, 'work_order', $wo9->id, 'missing'));

        // WO5 correctly shows the 7 units that actually landed there.
        $wo5Response = $this->actingAs($admin)->get("/work-orders/{$wo5->id}/equipment");
        $wo5Response->assertOk()
            ->assertSee('Waller')
            ->assertViewHas('stockSummary', fn ($s) => $s['total'] === 7 && $s['available'] === 7);

        // Movement History retains the complete record regardless.
        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id, 'from_work_order_id' => $wo9->id, 'to_work_order_id' => $wo5->id,
            'quantity' => 7, 'status' => 'confirmed',
        ]);
    }

    public function test_removing_an_asset_drops_it_and_its_damaged_missing_quantities_from_the_summary(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $asset = $this->allocate($workOrder, $admin, 'Safety Helmet', 15, 3, 2);

        $asset->delete();

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/equipment");
        $response->assertOk()->assertDontSee('Safety Helmet');

        $summary = $response->viewData('assetWiseSummary');
        $this->assertNull($summary->firstWhere('asset.name', 'Safety Helmet'));

        $stockSummary = $response->viewData('stockSummary');
        $this->assertSame(0, $stockSummary['total']);
        $this->assertSame(0, $stockSummary['damaged']);
        $this->assertSame(0, $stockSummary['missing']);
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
