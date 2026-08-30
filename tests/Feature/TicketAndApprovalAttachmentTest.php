<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Ticket raise/edit already accepted multiple files but rejected videos;
 * Approval Request only ever accepted a single non-video file. Both now
 * accept any number of images/documents/videos in one submission, and every
 * uploaded file stays linked and viewable afterward.
 */
class TicketAndApprovalAttachmentTest extends TestCase
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

    public function test_raising_a_ticket_accepts_a_video_alongside_other_multiple_files(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $response = $this->actingAs($admin)->post('/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Crack in wall',
            'files' => [
                UploadedFile::fake()->image('photo.jpg'),
                UploadedFile::fake()->create('site_walkthrough.mp4', 500, 'video/mp4'),
                UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ],
        ]);
        $response->assertRedirect();

        $ticket = \App\Models\Ticket::firstOrFail();
        $this->assertCount(3, $ticket->media);
        $this->assertTrue($ticket->media->contains('file_name', 'site_walkthrough.mp4'));

        $this->actingAs($admin)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('photo.jpg')
            ->assertSee('site_walkthrough.mp4')
            ->assertSee('report.pdf');
    }

    public function test_client_can_raise_a_ticket_with_a_video_and_multiple_files_via_the_portal(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();
        $clientUser = ClientLogin::where('client_id', $client->id)->firstOrFail()->user;

        $response = $this->actingAs($clientUser)->post('/portal/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'title' => 'Water leakage',
            'files' => [
                UploadedFile::fake()->create('leak.mov', 300, 'video/quicktime'),
                UploadedFile::fake()->image('leak.jpg'),
            ],
        ]);
        $response->assertRedirect();

        $ticket = \App\Models\Ticket::firstOrFail();
        $this->assertCount(2, $ticket->media);
    }

    public function test_raising_an_approval_request_accepts_a_video_alongside_multiple_files(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $response = $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/approval-requests", [
            'title' => 'Approve elevation change',
            'files' => [
                UploadedFile::fake()->image('elevation.jpg'),
                UploadedFile::fake()->create('walkthrough.mp4', 500, 'video/mp4'),
                UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf'),
            ],
        ]);
        $response->assertRedirect();

        $approval = $workOrder->fresh()->approvalRequests()->firstOrFail();
        $this->assertCount(3, $approval->getMedia('attachment'));
        $this->assertTrue($approval->getMedia('attachment')->contains('file_name', 'walkthrough.mp4'));

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=approvals")
            ->assertOk()
            ->assertSee('elevation.jpg')
            ->assertSee('walkthrough.mp4')
            ->assertSee('spec.pdf');
    }

    public function test_a_client_can_send_an_approval_request_with_multiple_files_including_video(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();
        $clientUser = ClientLogin::where('client_id', $client->id)->firstOrFail()->user;

        $response = $this->actingAs($clientUser)->post("/portal/work-orders/{$workOrder->id}/approval-requests", [
            'title' => 'Please review this change',
            'files' => [
                UploadedFile::fake()->create('site_video.avi', 400, 'video/x-msvideo'),
                UploadedFile::fake()->image('site.jpg'),
            ],
        ]);
        $response->assertRedirect();

        $approval = $workOrder->fresh()->approvalRequests()->firstOrFail();
        $this->assertCount(2, $approval->getMedia('attachment'));

        $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}")
            ->assertOk()
            ->assertSee('site_video.avi')
            ->assertSee('site.jpg');
    }

    public function test_the_full_work_order_pdf_lists_every_approval_request_attachment_not_just_the_first(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $approval = $workOrder->approvalRequests()->create([
            'title' => 'Approve elevation change', 'direction' => 'company_to_client',
            'requested_by' => $admin->id, 'status' => 'pending',
        ]);
        $approval->addMedia(UploadedFile::fake()->image('elevation.jpg'))->toMediaCollection('attachment');
        $approval->addMedia(UploadedFile::fake()->create('walkthrough.mp4', 500, 'video/mp4'))->toMediaCollection('attachment');

        $html = view('work-orders.pdf.full', [
            'workOrder' => $workOrder->fresh(['approvalRequests.media']),
            'sections' => ['approvals'],
        ])->render();

        $this->assertStringContainsString('elevation.jpg', $html);
        $this->assertStringContainsString('walkthrough.mp4', $html);
    }
}
