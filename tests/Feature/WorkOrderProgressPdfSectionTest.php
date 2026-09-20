<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Regression test for the "Progress & Media" section PDF (WO -> PDF Download
 * -> Progress & Media). This section renders every file under "Details
 * Stored on This Work Order" plus every daily progress report's attachments.
 * Before the pcre.backtrack_limit fix, every image in that list was also
 * base64-embedded inline, making this the section most likely to exceed
 * PHP's default 1MB pcre.backtrack_limit on a work order with a sizeable
 * media gallery - it threw Mpdf\MpdfException, surfacing as a 500. This
 * hits the exact production route (work-orders.pdf.section, section=progress)
 * at a scale representative of a long-running project's gallery.
 */
class WorkOrderProgressPdfSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_progress_and_media_section_pdf_downloads_with_a_large_media_gallery(): void
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $admin->syncRoles(['Admin']);

        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        // A long-running project's "Details Stored on This Work Order" gallery.
        for ($i = 0; $i < 250; $i++) {
            $workOrder->addMedia(UploadedFile::fake()->image("gallery_{$i}.jpg", 800, 600))->toMediaCollection('images');
        }

        // Plus a run of daily progress reports, each with its own attachment.
        for ($i = 0; $i < 100; $i++) {
            $report = $workOrder->dailyProgressReports()->create([
                'date' => now()->subDays($i)->toDateString(),
                'completed_work' => "Work update {$i}",
                'submitted_by' => $admin->id,
            ]);
            $report->addMedia(UploadedFile::fake()->image("progress_{$i}.jpg", 800, 600))->toMediaCollection('attachments');
        }

        $html = view('work-orders.pdf.section', [
            'workOrder' => $workOrder->fresh(['media', 'dailyProgressReports.media']),
            'section' => 'progress',
        ])->render();
        // 350 attachments only produce a text label + link line each (no
        // embedding), so the HTML stays small regardless of gallery size -
        // this is what keeps the section clear of pcre.backtrack_limit.
        $this->assertLessThan(500_000, strlen($html));
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringContainsString('gallery_0.jpg', $html);
        $this->assertStringContainsString('progress_0.jpg', $html);

        $response = $this->actingAs($admin)->get(route('work-orders.pdf.section', [$workOrder, 'progress']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
