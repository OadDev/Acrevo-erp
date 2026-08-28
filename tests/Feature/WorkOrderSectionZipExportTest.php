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

/**
 * Downloading the whole-WO ZIP just to get one section's files (e.g. Site
 * Ledger bills) meant manually sorting through every attachment afterward.
 * Each PDF-exportable section that actually has attachments now also gets
 * its own "ZIP Download" containing only that section's files.
 */
class WorkOrderSectionZipExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RolePermissionSeeder deletes and recreates the whole roles table
        // on every run, wiping any role already assigned to another user in
        // this test - so it must run exactly once, before any user is given
        // a role, never per-user.
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'name' => 'U', 'email' => 'u+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
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

    private function zipNames(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        return $names;
    }

    public function test_the_ledger_section_zip_contains_only_ledger_bills(): void
    {
        $admin = $this->userWithRole('Admin');
        $workOrder = $this->workOrder($admin);

        $workOrder->addMedia(UploadedFile::fake()->image('gallery.jpg'))->toMediaCollection('images');

        $ledger = Ledger::create([
            'work_order_id' => $workOrder->id, 'entry_date' => now(), 'type' => 'debit',
            'category' => 'Cement Bill', 'amount' => 5000, 'balance' => -5000, 'created_by' => $admin->id,
        ]);
        $ledger->addMedia(UploadedFile::fake()->create('cement_bill.pdf', 50, 'application/pdf'))->toMediaCollection('bill');

        $response = $this->actingAs($admin)->get(route('work-orders.zip.section', [$workOrder, 'ledger']));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        $names = $this->zipNames($response->getFile()->getPathname());

        $this->assertContains('Ledger Bills/cement_bill.pdf', $names);
        $this->assertNotContains('Images/gallery.jpg', $names);
        $this->assertStringNotContainsString('WO Details.pdf', implode(',', $names));
    }

    public function test_the_progress_section_zip_contains_the_general_gallery_and_progress_report_files(): void
    {
        $admin = $this->userWithRole('Admin');
        $workOrder = $this->workOrder($admin);

        $workOrder->addMedia(UploadedFile::fake()->image('gallery.jpg'))->toMediaCollection('images');
        $report = $workOrder->dailyProgressReports()->create([
            'date' => now()->toDateString(), 'completed_work' => 'Ground Floor Work', 'submitted_by' => $admin->id,
        ]);
        $report->addMedia(UploadedFile::fake()->image('site-photo.jpg'))->toMediaCollection('attachments');

        $ledger = Ledger::create([
            'work_order_id' => $workOrder->id, 'entry_date' => now(), 'type' => 'debit',
            'category' => 'Cement Bill', 'amount' => 5000, 'balance' => -5000, 'created_by' => $admin->id,
        ]);
        $ledger->addMedia(UploadedFile::fake()->create('cement_bill.pdf', 50, 'application/pdf'))->toMediaCollection('bill');

        $response = $this->actingAs($admin)->get(route('work-orders.zip.section', [$workOrder, 'progress']));
        $response->assertOk();

        $names = $this->zipNames($response->getFile()->getPathname());

        $this->assertContains('Images/gallery.jpg', $names);
        $this->assertContains('Progress Report Attachments/site-photo.jpg', $names);
        $this->assertNotContains('Ledger Bills/cement_bill.pdf', $names);
    }

    public function test_a_user_without_permission_for_a_section_cannot_download_its_zip(): void
    {
        $qc = $this->userWithRole('QC Officer');
        $admin = $this->userWithRole('Admin');
        $workOrder = $this->workOrder($admin);

        $this->actingAs($qc)
            ->get(route('work-orders.zip.section', [$workOrder, 'company-ledger']))
            ->assertNotFound();
    }

    public function test_the_full_zip_omits_a_section_the_requesting_user_cannot_see(): void
    {
        $admin = $this->userWithRole('Admin');
        $qc = $this->userWithRole('QC Officer');
        $workOrder = $this->workOrder($admin);

        $companyLedger = $workOrder->companyLedgers()->create([
            'entry_date' => now(), 'type' => 'debit', 'category' => 'Fuel', 'amount' => 1000, 'balance' => -1000, 'created_by' => $admin->id,
        ]);
        $companyLedger->addMedia(UploadedFile::fake()->create('fuel_bill.pdf', 50, 'application/pdf'))->toMediaCollection('bill');

        $response = $this->actingAs($qc)->get(route('work-orders.zip', $workOrder));
        $response->assertOk();

        $names = $this->zipNames($response->getFile()->getPathname());

        $this->assertNotContains('Company Ledger Bills/fuel_bill.pdf', $names);
    }
}
