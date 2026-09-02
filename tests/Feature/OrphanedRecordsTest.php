<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Enquiry;
use App\Models\QcInspection;
use App\Models\SiteVisit;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WorkOrder and Enquiry both support soft-delete via an Admin-only Destroy
 * action. QC inspections, tickets, and site visits keep pointing at those
 * ids after the parent is gone, so their belongsTo relations resolve to
 * null (soft deletes are excluded by default) - every view that assumed
 * the parent always exists then 500s. These are regression tests for that
 * class of bug across QC, Tickets, and Site Visits.
 */
class OrphanedRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        return $admin;
    }

    private function workOrder(User $admin): WorkOrder
    {
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'pending_hr_assignment', 'created_by' => $admin->id,
        ]);
    }

    public function test_qc_index_and_show_survive_a_deleted_work_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $inspection = QcInspection::create([
            'work_order_id' => $workOrder->id, 'inspection_type' => 'daily',
            'inspected_by' => $admin->id, 'inspection_date' => now(), 'status' => 'passed',
        ]);

        $workOrder->delete();

        // Before this fix, the index row's onclick navigation only fired
        // when a work order existed, so an orphaned QC record (its work
        // order deleted) had no way to reach the show page - and with it,
        // no way to reach the Remove button - from the QC menu at all.
        $indexResponse = $this->actingAs($admin)->get('/qc');
        $indexResponse->assertOk()->assertSee('Deleted work order');
        $indexResponse->assertSee(route('qc.show', $inspection), false);

        $this->actingAs($admin)->get("/qc/{$inspection->id}")->assertOk()->assertSee('Deleted work order');

        $this->actingAs($admin)->delete("/qc/{$inspection->id}")->assertRedirect(route('qc.index'));
        $this->assertNull(QcInspection::find($inspection->id));
    }

    public function test_non_admin_cannot_remove_a_qc_inspection_from_the_index(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $inspector = User::factory()->create();
        $inspector->syncRoles(['QC Officer']);

        $inspection = QcInspection::create([
            'work_order_id' => $workOrder->id, 'inspection_type' => 'daily',
            'inspected_by' => $inspector->id, 'inspection_date' => now(), 'status' => 'passed',
        ]);

        $indexResponse = $this->actingAs($inspector)->get('/qc');
        $indexResponse->assertOk()->assertDontSee('Remove');

        $this->actingAs($inspector)->delete("/qc/{$inspection->id}")->assertForbidden();
        $this->assertNotNull(QcInspection::find($inspection->id));
    }

    public function test_tickets_index_show_and_edit_survive_a_deleted_work_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Test ticket',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);

        $workOrder->delete();

        $this->actingAs($admin)->get('/tickets')->assertOk()->assertSee('Deleted work order');
        $this->actingAs($admin)->get("/tickets/{$ticket->id}")->assertOk()->assertSee('Deleted work order');
        $this->actingAs($admin)->get("/tickets/{$ticket->id}/edit")->assertNotFound();
    }

    public function test_site_visits_index_survives_a_deleted_enquiry_and_hides_the_edit_link(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        $siteVisit = SiteVisit::create([
            'enquiry_id' => $enquiry->id, 'scheduled_at' => now(), 'assigned_to' => $admin->id,
            'status' => 'scheduled', 'created_by' => $admin->id,
        ]);

        $enquiry->delete();

        $response = $this->actingAs($admin)->get('/site-visits');
        $response->assertOk()->assertSee('Deleted enquiry')->assertDontSee(route('site-visits.edit', $siteVisit));

        $this->actingAs($admin)->get("/site-visits/{$siteVisit->id}/edit")->assertNotFound();
    }
}
