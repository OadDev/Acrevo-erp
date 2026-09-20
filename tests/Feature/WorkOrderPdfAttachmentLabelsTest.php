<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Ledger;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Attachments used to render as a generic "Video"/"Image" label (or, for
 * images, an embedded copy of the file itself) with no indication of which
 * section/entry they belonged to. The PDF now shows only the file name
 * against a clear "Section — entry" label for every attachment type
 * (including videos, previously silently skipped) and never embeds the
 * file - the ZIP download is where the actual file is opened from.
 */
class WorkOrderPdfAttachmentLabelsTest extends TestCase
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

    public function test_progress_report_attachments_show_the_file_name_and_section_without_being_embedded(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $report = $workOrder->dailyProgressReports()->create([
            'date' => now()->toDateString(), 'completed_work' => 'Ground Floor Work', 'submitted_by' => $admin->id,
        ]);
        $report->addMedia(UploadedFile::fake()->image('IMG_001.jpg'))->toMediaCollection('attachments');

        $html = view('work-orders.pdf.full', ['workOrder' => $workOrder->fresh(['dailyProgressReports.media']), 'sections' => ['progress']])->render();

        $this->assertStringContainsString('Progress Report — Ground Floor Work', $html);
        $this->assertStringContainsString('IMG_001.jpg', $html);
        // Not embedded: no base64 image data in the markup.
        $this->assertStringNotContainsString('data:image', $html);
    }

    public function test_a_video_attachment_shows_its_file_name_instead_of_being_silently_skipped(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $report = $workOrder->dailyProgressReports()->create([
            'date' => now()->toDateString(), 'completed_work' => 'Slab Work', 'submitted_by' => $admin->id,
        ]);
        $report->addMedia(UploadedFile::fake()->create('video_01.mp4', 100, 'video/mp4'))->toMediaCollection('attachments');

        $html = view('work-orders.pdf.full', ['workOrder' => $workOrder->fresh(['dailyProgressReports.media']), 'sections' => ['progress']])->render();

        $this->assertStringContainsString('video_01.mp4', $html);
        $this->assertStringContainsString('Progress Report — Slab Work', $html);
    }

    public function test_a_ledger_bill_shows_the_site_ledger_label_and_file_name(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $ledger = Ledger::create([
            'work_order_id' => $workOrder->id, 'entry_date' => now(), 'type' => 'debit',
            'category' => 'Cement Bill', 'amount' => 5000, 'balance' => -5000, 'created_by' => $admin->id,
        ]);
        $ledger->addMedia(UploadedFile::fake()->create('cement_bill_01.pdf', 50, 'application/pdf'))->toMediaCollection('bill');

        $html = view('work-orders.pdf.full', ['workOrder' => $workOrder->fresh(['ledgers.media']), 'sections' => ['ledger']])->render();

        $this->assertStringContainsString('Site Ledger — Cement Bill', $html);
        $this->assertStringContainsString('cement_bill_01.pdf', $html);
    }

    public function test_a_daily_work_checklist_proof_shows_the_checklist_title_and_file_name(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $checklist = $workOrder->dailyChecklists()->create(['title' => 'Slab Work', 'date' => now(), 'created_by' => $admin->id]);
        $item = $checklist->checklistItems()->create(['description' => 'Pour concrete', 'is_done' => true, 'done_at' => now()]);
        $item->addMedia(UploadedFile::fake()->image('proof.jpg'))->toMediaCollection('proof');

        $html = view('work-orders.pdf.full', ['workOrder' => $workOrder->fresh(['dailyChecklists.checklistItems.media']), 'sections' => ['checklist']])->render();

        $this->assertStringContainsString('Daily Work — Slab Work', $html);
        $this->assertStringContainsString('proof.jpg', $html);
        $this->assertStringNotContainsString('data:image', $html);
    }
}
