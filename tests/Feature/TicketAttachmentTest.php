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
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TicketAttachmentTest extends TestCase
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

    private function user(string $role): User
    {
        $user = User::create([
            'name' => $role.' User', 'email' => strtolower(str_replace(' ', '', $role)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function workOrder(User $admin, ?Client $client = null): WorkOrder
    {
        $client ??= Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    public function test_raising_a_ticket_can_attach_multiple_files_in_one_submission(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $sales = $this->user('Sales');

        $response = $this->actingAs($sales)->post('/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Cracked tile',
            'files' => [
                UploadedFile::fake()->image('crack1.jpg'),
                UploadedFile::fake()->image('crack2.jpg'),
            ],
        ]);
        $response->assertRedirect();

        $ticket = Ticket::where('title', 'Cracked tile')->firstOrFail();
        $this->assertSame(2, $ticket->media()->count());

        $this->actingAs($sales)->get("/tickets/{$ticket->id}")->assertOk()->assertSee('crack1.jpg')->assertSee('crack2.jpg');
    }

    public function test_editing_a_ticket_can_add_more_files_and_an_individual_file_can_be_removed(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $creator = $this->user('Sales');

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Delay issue',
            'raised_by_type' => 'internal', 'raised_by' => $creator->id, 'status' => 'open',
        ]);
        $media = $ticket->addMedia(UploadedFile::fake()->create('original.pdf', 50))->toMediaCollection('attachments');

        $this->actingAs($creator)->put("/tickets/{$ticket->id}", [
            'work_order_id' => $workOrder->id, 'type' => 'delay', 'priority' => 'medium', 'title' => 'Delay issue',
            'files' => [UploadedFile::fake()->create('followup.pdf', 50)],
        ])->assertRedirect();

        $this->assertSame(2, $ticket->fresh()->media()->count());

        $this->actingAs($creator)->delete("/tickets/{$ticket->id}/media/{$media->id}")->assertRedirect();
        $this->assertSame(1, $ticket->fresh()->media()->count());
    }

    public function test_a_client_raising_a_portal_ticket_can_attach_files(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'Pari', 'email' => 'pari+'.uniqid().'@example.com', 'phone' => '1', 'address' => 'Addr', 'is_active' => true, 'created_by' => $admin->id]);
        $clientUser = User::create([
            'name' => $client->name, 'email' => $client->email,
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $clientUser->syncRoles(['Client']);
        ClientLogin::create(['client_id' => $client->id, 'user_id' => $clientUser->id]);
        $workOrder = $this->workOrder($admin, $client);

        $response = $this->actingAs($clientUser)->post('/portal/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'title' => 'Leaking pipe',
            'files' => [UploadedFile::fake()->image('leak.jpg')],
        ]);
        $response->assertRedirect();

        $ticket = Ticket::where('title', 'Leaking pipe')->firstOrFail();
        $this->assertSame(1, $ticket->media()->count());
    }
}
