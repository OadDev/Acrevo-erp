<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tickets previously showed the comment thread only on the internal side;
 * the client portal never loaded or displayed comments at all, and had no
 * route to post one. This makes the comment thread a genuine two-way
 * Company <-> Client channel for both company-raised and client-raised
 * tickets, all stored on the same ticket_comments row so it stays a single
 * record for the ticket's history.
 */
class TicketCommentsBidirectionalTest extends TestCase
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

    private function clientWithWorkOrder(User $admin, ?WorkOrder &$workOrder = null): User
    {
        $client = Client::create(['name' => 'Pari', 'email' => 'pari+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();

        return ClientLogin::where('client_id', $client->id)->firstOrFail()->user;
    }

    public function test_client_can_view_and_add_comments_on_a_company_raised_ticket(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $clientUser = $this->clientWithWorkOrder($admin, $workOrder);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Company-raised issue',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
        $ticket->comments()->create(['comment' => 'We noticed a delay on site.', 'user_id' => $admin->id]);

        $this->actingAs($clientUser)->get(route('portal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('We noticed a delay on site.');

        $this->actingAs($clientUser)->post(route('portal.tickets.comments.store', $ticket), [
            'comment' => 'Thanks for the update, please proceed.',
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id, 'comment' => 'Thanks for the update, please proceed.', 'user_id' => $clientUser->id,
        ]);

        // Staff must see the client's reply on the internal ticket page too.
        $this->actingAs($admin)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Thanks for the update, please proceed.')
            ->assertSee('(Client)');
    }

    public function test_client_can_view_and_add_comments_on_their_own_raised_ticket(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $clientUser = $this->clientWithWorkOrder($admin, $workOrder);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Client-raised issue',
            'raised_by_type' => 'client', 'raised_by_client_id' => $clientUser->client()->id, 'status' => 'open',
        ]);

        $this->actingAs($clientUser)->post(route('portal.tickets.comments.store', $ticket), [
            'comment' => 'Any update on this?',
        ])->assertRedirect();

        // Staff replies from the internal side.
        $this->actingAs($admin)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'We are looking into it.',
        ])->assertRedirect();

        $response = $this->actingAs($clientUser)->get(route('portal.tickets.show', $ticket));
        $response->assertOk()
            ->assertSee('Any update on this?')
            ->assertSee('We are looking into it.');
    }

    public function test_a_client_cannot_comment_on_another_clients_ticket(): void
    {
        $admin = $this->admin();
        $workOrderA = null;
        $this->clientWithWorkOrder($admin, $workOrderA);
        $workOrderB = null;
        $clientBUser = $this->clientWithWorkOrder($admin, $workOrderB);

        $ticket = Ticket::create([
            'work_order_id' => $workOrderA->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Private issue',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);

        $this->actingAs($clientBUser)->post(route('portal.tickets.comments.store', $ticket), [
            'comment' => 'Trying to snoop',
        ])->assertForbidden();

        $this->assertDatabaseMissing('ticket_comments', ['ticket_id' => $ticket->id]);
    }
}
