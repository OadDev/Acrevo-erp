<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\EquipmentRequest;
use App\Models\ExecutiveTeam;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 of the Equipment & Asset Management module: the equipment
 * request workflow - Requested -> Approved -> Purchase Required/Available
 * -> Dispatched -> Received -> Completed, with Cancelled reachable before
 * dispatch. Deciding a request (approve/reject/mark available/dispatch) is
 * Admin-only; Management and Executive Team Leader can request and
 * confirm/complete receipt.
 */
class EquipmentRequestTest extends TestCase
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

    public function test_a_team_leader_can_request_equipment_for_their_own_site(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($teamLeader)->post('/equipment-requests', [
            'work_order_id' => $workOrder->id, 'item_name' => 'Hard Hats', 'quantity' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('equipment_requests', [
            'item_name' => 'Hard Hats', 'work_order_id' => $workOrder->id, 'status' => 'requested', 'requested_by' => $teamLeader->id,
        ]);
    }

    public function test_a_team_leader_cannot_request_equipment_for_a_site_they_do_not_lead(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $otherWorkOrder = $this->workOrderLedBy($admin, $this->staffUser($admin, 'Executive Team Leader'));

        $this->actingAs($teamLeader)->post('/equipment-requests', [
            'work_order_id' => $otherWorkOrder->id, 'item_name' => 'Ladders', 'quantity' => 2,
        ])->assertForbidden();
    }

    public function test_full_workflow_from_request_through_completion_needing_purchase(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($teamLeader)->post('/equipment-requests', [
            'work_order_id' => $workOrder->id, 'item_name' => 'Generator', 'quantity' => 1,
        ])->assertRedirect();
        $equipmentRequest = EquipmentRequest::where('item_name', 'Generator')->firstOrFail();

        $this->actingAs($admin)->post("/equipment-requests/{$equipmentRequest->id}/approve", [
            'decision' => 'approved', 'needs_purchase' => '1',
        ])->assertRedirect();
        $this->assertSame('purchase_required', $equipmentRequest->fresh()->status);

        $this->actingAs($admin)->post("/equipment-requests/{$equipmentRequest->id}/mark-available")->assertRedirect();
        $this->assertSame('available', $equipmentRequest->fresh()->status);

        $this->actingAs($admin)->post("/equipment-requests/{$equipmentRequest->id}/dispatch")->assertRedirect();
        $this->assertSame('dispatched', $equipmentRequest->fresh()->status);

        // Wrong-site team leader cannot receive it.
        $otherLeader = $this->staffUser($admin, 'Executive Team Leader');
        $this->actingAs($otherLeader)->post("/equipment-requests/{$equipmentRequest->id}/receive")->assertForbidden();

        $this->actingAs($teamLeader)->post("/equipment-requests/{$equipmentRequest->id}/receive")->assertRedirect();
        $this->assertSame('received', $equipmentRequest->fresh()->status);

        $this->actingAs($teamLeader)->post("/equipment-requests/{$equipmentRequest->id}/complete")->assertRedirect();
        $this->assertSame('completed', $equipmentRequest->fresh()->status);
    }

    public function test_approving_from_existing_stock_skips_purchase_required(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/equipment-requests', ['item_name' => 'Tool Box', 'quantity' => 1])->assertRedirect();
        $equipmentRequest = EquipmentRequest::where('item_name', 'Tool Box')->firstOrFail();

        $this->actingAs($admin)->post("/equipment-requests/{$equipmentRequest->id}/approve", [
            'decision' => 'approved', 'needs_purchase' => '0',
        ])->assertRedirect();

        $this->assertSame('available', $equipmentRequest->fresh()->status);
    }

    public function test_admin_can_reject_a_request_with_a_reason(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/equipment-requests', ['item_name' => 'Luxury Chair', 'quantity' => 1])->assertRedirect();
        $equipmentRequest = EquipmentRequest::where('item_name', 'Luxury Chair')->firstOrFail();

        $this->actingAs($admin)->post("/equipment-requests/{$equipmentRequest->id}/approve", [
            'decision' => 'rejected', 'rejection_reason' => 'Not required.',
        ])->assertRedirect();

        $fresh = $equipmentRequest->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('Not required.', $fresh->rejection_reason);
    }

    public function test_a_management_user_cannot_approve_a_request(): void
    {
        $admin = $this->admin();
        $management = $this->staffUser($admin, 'Management');

        $this->actingAs($management)->post('/equipment-requests', ['item_name' => 'Safety Vests', 'quantity' => 5])->assertRedirect();
        $equipmentRequest = EquipmentRequest::where('item_name', 'Safety Vests')->firstOrFail();

        $this->actingAs($management)->post("/equipment-requests/{$equipmentRequest->id}/approve", [
            'decision' => 'approved', 'needs_purchase' => '0',
        ])->assertForbidden();
    }

    public function test_the_requester_can_cancel_their_own_pending_request_but_not_after_it_is_approved(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);

        $this->actingAs($teamLeader)->post('/equipment-requests', [
            'work_order_id' => $workOrder->id, 'item_name' => 'Spare Cable', 'quantity' => 1,
        ])->assertRedirect();
        $pending = EquipmentRequest::where('item_name', 'Spare Cable')->firstOrFail();

        // Still "requested" - the requester can withdraw it themselves.
        $this->actingAs($teamLeader)->post("/equipment-requests/{$pending->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $pending->fresh()->status);

        $this->actingAs($teamLeader)->post('/equipment-requests', [
            'work_order_id' => $workOrder->id, 'item_name' => 'Spare Fuse', 'quantity' => 1,
        ])->assertRedirect();
        $decided = EquipmentRequest::where('item_name', 'Spare Fuse')->firstOrFail();

        $this->actingAs($admin)->post("/equipment-requests/{$decided->id}/approve", [
            'decision' => 'approved', 'needs_purchase' => '0',
        ])->assertRedirect();

        // Already decided - the requester can no longer cancel it themselves.
        $this->actingAs($teamLeader)->post("/equipment-requests/{$decided->id}/cancel")->assertForbidden();

        // Admin can still cancel it since it hasn't been dispatched yet.
        $this->actingAs($admin)->post("/equipment-requests/{$decided->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $decided->fresh()->status);
    }

    public function test_equipment_requests_list_search_and_filters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/equipment-requests', ['item_name' => 'Extension Cord', 'category' => 'Electrical', 'quantity' => 2])->assertRedirect();

        $this->actingAs($admin)->get('/equipment-requests?q=Extension')->assertOk()->assertSee('Extension Cord');
        $this->actingAs($admin)->get('/equipment-requests?status=approved')->assertOk()->assertDontSee('Extension Cord');
        $this->actingAs($admin)->get('/equipment-requests?status=requested')->assertOk()->assertSee('Extension Cord');
    }

    public function test_equipment_requests_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/equipment-requests', ['item_name' => 'Measuring Tape', 'quantity' => 1])->assertRedirect();

        $response = $this->actingAs($admin)->get('/equipment-requests/pdf');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_team_leader_only_sees_equipment_requests_from_sites_they_lead(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');
        $workOrder = $this->workOrderLedBy($admin, $teamLeader);
        $otherLeader = $this->staffUser($admin, 'Executive Team Leader');
        $otherWorkOrder = $this->workOrderLedBy($admin, $otherLeader);

        $this->actingAs($teamLeader)->post('/equipment-requests', ['work_order_id' => $workOrder->id, 'item_name' => 'My Site Item', 'quantity' => 1])->assertRedirect();
        $this->actingAs($otherLeader)->post('/equipment-requests', ['work_order_id' => $otherWorkOrder->id, 'item_name' => 'Other Site Item', 'quantity' => 1])->assertRedirect();

        $this->actingAs($teamLeader)->get('/equipment-requests')
            ->assertOk()
            ->assertSee('My Site Item')
            ->assertDontSee('Other Site Item');
    }
}
