<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Ledger;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the "ZIP Download" 500 error: the ZIP export embeds
 * the full multi-section work order PDF, and mPDF's HTML parser leans on
 * PCRE - once a work order accumulates enough entries (a long-running
 * project's ledger, in production: thousands of rows), the resulting HTML
 * exceeds PHP's default 1MB pcre.backtrack_limit and WriteHTML() throws
 * Mpdf\MpdfException instead of rendering, which surfaced as an uncaught
 * 500 on both the ZIP download and the plain PDF export.
 */
class WorkOrderPdfPcreLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_work_order_pdf_with_thousands_of_ledger_entries_still_renders(): void
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);

        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $rows = [];
        for ($i = 0; $i < 6000; $i++) {
            $rows[] = [
                'work_order_id' => $workOrder->id, 'entry_date' => now(), 'type' => 'debit',
                'category' => "Cat {$i}", 'description' => "Some description text for entry number {$i} to pad content",
                'amount' => 100, 'balance' => -100 * $i, 'created_by' => $admin->id,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Ledger::insert($chunk);
        }

        $workOrder->load(['client', 'ledgers']);
        $html = view('work-orders.pdf.full', ['workOrder' => $workOrder, 'sections' => ['ledger']])->render();
        $this->assertGreaterThan(1_000_000, strlen($html));

        // This is the exact call \App\Support\PdfDocument makes - it must
        // not throw Mpdf\MpdfException for a work order this size.
        $bytes = \App\Support\Pdf::loadView('work-orders.pdf.full', ['workOrder' => $workOrder, 'sections' => ['ledger']])->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
    }
}
