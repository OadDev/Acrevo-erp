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

class TicketPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::create([
            'name' => $role.' User', 'email' => strtolower(str_replace(' ', '', $role)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
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

    public function test_sales_and_executive_tl_can_edit_a_client_raised_ticket_until_locked(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Client issue',
            'raised_by_type' => 'client', 'status' => 'open',
        ]);

        $sales = $this->user('Sales');
        $execTl = $this->user('Executive Team Leader');
        $hr = $this->user('HR');

        $this->actingAs($sales)->get("/tickets/{$ticket->id}/edit")->assertOk();
        $this->actingAs($execTl)->get("/tickets/{$ticket->id}/edit")->assertOk();
        $this->actingAs($hr)->get("/tickets/{$ticket->id}/edit")->assertForbidden();

        $this->actingAs($sales)->post("/tickets/{$ticket->id}/lock")->assertRedirect();
        $this->assertNotNull($ticket->fresh()->locked_at);

        // Once locked, nobody can edit it anymore - not even Sales.
        $this->actingAs($sales)->get("/tickets/{$ticket->id}/edit")->assertForbidden();
        $this->actingAs($execTl)->get("/tickets/{$ticket->id}/edit")->assertForbidden();
    }

    public function test_only_the_creator_can_edit_an_internally_raised_ticket(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $creator = $this->user('Sales');
        $otherSales = $this->user('Sales');

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Internal issue',
            'raised_by_type' => 'internal', 'raised_by' => $creator->id, 'status' => 'open',
        ]);

        $this->actingAs($creator)->get("/tickets/{$ticket->id}/edit")->assertOk();
        $this->actingAs($otherSales)->get("/tickets/{$ticket->id}/edit")->assertForbidden();

        // Admin can always correct a ticket's details, even one raised by
        // someone else, so incorrect info doesn't stay wrong in front of
        // the client.
        $this->actingAs($admin)->get("/tickets/{$ticket->id}/edit")->assertOk();
    }

    public function test_locking_a_ticket_is_gated_the_same_as_editing_it(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $creator = $this->user('Sales');
        $otherSales = $this->user('Sales');

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Internal issue',
            'raised_by_type' => 'internal', 'raised_by' => $creator->id, 'status' => 'open',
        ]);

        $this->actingAs($otherSales)->post("/tickets/{$ticket->id}/lock")->assertForbidden();
        $this->actingAs($creator)->post("/tickets/{$ticket->id}/lock")->assertRedirect();
        $this->assertNotNull($ticket->fresh()->locked_at);
    }

    public function test_tickets_manage_permission_can_remove_a_ticket(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $sales = $this->user('Sales');

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Removable',
            'raised_by_type' => 'internal', 'raised_by' => $sales->id, 'status' => 'open',
        ]);

        $this->actingAs($sales)->get("/tickets/{$ticket->id}")->assertOk()->assertSee('Remove');

        $this->actingAs($sales)->delete("/tickets/{$ticket->id}")->assertRedirect('/tickets');
        $this->assertNotNull($ticket->fresh()->deleted_at);

        $this->actingAs($sales)->get('/tickets')->assertOk()->assertDontSee('Removable');
    }

    public function test_a_role_without_tickets_manage_cannot_remove_a_ticket(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $execTl = $this->user('Executive Team Leader');

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Protected',
            'raised_by_type' => 'internal', 'raised_by' => $execTl->id, 'status' => 'open',
        ]);

        $this->actingAs($execTl)->get("/tickets/{$ticket->id}")->assertOk()->assertDontSee('Remove');
        $this->actingAs($execTl)->delete("/tickets/{$ticket->id}")->assertForbidden();
        $this->assertNull($ticket->fresh()->deleted_at);
    }
}
