<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Department;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ChatManagementTest extends TestCase
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

    private function userWithRole(string $role, string $name = 'User'): User
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_a_direct_chat_can_be_started_and_messages_exchanged_with_attachments(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->post('/chat/direct', ['user_id' => $hr->id])->assertRedirect();
        $conversation = Conversation::where('type', 'direct')->firstOrFail();
        $this->assertTrue($conversation->hasParticipant($sales));
        $this->assertTrue($conversation->hasParticipant($hr));

        // Reusing the same pair doesn't create a second conversation.
        $this->actingAs($hr)->post('/chat/direct', ['user_id' => $sales->id])->assertRedirect();
        $this->assertSame(1, Conversation::where('type', 'direct')->count());

        $this->actingAs($sales)->post("/conversations/{$conversation->id}/messages", [
            'body' => 'Please review the enquiry',
            'files' => [UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $message = Message::firstOrFail();
        $this->assertSame($sales->id, $message->user_id);
        $this->assertSame(1, $message->media()->count());

        $this->actingAs($hr)->get('/chat?conversation='.$conversation->id)
            ->assertOk()->assertSee('Please review the enquiry')->assertSee('brief.pdf');

        // A stranger cannot see or post into this conversation.
        $stranger = $this->userWithRole('QC Officer', 'Stranger');
        $this->actingAs($stranger)->post("/conversations/{$conversation->id}/messages", ['body' => 'Hi'])->assertForbidden();
    }

    public function test_message_timestamps_show_ist_not_raw_utc(): void
    {
        // Regression: app.timezone is UTC, so Message::created_at is stored
        // in UTC. The chat thread displayed it raw, ~5.5 hours behind the
        // real IST send time.
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->post('/chat/direct', ['user_id' => $hr->id])->assertRedirect();
        $conversation = Conversation::where('type', 'direct')->firstOrFail();

        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-08-21 16:00:00', 'UTC'));
        $this->actingAs($sales)->post("/conversations/{$conversation->id}/messages", ['body' => 'Hello'])->assertRedirect();
        \Illuminate\Support\Carbon::setTestNow();

        // 16:00 UTC = 21:30 IST (09:30 PM), not 04:00 PM.
        $this->actingAs($hr)->get('/chat?conversation='.$conversation->id)
            ->assertOk()->assertSee('09:30 PM')->assertDontSee('04:00 PM');
    }

    public function test_a_group_can_be_created_managed_and_left(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');
        $finance = $this->userWithRole('Finance', 'Finance Person');

        $this->actingAs($sales)->post('/chat/groups', [
            'name' => 'Project Alpha', 'user_ids' => [$hr->id],
        ])->assertRedirect();

        $group = Conversation::where('type', 'group')->firstOrFail();
        $this->assertSame('Project Alpha', $group->name);
        $this->assertTrue($group->hasParticipant($sales));
        $this->assertTrue($group->hasParticipant($hr));
        $this->assertFalse($group->hasParticipant($finance));

        // Only the creator (or Admin) can add/remove members.
        $this->actingAs($finance)->post("/conversations/{$group->id}/participants", ['user_id' => $finance->id])->assertForbidden();
        $this->actingAs($sales)->post("/conversations/{$group->id}/participants", ['user_id' => $finance->id])->assertRedirect();
        $this->assertTrue($group->fresh()->hasParticipant($finance));

        $this->actingAs($sales)->delete("/conversations/{$group->id}/participants/{$finance->id}")->assertRedirect();
        $this->assertFalse($group->fresh()->hasParticipant($finance));

        $this->actingAs($hr)->post("/conversations/{$group->id}/leave")->assertRedirect();
        $this->assertFalse($group->fresh()->hasParticipant($hr));
    }

    public function test_mentioning_a_participant_notifies_them_and_tags_the_message(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');
        $finance = $this->userWithRole('Finance', 'Finance Person');

        $this->actingAs($sales)->post('/chat/groups', [
            'name' => 'Ops', 'user_ids' => [$hr->id, $finance->id],
        ])->assertRedirect();
        $group = Conversation::where('type', 'group')->firstOrFail();

        $this->actingAs($sales)->post("/conversations/{$group->id}/messages", [
            'body' => 'Reminder for @HR Person to close the invoice.',
        ])->assertRedirect();

        $message = Message::firstOrFail();
        $this->assertSame(1, $message->mentions()->count());
        $this->assertSame($hr->id, $message->mentions()->first()->user_id);

        $this->assertSame(1, $hr->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $finance->fresh()->unreadNotifications()->count());
        $this->assertStringContainsString('mentioned you', $hr->fresh()->unreadNotifications()->first()->data['message']);
        $this->assertStringNotContainsString('mentioned you', $finance->fresh()->unreadNotifications()->first()->data['message']);
    }

    public function test_unread_counts_update_as_messages_are_sent_and_read(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->post('/chat/direct', ['user_id' => $hr->id])->assertRedirect();
        $conversation = Conversation::where('type', 'direct')->firstOrFail();

        $this->actingAs($sales)->post("/conversations/{$conversation->id}/messages", ['body' => 'Hello'])->assertRedirect();

        $this->assertSame(1, $hr->fresh()->unreadConversationCount());
        $this->actingAs($hr)->get('/chat/unread-count')->assertOk()->assertJson(['count' => 1]);

        // Opening the conversation marks it read.
        $this->actingAs($hr)->get('/chat?conversation='.$conversation->id)->assertOk();
        $this->assertSame(0, $hr->fresh()->unreadConversationCount());
    }

    public function test_a_discussion_conversation_is_created_once_per_record_and_shared_by_participants(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');

        $client = \App\Models\Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $service = app(ConversationService::class);
        $first = $service->discussionFor($client);
        $second = $service->discussionFor($client->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame('discussion', $first->type);
        $this->assertSame(\App\Models\Client::class, $first->subject_type);
        $this->assertSame((string) $client->id, $first->subject_id);
    }

    public function test_the_enquiry_page_shows_a_discussion_thread_that_can_be_posted_to(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $client = \App\Models\Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = \App\Models\Enquiry::create([
            'client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1',
            'status' => 'new', 'source' => 'website', 'created_by' => $admin->id,
        ]);

        $this->actingAs($sales)->get("/enquiries/{$enquiry->id}")->assertOk()->assertSee('Discussion');

        $discussion = Conversation::where('type', 'discussion')
            ->where('subject_type', \App\Models\Enquiry::class)
            ->where('subject_id', (string) $enquiry->id)
            ->firstOrFail();

        $this->actingAs($sales)->post("/conversations/{$discussion->id}/messages", [
            'body' => 'Client wants a site visit this week.',
        ])->assertRedirect();

        $this->actingAs($sales)->get("/enquiries/{$enquiry->id}")->assertOk()->assertSee('Client wants a site visit this week.');
    }

    public function test_the_work_order_discussion_tab_shows_the_same_shared_thread(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $client = \App\Models\Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = \App\Models\Enquiry::create([
            'client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1',
            'status' => 'new', 'source' => 'website', 'created_by' => $admin->id,
        ]);
        $workOrder = \App\Models\WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}")->assertOk()->assertSee('Discussion');

        $discussion = app(ConversationService::class)->discussionFor($workOrder);
        $this->assertTrue($discussion->hasParticipant($admin));
    }

    public function test_global_search_finds_messages_only_within_the_searchers_own_conversations(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');
        $stranger = $this->userWithRole('QC Officer', 'Stranger');

        $this->actingAs($sales)->post('/chat/direct', ['user_id' => $hr->id])->assertRedirect();
        $conversation = Conversation::where('type', 'direct')->firstOrFail();
        $this->actingAs($sales)->post("/conversations/{$conversation->id}/messages", [
            'body' => 'The foundation inspection is scheduled for Monday.',
        ])->assertRedirect();

        $this->actingAs($hr)->get('/search?q=foundation+inspection')
            ->assertOk()->assertSee('The foundation inspection is scheduled for Monday');

        // The subtitle always echoes the typed query back, so check for the
        // actual message content (or lack of it) rather than the query text.
        $this->actingAs($stranger)->get('/search?q=foundation+inspection')
            ->assertOk()
            ->assertDontSee('The foundation inspection is scheduled for Monday')
            ->assertSee('No results found');
    }

    public function test_the_client_role_cannot_reach_chat_at_all(): void
    {
        $admin = $this->admin();
        $clientUser = User::create([
            'name' => 'Client User', 'email' => 'clientuser@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $clientUser->syncRoles(['Client']);

        $this->actingAs($clientUser)->get('/chat')->assertForbidden();
    }
}
