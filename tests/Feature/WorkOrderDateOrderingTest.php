<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Material Inward, Daily Material Used Entry, Used Man Power, Measurement
 * Book, Worker Attendance, Site Ledger, Daily Work with Checklist, Daily
 * Progress Report, Monthly Summary, and QC Inspections all used to list
 * entries in insertion order (or, for the two Daily* tabs, newest-created
 * first) - entering a backdated row after later ones left the table out of
 * chronological order. All now order by their date column (at the
 * WorkOrder relation level, so every view/PDF that reads them gets the same
 * ordering for free) regardless of the order entries were saved in.
 */
class WorkOrderDateOrderingTest extends TestCase
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

    public function test_material_inward_entries_display_in_date_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/materials", [
            'entry_date' => '2026-08-20', 'material_name' => 'Cement', 'unit' => 'Bag', 'quantity' => 10, 'rate' => 400,
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/materials", [
            'entry_date' => '2026-08-10', 'material_name' => 'Sand', 'unit' => 'Cft', 'quantity' => 5, 'rate' => 100,
        ])->assertRedirect();

        $names = $workOrder->materialEntries()->pluck('material_name')->all();
        $this->assertSame(['Sand', 'Cement'], $names);
    }

    public function test_used_man_power_entries_display_in_date_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/labour", [
            'entry_date' => '2026-08-20', 'labour_type' => 'Mason', 'count' => 2, 'wage_rate' => 800,
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/labour", [
            'entry_date' => '2026-08-10', 'labour_type' => 'Helper', 'count' => 3, 'wage_rate' => 500,
        ])->assertRedirect();

        $types = $workOrder->labourEntries()->pluck('labour_type')->all();
        $this->assertSame(['Helper', 'Mason'], $types);
    }

    public function test_daily_material_used_entries_display_in_date_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/material-usage", [
            'date' => '2026-08-20', 'material_name' => 'Cement', 'quantity' => 2, 'unit' => 'Bag',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/material-usage", [
            'date' => '2026-08-10', 'material_name' => 'Sand', 'quantity' => 1, 'unit' => 'Cft',
        ])->assertRedirect();

        $names = $workOrder->materialUsageEntries()->pluck('material_name')->all();
        $this->assertSame(['Sand', 'Cement'], $names);
    }

    public function test_measurement_book_entries_display_in_date_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books", [
            'description' => 'Second visit', 'date' => '2026-08-20',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books", [
            'description' => 'First visit', 'date' => '2026-08-10',
        ])->assertRedirect();

        $descriptions = $workOrder->measurementBooks()->pluck('description')->all();
        $this->assertSame(['First visit', 'Second visit'], $descriptions);
    }

    public function test_site_ledger_accepts_a_date_on_creation_and_orders_entries_chronologically(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        // Entered out of order: later date first, then a backdated entry.
        $this->actingAs($admin)->post(route('work-orders.ledger.store', $workOrder), [
            'entry_date' => '2026-08-20', 'type' => 'credit', 'amount' => 1000,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('work-orders.ledger.store', $workOrder), [
            'entry_date' => '2026-08-10', 'type' => 'debit', 'amount' => 300,
        ])->assertRedirect();

        $entries = $workOrder->ledgers()->get();
        $this->assertSame(['2026-08-10', '2026-08-20'], $entries->map(fn ($e) => $e->entry_date->format('Y-m-d'))->all());

        // Balances recalculated in date order: the backdated debit applies
        // BEFORE the credit chronologically, not after it was inserted.
        $this->assertEquals(-300, (float) $entries->first()->balance);
        $this->assertEquals(700, (float) $entries->last()->balance);
    }

    public function test_company_ledger_orders_entries_chronologically_and_recalculates_balances(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        // Entered out of order: later date first, then a backdated entry.
        $this->actingAs($admin)->post(route('work-orders.company-ledger.store', $workOrder), [
            'entry_date' => '2026-08-20', 'type' => 'credit', 'amount' => 1000,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('work-orders.company-ledger.store', $workOrder), [
            'entry_date' => '2026-08-10', 'type' => 'debit', 'amount' => 300,
        ])->assertRedirect();

        $entries = $workOrder->companyLedgers()->get();
        $this->assertSame(['2026-08-10', '2026-08-20'], $entries->map(fn ($e) => $e->entry_date->format('Y-m-d'))->all());

        // Balances recalculated in date order: the backdated debit applies
        // BEFORE the credit chronologically, not after it was inserted.
        $this->assertEquals(-300, (float) $entries->first()->balance);
        $this->assertEquals(700, (float) $entries->last()->balance);
    }

    public function test_daily_work_with_checklist_entries_display_in_date_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $workOrder->update(['execution_way' => 'way_2']);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'date' => '2026-09-01', 'title' => 'September 1st work', 'items' => 'Item A',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'date' => '2026-08-30', 'title' => 'August 30th work', 'items' => 'Item B',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/checklists", [
            'date' => '2026-08-31', 'title' => 'August 31st work', 'items' => 'Item C',
        ])->assertRedirect();

        $titles = $workOrder->dailyChecklists()->pluck('title')->all();
        $this->assertSame(['August 30th work', 'August 31st work', 'September 1st work'], $titles);
    }

    public function test_daily_progress_report_entries_display_in_date_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $workOrder->update(['execution_way' => 'way_2']);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/progress", [
            'date' => '2026-09-01', 'completed_work' => 'September 1st progress',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/progress", [
            'date' => '2026-08-30', 'completed_work' => 'August 30th progress',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/progress", [
            'date' => '2026-08-31', 'completed_work' => 'August 31st progress',
        ])->assertRedirect();

        $work = $workOrder->dailyProgressReports()->pluck('completed_work')->all();
        $this->assertSame(['August 30th progress', 'August 31st progress', 'September 1st progress'], $work);
    }

    public function test_monthly_summary_entries_display_in_date_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/summary", [
            'entry_date' => '2026-09-01', 'status' => 'done', 'responsibility' => 'company',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/summary", [
            'entry_date' => '2026-08-30', 'status' => 'done', 'responsibility' => 'company',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/summary", [
            'entry_date' => '2026-08-31', 'status' => 'not_done', 'responsibility' => 'client',
        ])->assertRedirect();

        $dates = $workOrder->summaries()->get()->map(fn ($e) => $e->entry_date->format('Y-m-d'))->all();
        $this->assertSame(['2026-08-30', '2026-08-31', '2026-09-01'], $dates);
    }

    public function test_qc_inspection_entries_display_in_date_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/qc', [
            'work_order_id' => $workOrder->id, 'inspection_date' => '2026-09-01',
            'inspection_type' => 'daily', 'status' => 'passed', 'remarks' => 'September 1st check',
        ])->assertRedirect();
        $this->actingAs($admin)->post('/qc', [
            'work_order_id' => $workOrder->id, 'inspection_date' => '2026-08-30',
            'inspection_type' => 'daily', 'status' => 'passed', 'remarks' => 'August 30th check',
        ])->assertRedirect();
        $this->actingAs($admin)->post('/qc', [
            'work_order_id' => $workOrder->id, 'inspection_date' => '2026-08-31',
            'inspection_type' => 'daily', 'status' => 'passed', 'remarks' => 'August 31st check',
        ])->assertRedirect();

        $remarks = $workOrder->qcInspections()->pluck('remarks')->all();
        $this->assertSame(['August 30th check', 'August 31st check', 'September 1st check'], $remarks);
    }
}
