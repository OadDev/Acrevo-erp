<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Ticket Comment/Reply could only ever carry text - no way to attach a
 * photo or document alongside a comment. Attachments must also stay
 * visible in the ticket's history (comments + files) after the ticket is
 * resolved, since that's the whole point of keeping them.
 */
class TicketCommentAttachmentTest extends TestCase
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

    private function ticket(User $admin): Ticket
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        return Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Crack in wall',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
    }

    public function test_a_comment_can_be_posted_with_an_attachment_and_it_shows_on_the_ticket(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);

        $this->actingAs($admin)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'Here is a photo of the crack.',
            'files' => [UploadedFile::fake()->image('crack.jpg')],
        ])->assertRedirect();

        $comment = TicketComment::firstOrFail();
        $this->assertCount(1, $comment->getMedia('attachments'));
        $this->assertSame('crack.jpg', $comment->getFirstMedia('attachments')->file_name);

        $this->actingAs($admin)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Here is a photo of the crack.')
            ->assertSee('crack.jpg');
    }

    public function test_a_client_can_post_a_comment_with_an_attachment_via_the_portal(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $this->actingAs($admin)->post("/clients/{$ticket->workOrder->client_id}/portal-access")->assertRedirect();
        $clientUser = ClientLogin::where('client_id', $ticket->workOrder->client_id)->firstOrFail()->user;

        $this->actingAs($clientUser)->post(route('portal.tickets.comments.store', $ticket), [
            'comment' => 'Attaching the invoice copy.',
            'files' => [UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $comment = TicketComment::firstOrFail();
        $this->assertCount(1, $comment->getMedia('attachments'));

        $this->actingAs($clientUser)->get(route('portal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Attaching the invoice copy.')
            ->assertSee('invoice.pdf');
    }

    public function test_comments_and_attachments_remain_visible_after_the_ticket_is_resolved(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);

        $this->actingAs($admin)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'Fixed the crack.',
            'files' => [UploadedFile::fake()->image('after.jpg')],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('tickets.status', $ticket), ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('resolved', $ticket->fresh()->status);

        $this->actingAs($admin)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Fixed the crack.')
            ->assertSee('after.jpg');

        $this->actingAs($admin)->get(route('tickets.pdf', $ticket))->assertOk();
    }

    public function test_an_oversized_comment_attachment_is_rejected_with_an_error(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);

        $this->actingAs($admin)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'Too big',
            'files' => [UploadedFile::fake()->create('huge.pdf', 30000, 'application/pdf')],
        ])->assertSessionHasErrors('files.0');

        $this->assertSame(0, TicketComment::count());
    }
}
