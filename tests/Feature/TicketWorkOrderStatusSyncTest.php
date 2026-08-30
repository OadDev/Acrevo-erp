<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Previously, updateStatus() only ever pushed the WO into
 * rework_in_progress and never moved it back, so a WO would stay stuck on
 * "Rework in Progress" forever after a ticket was resolved/closed - even
 * with no other active tickets. WorkOrder::syncStatusFromTickets() replaces
 * that one-directional logic with a re-derivation from all of the WO's
 * currently open/in_progress tickets, run after every ticket create/status
 * change/delete.
 */
class TicketWorkOrderStatusSyncTest extends TestCase
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

    private function ticket(WorkOrder $workOrder, User $admin, string $status = 'open'): Ticket
    {
        return Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'T',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => $status,
        ]);
    }

    public function test_wo_status_tracks_the_ticket_lifecycle_and_returns_to_in_progress_once_resolved(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $ticket = $this->ticket($workOrder, $admin, 'open');
        $workOrder->syncStatusFromTickets();
        $this->assertSame('ticket_raised', $workOrder->fresh()->status);

        $this->actingAs($admin)->post("/tickets/{$ticket->id}/status", ['status' => 'in_progress'])->assertRedirect();
        $this->assertSame('rework_in_progress', $workOrder->fresh()->status);

        $this->actingAs($admin)->post("/tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('in_progress', $workOrder->fresh()->status, 'WO must leave rework_in_progress once its only ticket is resolved.');

        $this->actingAs($admin)->post("/tickets/{$ticket->id}/status", ['status' => 'closed'])->assertRedirect();
        $this->assertSame('in_progress', $workOrder->fresh()->status);
    }

    public function test_wo_stays_in_a_ticket_driven_state_while_another_ticket_is_still_active(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $ticketOne = $this->ticket($workOrder, $admin, 'open');
        $ticketTwo = $this->ticket($workOrder, $admin, 'open');
        $workOrder->syncStatusFromTickets();

        $this->actingAs($admin)->post("/tickets/{$ticketOne->id}/status", ['status' => 'in_progress'])->assertRedirect();
        $this->assertSame('rework_in_progress', $workOrder->fresh()->status);

        // Resolving the in-progress ticket while ticketTwo is still open must
        // drop the WO back to ticket_raised, not all the way to in_progress.
        $this->actingAs($admin)->post("/tickets/{$ticketOne->id}/status", ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('ticket_raised', $workOrder->fresh()->status);

        $this->actingAs($admin)->post("/tickets/{$ticketTwo->id}/status", ['status' => 'closed'])->assertRedirect();
        $this->assertSame('in_progress', $workOrder->fresh()->status);
    }

    public function test_deleting_the_only_active_ticket_returns_the_wo_to_in_progress(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $ticket = $this->ticket($workOrder, $admin, 'in_progress');
        $workOrder->syncStatusFromTickets();
        $this->assertSame('rework_in_progress', $workOrder->fresh()->status);

        $this->actingAs($admin)->delete("/tickets/{$ticket->id}")->assertRedirect();
        $this->assertSame('in_progress', $workOrder->fresh()->status);
    }

    public function test_sync_never_disturbs_a_wo_status_unrelated_to_tickets(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $workOrder->update(['status' => 'qc_pending']);
        $this->ticket($workOrder, $admin, 'resolved');

        $workOrder->syncStatusFromTickets();

        $this->assertSame('qc_pending', $workOrder->fresh()->status, 'A resolved ticket with no active siblings must not touch an unrelated WO status.');
    }

    public function test_client_raised_tickets_drive_the_same_wo_status_sync(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();
        $clientUser = \App\Models\ClientLogin::where('client_id', $client->id)->firstOrFail()->user;

        $this->actingAs($clientUser)->post('/portal/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'title' => 'Client issue',
        ])->assertRedirect();

        $this->assertSame('ticket_raised', $workOrder->fresh()->status);

        $ticket = Ticket::firstOrFail();
        $this->actingAs($admin)->post("/tickets/{$ticket->id}/status", ['status' => 'closed'])->assertRedirect();
        $this->assertSame('in_progress', $workOrder->fresh()->status);
    }
}
