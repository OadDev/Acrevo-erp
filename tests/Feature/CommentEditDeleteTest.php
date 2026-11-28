<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestComment;
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
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Sir asked for Edit/Delete on comments/replies (and their attachments)
 * under both Raise Ticket and Approval Request, scoped to a user's own
 * comment, with separate Edit/Delete permissions for comments vs
 * attachments that Admin can grant per role or per user, and every
 * change recorded in the Activity Log with who and when.
 */
class CommentEditDeleteTest extends TestCase
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

    private function salesUser(string $name = 'Sales Person'): User
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Sales']);

        return $user;
    }

    /**
     * A roleless user holding only the given permissions directly -
     * revokePermissionTo() on a role-based user wouldn't remove what the
     * role itself grants, so the "lacks this permission" tests need a
     * user whose access comes entirely from direct grants instead.
     */
    private function userWithOnly(array $permissions, string $name = 'Limited User'): User
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->givePermissionTo($permissions);

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

    private function ticket(WorkOrder $workOrder, User $admin): Ticket
    {
        return Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Crack in wall',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
    }

    private function clientUserFor(WorkOrder $workOrder, User $admin): User
    {
        $this->actingAs($admin)->post("/clients/{$workOrder->client_id}/portal-access")->assertRedirect();

        return ClientLogin::where('client_id', $workOrder->client_id)->firstOrFail()->user;
    }

    // --- Ticket comments ------------------------------------------------

    public function test_a_user_can_edit_their_own_ticket_comment_text(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        $sales = $this->salesUser();

        $this->actingAs($sales)->post(route('tickets.comments.store', $ticket), ['comment' => 'Original text'])->assertRedirect();
        $comment = TicketComment::firstOrFail();

        $this->actingAs($sales)->put(route('tickets.comments.update', [$ticket, $comment]), ['comment' => 'Corrected text'])
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame('Corrected text', $comment->comment);
        $this->assertTrue($comment->isEdited());
    }

    public function test_a_user_cannot_edit_someone_elses_ticket_comment(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        $author = $this->salesUser('Author');
        $otherUser = $this->salesUser('Other');

        $this->actingAs($author)->post(route('tickets.comments.store', $ticket), ['comment' => 'Mine'])->assertRedirect();
        $comment = TicketComment::firstOrFail();

        $this->actingAs($otherUser)->put(route('tickets.comments.update', [$ticket, $comment]), ['comment' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Mine', $comment->fresh()->comment);
    }

    public function test_a_user_without_the_comment_edit_permission_cannot_edit_their_own_ticket_comment(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        // Can view/comment on any ticket (tickets.all_view), but Admin has
        // deliberately not granted the comment-edit permission.
        $user = $this->userWithOnly(['tickets.all_view']);

        $this->actingAs($user)->post(route('tickets.comments.store', $ticket), ['comment' => 'Mine'])->assertRedirect();
        $comment = TicketComment::firstOrFail();

        $this->actingAs($user)->put(route('tickets.comments.update', [$ticket, $comment]), ['comment' => 'Edited'])
            ->assertForbidden();

        $this->assertSame('Mine', $comment->fresh()->comment);
    }

    public function test_a_user_can_delete_their_own_ticket_comment(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        $sales = $this->salesUser();

        $this->actingAs($sales)->post(route('tickets.comments.store', $ticket), ['comment' => 'Remove me'])->assertRedirect();
        $comment = TicketComment::firstOrFail();

        $this->actingAs($sales)->delete(route('tickets.comments.destroy', [$ticket, $comment]))->assertRedirect();

        $this->assertSame(0, TicketComment::count());
    }

    public function test_admin_can_edit_and_delete_any_users_ticket_comment(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        $sales = $this->salesUser();

        $this->actingAs($sales)->post(route('tickets.comments.store', $ticket), ['comment' => 'Original'])->assertRedirect();
        $comment = TicketComment::firstOrFail();

        $this->actingAs($admin)->put(route('tickets.comments.update', [$ticket, $comment]), ['comment' => 'Admin fixed this'])
            ->assertRedirect();
        $this->assertSame('Admin fixed this', $comment->fresh()->comment);

        $this->actingAs($admin)->delete(route('tickets.comments.destroy', [$ticket, $comment]))->assertRedirect();
        $this->assertSame(0, TicketComment::count());
    }

    public function test_a_user_can_remove_an_attachment_from_their_own_comment_and_upload_a_replacement(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        $sales = $this->salesUser();

        $this->actingAs($sales)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'Wrong photo attached', 'files' => [UploadedFile::fake()->image('wrong.jpg')],
        ])->assertRedirect();
        $comment = TicketComment::firstOrFail();
        $wrongMedia = $comment->getFirstMedia('attachments');

        $this->actingAs($sales)->put(route('tickets.comments.update', [$ticket, $comment]), [
            'comment' => 'Correct photo attached',
            'remove_media_ids' => [$wrongMedia->id],
            'files' => [UploadedFile::fake()->image('correct.jpg')],
        ])->assertRedirect();

        $comment->refresh();
        $this->assertCount(1, $comment->getMedia('attachments'));
        $this->assertSame('correct.jpg', $comment->getFirstMedia('attachments')->file_name);
    }

    public function test_a_user_without_the_attachment_delete_permission_cannot_remove_a_comment_attachment_directly(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        // Can view/comment/attach on any ticket, but not granted the
        // separate attachment-delete permission.
        $user = $this->userWithOnly(['tickets.all_view']);

        $this->actingAs($user)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'Has a file', 'files' => [UploadedFile::fake()->image('photo.jpg')],
        ])->assertRedirect();
        $comment = TicketComment::firstOrFail();
        $media = $comment->getFirstMedia('attachments');

        $this->actingAs($user)->delete(route('tickets.comments.media.destroy', [$ticket, $comment, $media]))
            ->assertForbidden();

        $this->assertCount(1, $comment->fresh()->getMedia('attachments'));
    }

    public function test_a_client_can_edit_and_delete_their_own_ticket_comment_via_the_portal(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        $clientUser = $this->clientUserFor($wo, $admin);

        $this->actingAs($clientUser)->post(route('portal.tickets.comments.store', $ticket), ['comment' => 'Client typo'])
            ->assertRedirect();
        $comment = TicketComment::firstOrFail();

        $this->actingAs($clientUser)->put(route('portal.tickets.comments.update', [$ticket, $comment]), ['comment' => 'Client fixed it'])
            ->assertRedirect();
        $this->assertSame('Client fixed it', $comment->fresh()->comment);

        $this->actingAs($clientUser)->delete(route('portal.tickets.comments.destroy', [$ticket, $comment]))->assertRedirect();
        $this->assertSame(0, TicketComment::count());
    }

    public function test_editing_and_deleting_a_ticket_comment_is_recorded_in_the_activity_log(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $ticket = $this->ticket($wo, $admin);
        $sales = $this->salesUser();

        $this->actingAs($sales)->post(route('tickets.comments.store', $ticket), ['comment' => 'Before edit'])->assertRedirect();
        $comment = TicketComment::firstOrFail();

        $this->actingAs($sales)->put(route('tickets.comments.update', [$ticket, $comment]), ['comment' => 'After edit'])->assertRedirect();

        $updated = Activity::where('subject_type', TicketComment::class)
            ->where('subject_id', $comment->id)->where('description', 'updated')->latest()->firstOrFail();
        $this->assertSame($sales->id, $updated->causer_id);
        $this->assertSame('After edit', $updated->properties->get('attributes')['comment']);
        $this->assertSame('Before edit', $updated->properties->get('old')['comment']);

        $this->actingAs($sales)->delete(route('tickets.comments.destroy', [$ticket, $comment]))->assertRedirect();

        $deleted = Activity::where('subject_type', TicketComment::class)
            ->where('subject_id', $comment->id)->where('description', 'deleted')->latest()->firstOrFail();
        $this->assertSame($sales->id, $deleted->causer_id);
    }

    // --- Approval Request comments --------------------------------------

    public function test_a_user_can_post_edit_and_delete_their_own_approval_request_comment(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $approval = ApprovalRequest::create([
            'work_order_id' => $wo->id, 'title' => 'Paint color', 'direction' => 'company_to_client',
            'requested_by' => $admin->id, 'status' => 'pending',
        ]);
        $sales = $this->salesUser();

        $this->actingAs($sales)->post(route('work-orders.approval-requests.comments.store', [$wo, $approval]), [
            'comment' => 'Client, please confirm the shade.',
        ])->assertRedirect();
        $comment = ApprovalRequestComment::firstOrFail();

        $this->actingAs($sales)->put(route('work-orders.approval-requests.comments.update', [$wo, $approval, $comment]), [
            'comment' => 'Client, please confirm the exact shade code.',
        ])->assertRedirect();
        $this->assertSame('Client, please confirm the exact shade code.', $comment->fresh()->comment);
        $this->assertTrue($comment->fresh()->isEdited());

        $this->actingAs($sales)->delete(route('work-orders.approval-requests.comments.destroy', [$wo, $approval, $comment]))
            ->assertRedirect();
        $this->assertSame(0, ApprovalRequestComment::count());
    }

    public function test_a_user_cannot_edit_someone_elses_approval_request_comment(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $approval = ApprovalRequest::create([
            'work_order_id' => $wo->id, 'title' => 'Paint color', 'direction' => 'company_to_client',
            'requested_by' => $admin->id, 'status' => 'pending',
        ]);
        $author = $this->salesUser('Author');
        $other = $this->salesUser('Other');

        $this->actingAs($author)->post(route('work-orders.approval-requests.comments.store', [$wo, $approval]), [
            'comment' => 'Mine',
        ])->assertRedirect();
        $comment = ApprovalRequestComment::firstOrFail();

        $this->actingAs($other)->put(route('work-orders.approval-requests.comments.update', [$wo, $approval, $comment]), [
            'comment' => 'Hijacked',
        ])->assertForbidden();

        $this->assertSame('Mine', $comment->fresh()->comment);
    }

    public function test_a_client_can_post_edit_and_delete_their_own_approval_request_comment_via_the_portal(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $approval = ApprovalRequest::create([
            'work_order_id' => $wo->id, 'title' => 'Paint color', 'direction' => 'company_to_client',
            'requested_by' => $admin->id, 'status' => 'pending',
        ]);
        $clientUser = $this->clientUserFor($wo, $admin);

        $this->actingAs($clientUser)->post(route('portal.work-orders.approval-requests.comments.store', [$wo, $approval]), [
            'comment' => 'We like the blue shade.',
        ])->assertRedirect();
        $comment = ApprovalRequestComment::firstOrFail();

        $this->actingAs($clientUser)->put(route('portal.work-orders.approval-requests.comments.update', [$wo, $approval, $comment]), [
            'comment' => 'We like the navy blue shade.',
        ])->assertRedirect();
        $this->assertSame('We like the navy blue shade.', $comment->fresh()->comment);

        $this->actingAs($clientUser)->delete(route('portal.work-orders.approval-requests.comments.destroy', [$wo, $approval, $comment]))
            ->assertRedirect();
        $this->assertSame(0, ApprovalRequestComment::count());
    }

    public function test_a_client_from_a_different_work_order_cannot_touch_this_approval_requests_comments(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin);
        $otherWo = $this->workOrder($admin);
        $approval = ApprovalRequest::create([
            'work_order_id' => $wo->id, 'title' => 'Paint color', 'direction' => 'company_to_client',
            'requested_by' => $admin->id, 'status' => 'pending',
        ]);
        $sales = $this->salesUser();
        $this->actingAs($sales)->post(route('work-orders.approval-requests.comments.store', [$wo, $approval]), [
            'comment' => 'Internal note',
        ])->assertRedirect();
        $comment = ApprovalRequestComment::firstOrFail();

        $otherClientUser = $this->clientUserFor($otherWo, $admin);

        $this->actingAs($otherClientUser)->put(route('portal.work-orders.approval-requests.comments.update', [$wo, $approval, $comment]), [
            'comment' => 'Hijacked',
        ])->assertForbidden();
    }
}
