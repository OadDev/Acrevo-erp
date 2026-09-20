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
 * Ledger bills, Company Ledger bills, and Checklist proofs previously had
 * no way to be removed short of deleting the whole entry - only the
 * general Media Gallery and Progress Report attachments had a dedicated
 * Remove action. Admin can now clear just the attachment on these three
 * types too, and it disappears from a freshly-generated ZIP/PDF
 * immediately (no stale cached copy, per the new no-store headers on every
 * PDF/ZIP download response).
 */
class WorkOrderAttachmentRemovalTest extends TestCase
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

    public function test_admin_can_remove_a_ledger_bill_without_deleting_the_entry(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $ledger = Ledger::create([
            'work_order_id' => $workOrder->id, 'entry_date' => now(), 'type' => 'debit',
            'category' => 'Cement Bill', 'amount' => 5000, 'balance' => -5000, 'created_by' => $admin->id,
        ]);
        $ledger->addMedia(UploadedFile::fake()->create('cement_bill.pdf', 50, 'application/pdf'))->toMediaCollection('bill');

        $response = $this->actingAs($admin)->delete(route('work-orders.ledger.bill.destroy', [$workOrder, $ledger]));
        $response->assertRedirect();

        $this->assertDatabaseHas('ledgers', ['id' => $ledger->id]);
        $this->assertNull($ledger->fresh()->getFirstMedia('bill'));
    }

    public function test_admin_can_remove_a_company_ledger_bill_without_deleting_the_entry(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $companyLedger = $workOrder->companyLedgers()->create([
            'entry_date' => now(), 'type' => 'debit', 'category' => 'Fuel', 'amount' => 1000, 'balance' => -1000, 'created_by' => $admin->id,
        ]);
        $companyLedger->addMedia(UploadedFile::fake()->create('fuel_bill.pdf', 50, 'application/pdf'))->toMediaCollection('bill');

        $response = $this->actingAs($admin)->delete(route('work-orders.company-ledger.bill.destroy', [$workOrder, $companyLedger]));
        $response->assertRedirect();

        $this->assertDatabaseHas('company_ledgers', ['id' => $companyLedger->id]);
        $this->assertNull($companyLedger->fresh()->getFirstMedia('bill'));
    }

    public function test_admin_can_remove_a_checklist_items_proof_and_it_reverts_to_pending(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $checklist = $workOrder->dailyChecklists()->create(['title' => 'Slab Work', 'date' => now(), 'created_by' => $admin->id]);
        $item = $checklist->checklistItems()->create(['description' => 'Pour concrete', 'is_done' => true, 'done_at' => now(), 'done_by' => $admin->id]);
        $item->addMedia(UploadedFile::fake()->image('proof.jpg'))->toMediaCollection('proof');

        $response = $this->actingAs($admin)->delete(route('work-orders.checklist-items.proof.destroy', [$workOrder, $item]));
        $response->assertRedirect();

        $item->refresh();
        $this->assertNull($item->getFirstMedia('proof'));
        $this->assertFalse($item->is_done);
        $this->assertNull($item->done_at);
    }

    public function test_removing_a_ledger_bill_is_immediately_reflected_in_a_fresh_zip_download(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $ledger = Ledger::create([
            'work_order_id' => $workOrder->id, 'entry_date' => now(), 'type' => 'debit',
            'category' => 'Cement Bill', 'amount' => 5000, 'balance' => -5000, 'created_by' => $admin->id,
        ]);
        $ledger->addMedia(UploadedFile::fake()->create('cement_bill.pdf', 50, 'application/pdf'))->toMediaCollection('bill');

        $this->actingAs($admin)->delete(route('work-orders.ledger.bill.destroy', [$workOrder, $ledger]));

        $response = $this->actingAs($admin)->get(route('work-orders.zip', $workOrder));
        $response->assertOk();

        $zip = new ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertNotContains('Ledger Bills/cement_bill.pdf', $names);
    }

    public function test_pdf_and_zip_downloads_are_never_cached(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $pdfResponse = $this->actingAs($admin)->get(route('work-orders.pdf', $workOrder));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('no-store', $pdfResponse->headers->get('Cache-Control'));

        $zipResponse = $this->actingAs($admin)->get(route('work-orders.zip', $workOrder));
        $zipResponse->assertOk();
        $this->assertStringContainsString('no-store', $zipResponse->headers->get('Cache-Control'));
    }
}
