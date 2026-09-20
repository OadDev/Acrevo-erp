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
use ZipArchive;

class WorkOrderZipExportTest extends TestCase
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

    public function test_zip_download_works_with_attachments_across_every_section(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $workOrder->addMedia(UploadedFile::fake()->image('gallery.jpg'))->toMediaCollection('images');

        $checklist = $workOrder->dailyChecklists()->create(['title' => 'Slab Work', 'date' => now(), 'created_by' => $admin->id]);
        $item = $checklist->checklistItems()->create(['description' => 'Pour concrete', 'is_done' => true, 'done_at' => now()]);
        $item->addMedia(UploadedFile::fake()->image('proof.jpg'))->toMediaCollection('proof');

        $report = $workOrder->dailyProgressReports()->create([
            'date' => now()->toDateString(), 'completed_work' => 'Ground Floor Work', 'submitted_by' => $admin->id,
        ]);
        $report->addMedia(UploadedFile::fake()->image('site-photo.jpg'))->toMediaCollection('attachments');

        $ledger = Ledger::create([
            'work_order_id' => $workOrder->id, 'entry_date' => now(), 'type' => 'debit',
            'category' => 'Cement Bill', 'amount' => 5000, 'balance' => -5000, 'created_by' => $admin->id,
        ]);
        $ledger->addMedia(UploadedFile::fake()->create('cement_bill.pdf', 50, 'application/pdf'))->toMediaCollection('bill');

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/zip");
        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        $path = $response->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertContains('WO Details.pdf', $names);
        $this->assertContains('Images/gallery.jpg', $names);
        $this->assertContains('Checklist Proofs/proof.jpg', $names);
        $this->assertContains('Progress Report Attachments/site-photo.jpg', $names);
        $this->assertContains('Ledger Bills/cement_bill.pdf', $names);
    }
}
