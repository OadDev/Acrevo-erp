<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Site;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceWorkflowTest extends TestCase
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

    // Call only after admin() has already seeded roles/permissions in the
    // same test - reseeding here would duplicate the Role rows.
    private function management(): User
    {
        $user = User::create([
            'name' => 'Management User', 'email' => 'mgmt+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Management']);

        return $user;
    }

    private function subContractor(): User
    {
        $user = User::create([
            'name' => 'Sub Contractor User', 'email' => 'sc+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Sub Contractor']);

        return $user;
    }

    private function clientWithLogin(User $admin): array
    {
        $client = Client::create(['name' => 'Pari', 'email' => 'pari+'.uniqid().'@example.com', 'phone' => '1', 'address' => 'Addr', 'is_active' => true, 'created_by' => $admin->id]);
        $user = User::create([
            'name' => $client->name, 'email' => $client->email,
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $user->syncRoles(['Client']);
        ClientLogin::create(['client_id' => $client->id, 'user_id' => $user->id]);

        return [$client, $user];
    }

    public function test_new_invoice_can_be_created_for_a_client_user_without_a_500(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);

        $response = $this->actingAs($admin)->get('/finance');
        $response->assertOk()->assertSee('New Invoice')->assertSee($clientUser->email);

        $store = $this->actingAs($admin)->post('/finance/invoices', [
            'client_id' => $client->id,
            'amount' => '10000',
            'tax_amount' => '1800',
            'due_date' => now()->addDays(7)->toDateString(),
        ]);
        $store->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertNull($invoice->work_order_id);
        $this->assertEquals(11800, $invoice->total_amount);
        $this->assertSame('sent', $invoice->status);

        // Visible in the client's portal, framed as a payment request.
        $portalIndex = $this->actingAs($clientUser)->get('/portal/invoices');
        $portalIndex->assertOk()->assertSee($invoice->invoice_no)->assertSee('Payment Requested');

        // And surfaced on their dashboard/projects landing page.
        $dashboard = $this->actingAs($clientUser)->get('/portal/work-orders');
        $dashboard->assertOk()->assertSee('Payment Requested');
    }

    public function test_admin_can_update_invoice_status_and_client_sees_it(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);

        $invoice = Invoice::create([
            'client_id' => $client->id, 'amount' => 5000, 'tax_amount' => 0, 'total_amount' => 5000,
            'status' => 'sent', 'issued_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/finance/invoices/{$invoice->id}/status", ['status' => 'paid'])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);

        $this->actingAs($clientUser)->get('/portal/invoices')->assertOk()->assertSee('Paid');
    }

    public function test_only_admin_can_delete_an_invoice(): void
    {
        $admin = $this->admin();
        [$client] = $this->clientWithLogin($admin);
        $mgmt = $this->management();

        $invoice = Invoice::create([
            'client_id' => $client->id, 'amount' => 5000, 'tax_amount' => 0, 'total_amount' => 5000,
            'status' => 'sent', 'issued_by' => $admin->id,
        ]);

        // Management can view Finance but cannot write to it at all.
        $this->actingAs($mgmt)->post('/finance/invoices', [
            'client_id' => $client->id, 'amount' => '100',
        ])->assertForbidden();

        $this->actingAs($admin)->delete("/finance/invoices/{$invoice->id}")->assertRedirect();
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_client_payment_supports_site_wo_filter_edit_and_delete(): void
    {
        $admin = $this->admin();
        [$client] = $this->clientWithLogin($admin);
        $site = Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'site_id' => $site->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post('/finance/payments', [
            'client_id' => $client->id, 'site_id' => $site->id, 'work_order_id' => $workOrder->id,
            'amount' => '2000', 'payment_date' => now()->toDateString(), 'mode' => 'upi',
        ])->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->assertSame($site->id, $payment->site_id);
        $this->assertSame($workOrder->id, $payment->work_order_id);

        $filtered = $this->actingAs($admin)->get('/finance?tab=payments&payment_client_id='.$client->id);
        $filtered->assertOk()->assertSee($workOrder->work_order_no)->assertSee($site->site_no);

        $this->actingAs($admin)->put("/finance/payments/{$payment->id}", [
            'client_id' => $client->id, 'site_id' => $site->id, 'work_order_id' => $workOrder->id,
            'amount' => '2500', 'payment_date' => now()->toDateString(), 'mode' => 'cash',
        ])->assertRedirect();
        $this->assertEquals(2500, $payment->fresh()->amount);

        $this->actingAs($admin)->delete("/finance/payments/{$payment->id}")->assertRedirect();
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_sub_contractor_payment_selects_from_sub_contractor_users_with_remark_edit_and_delete(): void
    {
        $admin = $this->admin();
        $subContractor = $this->subContractor();

        $response = $this->actingAs($admin)->get('/finance?tab=vendor');
        $response->assertOk()->assertSee('Sub Contractor Payments')->assertSee($subContractor->name);

        $this->actingAs($admin)->post('/finance/vendor-payments', [
            'user_id' => $subContractor->id, 'amount' => '15000', 'payment_date' => now()->toDateString(),
            'mode' => 'bank_transfer', 'category' => 'Labour', 'remark' => 'Advance for phase 1',
        ])->assertRedirect();

        $vp = VendorPayment::firstOrFail();
        $this->assertSame($subContractor->id, $vp->user_id);
        $this->assertSame($subContractor->name, $vp->vendor_name);
        $this->assertSame('Advance for phase 1', $vp->remark);

        $this->actingAs($admin)->put("/finance/vendor-payments/{$vp->id}", [
            'user_id' => $subContractor->id, 'amount' => '18000', 'payment_date' => now()->toDateString(),
            'mode' => 'upi', 'remark' => 'Final settlement',
        ])->assertRedirect();
        $this->assertSame('Final settlement', $vp->fresh()->remark);

        $this->actingAs($admin)->delete("/finance/vendor-payments/{$vp->id}")->assertRedirect();
        $this->assertDatabaseMissing('vendor_payments', ['id' => $vp->id]);
    }

    public function test_expense_mirrors_company_ledger_columns_with_running_balance_filter_edit_and_delete(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/finance/expenses', [
            'type' => 'debit', 'category' => 'Salary', 'description' => 'Sales staff salary',
            'amount' => '30000', 'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($admin)->post('/finance/expenses', [
            'type' => 'credit', 'category' => 'Refund', 'description' => 'Vendor refund',
            'amount' => '5000', 'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $expenses = Expense::orderBy('id')->get();
        $this->assertEquals(-30000, $expenses[0]->balance);
        $this->assertEquals(-25000, $expenses[1]->balance);

        $filtered = $this->actingAs($admin)->get('/finance?tab=expenses&expense_category=Salary');
        $filtered->assertOk()->assertSee('Sales staff salary')->assertDontSee('Vendor refund');

        $this->actingAs($admin)->put("/finance/expenses/{$expenses[0]->id}", [
            'type' => 'debit', 'category' => 'Salary', 'description' => 'Sales staff salary (corrected)',
            'amount' => '20000', 'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        // Balances recalculated after the edit: -20000 then -20000+5000=-15000.
        $this->assertEquals(-20000, $expenses[0]->fresh()->balance);
        $this->assertEquals(-15000, $expenses[1]->fresh()->balance);

        $this->actingAs($admin)->delete("/finance/expenses/{$expenses[1]->id}")->assertRedirect();
        $this->assertEquals(-20000, $expenses[0]->fresh()->balance);
    }

    public function test_new_invoice_can_be_created_with_no_tax_amount_entered_at_all(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);

        // Regression test: leaving Tax Amount blank sends an empty string,
        // which middleware converts to null - tax_amount is a NOT NULL
        // column, so this used to 500 instead of defaulting to 0.
        $response = $this->actingAs($admin)->post('/finance/invoices', [
            'client_id' => $client->id,
            'amount' => '10000',
        ]);
        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $invoice = Invoice::firstOrFail();
        $this->assertEquals(0, $invoice->tax_amount);
        $this->assertEquals(10000, $invoice->total_amount);

        // And it now shows up as a Payment Requested banner on the client's
        // dashboard, since creation actually succeeded.
        $this->actingAs($clientUser)->get('/portal/work-orders')->assertOk()->assertSee('Payment Requested');
    }

    public function test_an_invoice_can_be_marked_cancelled_without_a_db_error(): void
    {
        $admin = $this->admin();
        [$client] = $this->clientWithLogin($admin);

        $invoice = Invoice::create([
            'client_id' => $client->id, 'amount' => 5000, 'tax_amount' => 0, 'total_amount' => 5000,
            'status' => 'sent', 'issued_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/finance/invoices/{$invoice->id}/status", ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_sub_contractor_sees_their_own_payments_on_their_work_orders_dashboard(): void
    {
        $admin = $this->admin();
        $subContractor = $this->subContractor();

        $this->actingAs($admin)->post('/finance/vendor-payments', [
            'user_id' => $subContractor->id, 'amount' => '15000', 'payment_date' => now()->toDateString(),
            'mode' => 'bank_transfer', 'category' => 'Labour', 'remark' => 'Advance',
        ])->assertRedirect();

        $response = $this->actingAs($subContractor)->get('/my-work-orders');
        $response->assertOk()->assertSee('My Payments')->assertSee('15,000.00')->assertSee('Advance');
    }

    public function test_expenses_pdf_downloads_with_filters_and_embeds_the_bill_image(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/finance/expenses', [
            'type' => 'debit', 'category' => 'Salary', 'description' => 'Sales staff salary',
            'amount' => '30000', 'expense_date' => now()->toDateString(),
            'bill' => \Illuminate\Http\UploadedFile::fake()->image('bill.jpg', 200, 200),
        ])->assertRedirect();

        $this->actingAs($admin)->post('/finance/expenses', [
            'type' => 'debit', 'category' => 'Office', 'description' => 'Office rent',
            'amount' => '12000', 'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $pdf = $this->actingAs($admin)->get('/finance/expenses/pdf?expense_category=Salary');
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(1000, strlen($pdf->getContent()));
    }
}
