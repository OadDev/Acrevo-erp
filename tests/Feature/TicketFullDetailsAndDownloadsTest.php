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

/**
 * Covers the five-part request: (1) clients can see a ticket's full details,
 * not just its title, (2) a closed ticket can be downloaded as a full-detail
 * PDF, (3) Admin can edit any company-side ticket including locked/other-
 * raised ones, and (5) Approval Request / Ticket PDFs and ZIPs carry full
 * detail and are scoped to their own work order.
 */
class TicketFullDetailsAndDownloadsTest extends TestCase
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

    private function executive(): User
    {
        $exec = User::create([
            'name' => 'Exec', 'email' => 'exec+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $exec->syncRoles(['Executive Team Leader']);

        return $exec;
    }

    private function clientAndUser(User $admin, ?WorkOrder &$workOrder = null): User
    {
        $client = Client::create(['name' => 'Client Co', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();

        return ClientLogin::where('client_id', $client->id)->firstOrFail()->user;
    }

    public function test_client_can_view_full_ticket_details_not_just_the_title(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $clientUser = $this->clientAndUser($admin, $workOrder);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'Crack in wall',
            'description' => 'Visible crack on the north-facing wall.',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
        $ticket->addMedia(UploadedFile::fake()->image('crack.jpg'))->toMediaCollection('attachments');

        $this->actingAs($clientUser)->get(route('portal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Crack in wall')
            ->assertSee('Visible crack on the north-facing wall.')
            ->assertSee($workOrder->work_order_no)
            ->assertSee('crack.jpg')
            ->assertSee('Admin');
    }

    public function test_a_client_cannot_view_another_clients_ticket(): void
    {
        $admin = $this->admin();
        $workOrderA = null;
        $this->clientAndUser($admin, $workOrderA);
        $workOrderB = null;
        $clientBUser = $this->clientAndUser($admin, $workOrderB);

        $ticket = Ticket::create([
            'work_order_id' => $workOrderA->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'Private issue',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);

        $this->actingAs($clientBUser)->get(route('portal.tickets.show', $ticket))->assertForbidden();
    }

    public function test_closed_ticket_can_be_downloaded_as_a_full_details_pdf(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $clientUser = $this->clientAndUser($admin, $workOrder);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'Crack in wall',
            'description' => 'Visible crack on the wall.',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'closed', 'closed_at' => now(),
        ]);
        $ticket->comments()->create(['comment' => 'Fixed it.', 'user_id' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('tickets.pdf', $ticket));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $html = view('tickets.pdf', ['ticket' => $ticket->fresh(['workOrder.client', 'raisedBy', 'raisedByClient', 'assignedTo', 'department', 'comments.user', 'media'])])->render();
        $this->assertStringContainsString('Crack in wall', $html);
        $this->assertStringContainsString('Visible crack on the wall.', $html);
        $this->assertStringContainsString('Fixed it.', $html);
        $this->assertStringContainsString($workOrder->work_order_no, $html);
    }

    public function test_ticket_zip_download_contains_only_that_tickets_attachments(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $this->clientAndUser($admin, $workOrder);

        $ticketOne = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'T1',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
        $ticketOne->addMedia(UploadedFile::fake()->image('t1.jpg'))->toMediaCollection('attachments');

        $ticketTwo = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'T2',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
        $ticketTwo->addMedia(UploadedFile::fake()->image('t2.jpg'))->toMediaCollection('attachments');

        $response = $this->actingAs($admin)->get(route('tickets.zip', $ticketOne));
        $response->assertOk();

        $path = $response->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($path);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertContains('Attachments/t1.jpg', $names);
        $this->assertNotContains('Attachments/t2.jpg', $names);
    }

    public function test_admin_can_edit_a_locked_client_raised_ticket(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $clientUser = $this->clientAndUser($admin, $workOrder);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'Wrong title',
            'description' => 'Wrong description', 'raised_by_type' => 'client',
            'raised_by_client_id' => $clientUser->client()->id, 'status' => 'open',
            'locked_at' => now(), 'locked_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('tickets.edit', $ticket))->assertOk();

        $this->actingAs($admin)->put(route('tickets.update', $ticket), [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high',
            'title' => 'Corrected title', 'description' => 'Corrected description',
        ])->assertRedirect();

        $this->assertSame('Corrected title', $ticket->fresh()->title);
        $this->assertSame('Corrected description', $ticket->fresh()->description);

        $this->actingAs($clientUser)->get(route('portal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Corrected title')
            ->assertSee('Corrected description');
    }

    public function test_a_non_raiser_executive_cannot_edit_another_executives_ticket(): void
    {
        $admin = $this->admin();
        $execOne = $this->executive();
        $execTwo = $this->executive();
        $workOrder = null;
        $this->clientAndUser($admin, $workOrder);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'Original',
            'raised_by_type' => 'internal', 'raised_by' => $execOne->id, 'status' => 'open',
        ]);

        $this->actingAs($execTwo)->get(route('tickets.edit', $ticket))->assertForbidden();
    }

    public function test_approval_request_zip_download_is_scoped_to_that_approval(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $this->clientAndUser($admin, $workOrder);

        $approval = $workOrder->approvalRequests()->create([
            'title' => 'Approve elevation', 'direction' => 'company_to_client',
            'requested_by' => $admin->id, 'status' => 'pending',
        ]);
        $approval->addMedia(UploadedFile::fake()->image('elevation.jpg'))->toMediaCollection('attachment');

        $response = $this->actingAs($admin)->get(route('work-orders.approval-requests.zip', [$workOrder, $approval]));
        $response->assertOk();

        $path = $response->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($path);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertContains('Attachments/elevation.jpg', $names);
    }

    public function test_client_can_zip_download_their_own_approval_request_attachments(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $clientUser = $this->clientAndUser($admin, $workOrder);

        $approval = $workOrder->approvalRequests()->create([
            'title' => 'Approve elevation', 'direction' => 'client_to_company',
            'requested_by_client_id' => $clientUser->client()->id, 'status' => 'pending',
        ]);
        $approval->addMedia(UploadedFile::fake()->image('site.jpg'))->toMediaCollection('attachment');

        $this->actingAs($clientUser)->get(route('portal.work-orders.approval-requests.zip', [$workOrder, $approval]))
            ->assertOk();
    }

    public function test_full_wo_pdf_includes_ticket_and_approval_full_details(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $this->clientAndUser($admin, $workOrder);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'Crack in wall',
            'description' => 'Long description of the crack issue.',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
        $ticket->addMedia(UploadedFile::fake()->image('crack.jpg'))->toMediaCollection('attachments');

        $approval = $workOrder->approvalRequests()->create([
            'title' => 'Approve elevation', 'description' => 'Please review the new elevation.',
            'direction' => 'company_to_client', 'requested_by' => $admin->id, 'status' => 'pending',
        ]);
        $approval->addMedia(UploadedFile::fake()->image('elevation.jpg'))->toMediaCollection('attachment');

        $html = view('work-orders.pdf.full', [
            'workOrder' => $workOrder->fresh(['tickets.media', 'tickets.raisedBy', 'approvalRequests.media', 'approvalRequests.requestedBy']),
            'sections' => ['tickets', 'approvals'],
        ])->render();

        $this->assertStringContainsString('Long description of the crack issue.', $html);
        $this->assertStringContainsString('crack.jpg', $html);
        $this->assertStringContainsString('Please review the new elevation.', $html);
        $this->assertStringContainsString('elevation.jpg', $html);
    }

    public function test_full_wo_zip_includes_ticket_attachments_folder(): void
    {
        $admin = $this->admin();
        $workOrder = null;
        $this->clientAndUser($admin, $workOrder);

        $ticket = Ticket::create([
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'high', 'title' => 'Crack in wall',
            'raised_by_type' => 'internal', 'raised_by' => $admin->id, 'status' => 'open',
        ]);
        $ticket->addMedia(UploadedFile::fake()->image('crack.jpg'))->toMediaCollection('attachments');

        $exporter = app(\App\Services\WorkOrderZipExporter::class);
        $zipPath = sys_get_temp_dir().'/test-wo-zip-'.uniqid().'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $exporter->addWorkOrder($zip, $workOrder->fresh(['tickets.media']), array_keys(\App\Support\WorkOrderPdfSections::SECTIONS));
        $zip->close();

        $reopen = new \ZipArchive;
        $reopen->open($zipPath);
        $names = [];
        for ($i = 0; $i < $reopen->numFiles; $i++) {
            $names[] = $reopen->getNameIndex($i);
        }
        $reopen->close();
        @unlink($zipPath);

        $this->assertContains('Ticket Attachments/crack.jpg', $names);
    }
}
