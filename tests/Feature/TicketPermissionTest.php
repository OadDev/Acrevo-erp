<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\ExecutiveTeam;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Raise Ticket's permissions are six independent ones - tickets.all_view,
 * tickets.assigned_view, tickets.assigned_create, tickets.assigned_manage
 * (comment only), tickets.assigned_edit, and tickets.assigned_remove -
 * each gated by its own route, so a role can hold Create/Manage without
 * also holding a View permission (previously every ticket route, Create
 * and Manage included, was nested under a single tickets.view-gated
 * group, which 403'd a role that held Create/Manage but not View).
 */
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

    private function customRole(string $name, array $permissions): User
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles([$name]);

        return $user;
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

    private function assignAsTeamLeader(WorkOrder $workOrder, User $user, User $admin): void
    {
        $team = ExecutiveTeam::create(['team_number' => 'ET-'.uniqid(), 'name' => 'Team', 'team_leader_id' => $user->id, 'is_active' => true]);
        WorkOrderExecutiveTeam::create(['work_order_id' => $workOrder->id, 'executive_team_id' => $team->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);
    }

    public function test_the_reported_bug_is_fixed_create_and_manage_work_without_any_view_permission(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);

        // A role with only Create + Manage (comment) - deliberately no
        // all_view or assigned_view at all.
        $fieldUser = $this->customRole('Field Reporter', [
            'tickets.assigned_create', 'tickets.assigned_manage',
        ]);
        $this->assignAsTeamLeader($workOrder, $fieldUser, $admin);

        $this->actingAs($fieldUser)->get("/tickets/create?work_order_id={$workOrder->id}")->assertOk();
        $response = $this->actingAs($fieldUser)->post('/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'No view needed',
        ]);
        $response->assertRedirect();
        $ticket = Ticket::where('title', 'No view needed')->firstOrFail();

        $this->actingAs($fieldUser)->post("/tickets/{$ticket->id}/comments", ['comment' => 'Checked on site'])
            ->assertRedirect();
        $this->assertSame(1, $ticket->fresh()->comments()->count());

        // No sidebar Ticket menu or global index without tickets.all_view.
        $this->actingAs($fieldUser)->get('/dashboard')->assertOk()->assertDontSee(route('tickets.index'));
        $this->actingAs($fieldUser)->get('/tickets')->assertForbidden();
    }

    public function test_tickets_all_view_shows_the_sidebar_menu_and_every_ticket(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrderOne = $this->workOrder($admin);
        $workOrderTwo = $this->workOrder($admin);

        Ticket::create([
            'work_order_id' => $workOrderOne->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'On WO one',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
        Ticket::create([
            'work_order_id' => $workOrderTwo->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'On WO two',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);

        $viewer = $this->customRole('All Ticket Viewer', ['tickets.all_view']);

        $this->actingAs($viewer)->get('/dashboard')->assertOk()->assertSee(route('tickets.index'));
        $this->actingAs($viewer)->get('/tickets')
            ->assertOk()
            ->assertSee('On WO one')
            ->assertSee('On WO two');
    }

    public function test_tickets_assigned_view_only_shows_tickets_from_the_users_assigned_work_orders(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $assignedWo = $this->workOrder($admin);
        $unassignedWo = $this->workOrder($admin);

        $assignedTicket = Ticket::create([
            'work_order_id' => $assignedWo->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Assigned WO ticket',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
        $unassignedTicket = Ticket::create([
            'work_order_id' => $unassignedWo->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Unassigned WO ticket',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);

        // work_orders.view lets this role open any Work Order page (both
        // WOs here) - tickets.assigned_view then further scopes which
        // one's Tickets tab actually shows ticket data.
        $viewer = $this->customRole('Assigned Ticket Viewer', ['tickets.assigned_view', 'work_orders.view']);
        $this->assignAsTeamLeader($assignedWo, $viewer, $admin);

        // No sidebar menu or global index - that's all_view only.
        $this->actingAs($viewer)->get('/dashboard')->assertOk()->assertDontSee(route('tickets.index'));
        $this->actingAs($viewer)->get('/tickets')->assertForbidden();

        // Can open the assigned WO's ticket, not the unassigned one's.
        $this->actingAs($viewer)->get("/tickets/{$assignedTicket->id}")->assertOk();
        $this->actingAs($viewer)->get("/tickets/{$unassignedTicket->id}")->assertForbidden();

        // The Work Order's own Tickets tab reflects the same scoping.
        $this->actingAs($viewer)->get("/work-orders/{$assignedWo->id}?tab=tickets")
            ->assertOk()->assertSee('Assigned WO ticket');
        $this->actingAs($viewer)->get("/work-orders/{$unassignedWo->id}?tab=tickets")
            ->assertOk()->assertDontSee('Unassigned WO ticket');
    }

    public function test_tickets_assigned_create_is_scoped_to_the_users_assigned_work_orders(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $creator = $this->customRole('Ticket Creator', ['tickets.assigned_create']);

        $this->actingAs($creator)->get("/tickets/create?work_order_id={$workOrder->id}")->assertForbidden();
        $this->actingAs($creator)->post('/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Cracked tile',
        ])->assertForbidden();
        $this->assertSame(0, Ticket::where('title', 'Cracked tile')->count());

        $this->assignAsTeamLeader($workOrder, $creator, $admin);

        $this->actingAs($creator)->get("/tickets/create?work_order_id={$workOrder->id}")->assertOk();
        $this->actingAs($creator)->post('/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Cracked tile',
        ])->assertRedirect();
        $this->assertSame(1, Ticket::where('title', 'Cracked tile')->count());

        // Admin bypasses the assignment check entirely.
        $otherWorkOrder = $this->workOrder($admin);
        $this->actingAs($admin)->post('/tickets', [
            'work_order_id' => $otherWorkOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Admin raised',
        ])->assertRedirect();
    }

    public function test_tickets_assigned_manage_comments_but_grants_no_edit_or_delete(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Discuss me',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);

        $commenter = $this->customRole('Ticket Commenter', ['tickets.all_view', 'tickets.assigned_manage']);

        // Not assigned yet - comment is forbidden even with the permission.
        $this->actingAs($commenter)->post("/tickets/{$ticket->id}/comments", ['comment' => 'Too early'])
            ->assertForbidden();

        $this->assignAsTeamLeader($workOrder, $commenter, $admin);

        $this->actingAs($commenter)->get("/tickets/{$ticket->id}")
            ->assertOk()->assertDontSee('Edit')->assertDontSee('Remove');

        $this->actingAs($commenter)->post("/tickets/{$ticket->id}/comments", ['comment' => 'On it'])
            ->assertRedirect();
        $this->assertSame(1, $ticket->fresh()->comments()->count());

        // No edit/delete/status rights from Manage alone.
        $this->actingAs($commenter)->get("/tickets/{$ticket->id}/edit")->assertForbidden();
        $this->actingAs($commenter)->post("/tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertForbidden();
        $this->actingAs($commenter)->delete("/tickets/{$ticket->id}")->assertForbidden();
    }

    public function test_tickets_assigned_edit_covers_ticket_fields_and_status_scoped_to_assignment(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Client issue',
            'raised_by_type' => 'client', 'status' => 'open',
        ]);

        $editor = $this->customRole('Ticket Editor', ['tickets.all_view', 'tickets.assigned_edit']);

        // Not assigned - edit and status change both forbidden, even
        // though this ticket wasn't raised by this user at all.
        $this->actingAs($editor)->get("/tickets/{$ticket->id}/edit")->assertForbidden();
        $this->actingAs($editor)->post("/tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertForbidden();

        $this->assignAsTeamLeader($workOrder, $editor, $admin);

        $this->actingAs($editor)->get("/tickets/{$ticket->id}/edit")->assertOk();
        $this->actingAs($editor)->put("/tickets/{$ticket->id}", [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'Client issue - urgent',
        ])->assertRedirect();
        $this->assertSame('Client issue - urgent', $ticket->fresh()->title);

        $this->actingAs($editor)->post("/tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('resolved', $ticket->fresh()->status);

        // Mark Uneditable (lock) is also an Edit-level action.
        $this->actingAs($editor)->post("/tickets/{$ticket->id}/lock")->assertRedirect();
        $this->assertNotNull($ticket->fresh()->locked_at);

        // Locked blocks further field edits for everyone except Admin, who
        // bypasses both the lock and the assignment check entirely (this
        // $admin was never assigned to $workOrder).
        $this->actingAs($editor)->get("/tickets/{$ticket->id}/edit")->assertForbidden();
        $this->actingAs($admin)->get("/tickets/{$ticket->id}/edit")->assertOk();
    }

    public function test_tickets_assigned_remove_is_scoped_to_the_users_assigned_work_orders(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Removable',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);

        $remover = $this->customRole('Ticket Deleter', ['tickets.all_view', 'tickets.assigned_remove']);

        $this->actingAs($remover)->get("/tickets/{$ticket->id}")->assertOk()->assertDontSee('Remove');
        $this->actingAs($remover)->delete("/tickets/{$ticket->id}")->assertForbidden();
        $this->assertNull($ticket->fresh()->deleted_at);

        $this->assignAsTeamLeader($workOrder, $remover, $admin);

        $this->actingAs($remover)->get("/tickets/{$ticket->id}")->assertOk()->assertSee('Remove');
        $this->actingAs($remover)->delete("/tickets/{$ticket->id}")->assertRedirect('/tickets');
        $this->assertNotNull($ticket->fresh()->deleted_at);
    }

    public function test_a_role_without_any_ticket_permission_is_blocked_everywhere(): void
    {
        $this->seedRoles();
        $admin = $this->user('Admin');
        $workOrder = $this->workOrder($admin);
        $this->assignAsTeamLeader($workOrder, $hr = $this->user('HR'), $admin);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Not for HR',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);

        $this->actingAs($hr)->get('/tickets')->assertForbidden();
        $this->actingAs($hr)->get("/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($hr)->get("/tickets/create?work_order_id={$workOrder->id}")->assertForbidden();
        $this->actingAs($hr)->post("/tickets/{$ticket->id}/comments", ['comment' => 'Nope'])->assertForbidden();
    }
}
