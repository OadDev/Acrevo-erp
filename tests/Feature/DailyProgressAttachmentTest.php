<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\DailyProgressReport;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Files uploaded while submitting a progress report must attach to that
 * specific report (its own media collection), not the work order's general
 * Media Gallery - report and files are saved together as one entry.
 */
class DailyProgressAttachmentTest extends TestCase
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
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium', 'execution_way' => 'way_2',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    public function test_submitting_a_progress_report_attaches_files_to_that_report_not_the_general_gallery(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/progress", [
            'date' => now()->toDateString(), 'completed_work' => 'Floor 1 plastering completed',
            'files' => [
                UploadedFile::fake()->image('photo1.jpg'),
                UploadedFile::fake()->image('photo2.jpg'),
                UploadedFile::fake()->image('photo3.jpg'),
            ],
        ])->assertRedirect();

        $report = DailyProgressReport::where('completed_work', 'Floor 1 plastering completed')->firstOrFail();
        $this->assertSame(3, $report->getMedia('attachments')->count());

        // The general Media Gallery (WorkOrder's own media) must stay empty -
        // these files belong to the progress report, not the work order.
        $this->assertSame(0, $workOrder->media()->count());

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=progress")
            ->assertOk()->assertSee('Floor 1 plastering completed')->assertSee('photo1.jpg');
    }

    public function test_editing_a_progress_report_can_add_more_files_and_an_individual_file_can_be_removed(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $report = $workOrder->dailyProgressReports()->create([
            'date' => now()->toDateString(), 'completed_work' => 'Initial work', 'submitted_by' => $admin->id,
        ]);
        $media = $report->addMedia(UploadedFile::fake()->image('original.jpg'))->toMediaCollection('attachments');

        $this->actingAs($admin)->put("/work-orders/{$workOrder->id}/progress/{$report->id}", [
            'date' => now()->toDateString(), 'completed_work' => 'Initial work',
            'files' => [UploadedFile::fake()->image('followup.jpg')],
        ])->assertRedirect();

        $this->assertSame(2, $report->fresh()->getMedia('attachments')->count());

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/progress/{$report->id}/media/{$media->id}")->assertRedirect();
        $this->assertSame(1, $report->fresh()->getMedia('attachments')->count());
    }

    public function test_client_can_see_a_progress_reports_related_images_and_documents_on_the_portal(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $client = $workOrder->client;
        $clientUser = User::create([
            'name' => $client->name, 'email' => $client->email ?? 'client+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $clientUser->syncRoles(['Client']);
        ClientLogin::create(['client_id' => $client->id, 'user_id' => $clientUser->id]);

        $report = $workOrder->dailyProgressReports()->create([
            'date' => now()->toDateString(), 'completed_work' => 'Floor 1 plastering completed', 'submitted_by' => $admin->id,
        ]);
        $report->addMedia(UploadedFile::fake()->image('site-photo.jpg'))->toMediaCollection('attachments');
        $report->addMedia(UploadedFile::fake()->create('inspection-note.pdf', 50))->toMediaCollection('attachments');

        $response = $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Floor 1 plastering completed')
            ->assertSee('Related Images')
            ->assertSee('Related Documents')
            ->assertSee('inspection-note.pdf');
    }

    public function test_removing_a_progress_report_removes_its_attached_media_too(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $report = $workOrder->dailyProgressReports()->create([
            'date' => now()->toDateString(), 'completed_work' => 'Work', 'submitted_by' => $admin->id,
        ]);
        $report->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('attachments');

        $this->actingAs($admin)->delete("/work-orders/{$workOrder->id}/progress/{$report->id}")->assertRedirect();

        $this->assertNull(DailyProgressReport::find($report->id));
        $this->assertDatabaseMissing('media', ['model_type' => DailyProgressReport::class, 'model_id' => $report->id]);
    }
}
