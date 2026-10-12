<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LedgerCategory;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Site Ledger and Company Ledger already had a per-section PDF download
 * (via the WO page's Download PDF dropdown); this adds a CSV option there
 * too, reusing the CSV export routes that already existed inside each tab.
 * The Finance menu (Invoices, Client Payments, Sub Contractor Payments,
 * Expenses) gets PDF + CSV downloads that honor the tab's current filters,
 * plus a new Work Order filter on the Sub Contractor Payments and Expenses
 * tabs (Expenses previously had no way to tag a work order at all).
 */
class FinanceDownloadsAndFiltersTest extends TestCase
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

    private function workOrder(User $admin, string $title = 'WO'): WorkOrder
    {
        $client = Client::create(['name' => 'C '.uniqid(), 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => $title, 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    public function test_wo_download_dropdown_offers_csv_for_ledger_and_company_ledger(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $response = $this->actingAs($admin)->get(route('work-orders.show', $workOrder));

        $response->assertOk();
        $response->assertSee(route('work-orders.ledger.export', $workOrder), false);
        $response->assertSee(route('work-orders.company-ledger.export', $workOrder), false);
    }

    public function test_site_ledger_entries_can_be_downloaded_as_a_filtered_pdf(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $workOrder->ledgers()->create(['type' => 'credit', 'amount' => 5000, 'entry_date' => '2026-08-05', 'balance' => 0, 'created_by' => $admin->id, 'category' => 'Cement']);
        $workOrder->ledgers()->create(['type' => 'debit', 'amount' => 1500, 'entry_date' => '2026-08-10', 'balance' => 0, 'created_by' => $admin->id, 'category' => 'Transport']);
        // Outside the filter - must not appear or count toward totals.
        $workOrder->ledgers()->create(['type' => 'debit', 'amount' => 9000, 'entry_date' => '2026-08-25', 'balance' => 0, 'created_by' => $admin->id]);

        // The filter form shows a Download PDF link alongside the CSV one.
        $page = $this->actingAs($admin)->get(route('work-orders.show', $workOrder).'?tab=ledger&ledger_from=2026-08-01&ledger_to=2026-08-15');
        $page->assertOk()->assertSee(route('work-orders.ledger.pdf', ['workOrder' => $workOrder, 'from' => '2026-08-01', 'to' => '2026-08-15']));

        $pdfResponse = $this->actingAs($admin)->get(route('work-orders.ledger.pdf', [
            'workOrder' => $workOrder, 'from' => '2026-08-01', 'to' => '2026-08-15',
        ]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));

        $entries = $workOrder->ledgers()->whereDate('entry_date', '>=', '2026-08-01')->whereDate('entry_date', '<=', '2026-08-15')->orderBy('entry_date')->get();
        $html = view('work-orders.ledger-pdf', ['workOrder' => $workOrder, 'entries' => $entries, 'from' => '2026-08-01', 'to' => '2026-08-15', 'title' => 'Site Ledger'])->render();

        $this->assertStringContainsString('Cement', $html);
        $this->assertStringContainsString('Transport', $html);
        $this->assertStringContainsString('5,000.00', $html);
        $this->assertStringNotContainsString('9,000.00', $html);
    }

    public function test_company_ledger_entries_can_be_downloaded_as_a_filtered_pdf_for_finance_and_admin_only(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $workOrder->companyLedgers()->create(['type' => 'credit', 'amount' => 8000, 'entry_date' => '2026-08-05', 'balance' => 0, 'created_by' => $admin->id, 'category' => 'Fuel']);
        $workOrder->companyLedgers()->create(['type' => 'debit', 'amount' => 2000, 'entry_date' => '2026-08-25', 'balance' => 0, 'created_by' => $admin->id, 'category' => 'Tools']);

        $pdfResponse = $this->actingAs($admin)->get(route('work-orders.company-ledger.pdf', [
            'workOrder' => $workOrder, 'from' => '2026-08-01', 'to' => '2026-08-15',
        ]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));

        $entries = $workOrder->companyLedgers()->whereDate('entry_date', '>=', '2026-08-01')->whereDate('entry_date', '<=', '2026-08-15')->orderBy('entry_date')->get();
        $html = view('work-orders.ledger-pdf', ['workOrder' => $workOrder, 'entries' => $entries, 'from' => '2026-08-01', 'to' => '2026-08-15', 'title' => 'Company Ledger'])->render();
        $this->assertStringContainsString('Fuel', $html);
        $this->assertStringNotContainsString('Tools', $html);

        // Restricted to Finance/Admin, same as the CSV export.
        $email = 'sales+'.uniqid().'@example.com';
        $this->actingAs($admin)->post('/admin/users', ['name' => 'Sales Person', 'email' => $email, 'role' => 'Sales'])->assertRedirect();
        $sales = User::where('email', $email)->firstOrFail();

        $this->actingAs($sales)->get(route('work-orders.company-ledger.pdf', $workOrder))->assertForbidden();
    }

    public function test_finance_invoices_can_be_downloaded_as_pdf_and_csv(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $client = $workOrder->client;

        Invoice::create([
            'invoice_no' => 'INV-1', 'work_order_id' => $workOrder->id, 'client_id' => $client->id,
            'amount' => 1000, 'tax_amount' => 0, 'total_amount' => 1000, 'status' => 'sent', 'issued_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('finance.invoices.pdf'))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $csv = $this->actingAs($admin)->get(route('finance.invoices.csv'));
        $csv->assertOk();
        $this->assertStringContainsString('INV-1', $csv->streamedContent());
        $this->assertStringContainsString($workOrder->work_order_no, $csv->streamedContent());
    }

    public function test_finance_client_payments_pdf_and_csv_respect_the_client_filter(): void
    {
        $admin = $this->admin();
        $workOrderA = $this->workOrder($admin, 'WO A');
        $workOrderB = $this->workOrder($admin, 'WO B');

        Payment::create([
            'client_id' => $workOrderA->client_id, 'work_order_id' => $workOrderA->id,
            'amount' => 500, 'payment_date' => now(), 'mode' => 'cash', 'received_by' => $admin->id,
        ]);
        Payment::create([
            'client_id' => $workOrderB->client_id, 'work_order_id' => $workOrderB->id,
            'amount' => 700, 'payment_date' => now(), 'mode' => 'upi', 'received_by' => $admin->id,
        ]);

        $csv = $this->actingAs($admin)->get(route('finance.payments.csv', ['payment_client_id' => $workOrderA->client_id]));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString($workOrderA->work_order_no, $content);
        $this->assertStringNotContainsString($workOrderB->work_order_no, $content);

        $this->actingAs($admin)->get(route('finance.payments.pdf', ['payment_client_id' => $workOrderA->client_id]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sub_contractor_payments_can_be_filtered_by_work_order(): void
    {
        $admin = $this->admin();
        $workOrderA = $this->workOrder($admin, 'WO A');
        $workOrderB = $this->workOrder($admin, 'WO B');
        $sc = User::create([
            'name' => 'Sub Co', 'email' => 'sc+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $sc->syncRoles(['Sub Contractor']);

        VendorPayment::create([
            'vendor_name' => $sc->name, 'user_id' => $sc->id, 'work_order_id' => $workOrderA->id,
            'amount' => 1200, 'payment_date' => now(), 'mode' => 'cash', 'paid_by' => $admin->id,
        ]);
        VendorPayment::create([
            'vendor_name' => $sc->name, 'user_id' => $sc->id, 'work_order_id' => $workOrderB->id,
            'amount' => 900, 'payment_date' => now(), 'mode' => 'cash', 'paid_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('finance.index', ['tab' => 'vendor', 'vendor_work_order_id' => $workOrderA->id]));
        $response->assertOk();
        $entries = $response->viewData('vendorPaymentEntries');
        $this->assertCount(1, $entries);
        $this->assertEquals($workOrderA->id, $entries->first()->work_order_id);

        $csv = $this->actingAs($admin)->get(route('finance.vendor-payments.csv', ['vendor_work_order_id' => $workOrderA->id]));
        $content = $csv->streamedContent();
        $this->assertStringContainsString($workOrderA->work_order_no, $content);
        $this->assertStringNotContainsString($workOrderB->work_order_no, $content);

        $this->actingAs($admin)->get(route('finance.vendor-payments.pdf', ['vendor_work_order_id' => $workOrderA->id]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_expenses_can_be_assigned_a_work_order_and_filtered_by_it(): void
    {
        $admin = $this->admin();
        $workOrderA = $this->workOrder($admin, 'WO A');
        $workOrderB = $this->workOrder($admin, 'WO B');
        LedgerCategory::create(['name' => 'Fuel', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('finance.expenses.store'), [
            'type' => 'debit', 'category' => 'Fuel', 'amount' => 500,
            'expense_date' => now()->format('Y-m-d'), 'work_order_id' => $workOrderA->id,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('finance.expenses.store'), [
            'type' => 'debit', 'category' => 'Fuel', 'amount' => 300,
            'expense_date' => now()->format('Y-m-d'), 'work_order_id' => $workOrderB->id,
        ])->assertRedirect();

        $this->assertSame($workOrderA->id, Expense::where('amount', 500)->firstOrFail()->work_order_id);

        $response = $this->actingAs($admin)->get(route('finance.index', ['tab' => 'expenses', 'expense_work_order_id' => $workOrderA->id]));
        $response->assertOk();
        $entries = $response->viewData('recentExpenses');
        $this->assertCount(1, $entries);
        $this->assertEquals($workOrderA->id, $entries->first()->work_order_id);

        $csv = $this->actingAs($admin)->get(route('finance.expenses.csv', ['expense_work_order_id' => $workOrderA->id]));
        $content = $csv->streamedContent();
        $this->assertStringContainsString($workOrderA->work_order_no, $content);
        $this->assertStringNotContainsString($workOrderB->work_order_no, $content);

        $this->actingAs($admin)->get(route('finance.expenses.pdf', ['expense_work_order_id' => $workOrderA->id]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
