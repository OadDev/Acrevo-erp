<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\LedgerCategory;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-maintained Category List, shared across WO Company Ledger and the
 * Finance menu (Expenses, Sub Contractor Payments), so the category field
 * on those entries is picked from one predefined list instead of free text.
 * Also covers the Finance Expense balance chronological-ordering fix:
 * storeExpense() used to compute the new row's balance from the
 * latest-inserted-id row instead of recalculating in date order, the same
 * bug Company Ledger had before it was fixed.
 */
class CategoryListTest extends TestCase
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

    private function finance(): User
    {
        $finance = User::create([
            'name' => 'Finance Guy', 'email' => 'finance+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $finance->syncRoles(['Finance']);

        return $finance;
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

    public function test_admin_can_add_and_remove_a_category(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.ledger-categories.store'), ['name' => 'Fuel'])->assertRedirect();
        $category = LedgerCategory::where('name', 'Fuel')->firstOrFail();

        $this->actingAs($admin)->get(route('finance.index', ['tab' => 'categories']))
            ->assertOk()
            ->assertSee('Fuel');

        $this->actingAs($admin)->delete(route('admin.ledger-categories.destroy', $category))->assertRedirect();

        $this->assertNull(LedgerCategory::find($category->id));
    }

    public function test_finance_role_cannot_manage_categories(): void
    {
        $admin = $this->admin();
        $finance = $this->finance();

        $this->actingAs($finance)->post(route('admin.ledger-categories.store'), ['name' => 'Fuel'])->assertForbidden();
    }

    public function test_company_ledger_tab_lists_categories_and_the_category_field_is_selectable(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        LedgerCategory::create(['name' => 'Fuel', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('work-orders.show', $workOrder).'?tab=company-ledger')
            ->assertOk()
            ->assertSee('Ledger Categories')
            ->assertSee('Fuel');

        $this->actingAs($admin)->post(route('work-orders.company-ledger.store', $workOrder), [
            'entry_date' => now()->format('Y-m-d'), 'type' => 'debit', 'amount' => 500, 'category' => 'Fuel',
        ])->assertRedirect();

        $this->assertSame('Fuel', $workOrder->companyLedgers()->firstOrFail()->category);
    }

    public function test_finance_expenses_tab_lists_categories_and_the_category_field_is_selectable(): void
    {
        $admin = $this->admin();
        LedgerCategory::create(['name' => 'Office Rent', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('finance.index', ['tab' => 'expenses']))
            ->assertOk()
            ->assertSee('Office Rent');

        $this->actingAs($admin)->post(route('finance.expenses.store'), [
            'type' => 'debit', 'category' => 'Office Rent', 'amount' => 15000, 'expense_date' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame('Office Rent', Expense::firstOrFail()->category);
    }

    public function test_expense_balances_recalculate_in_chronological_order_regardless_of_entry_order(): void
    {
        $admin = $this->admin();
        LedgerCategory::create(['name' => 'General', 'created_by' => $admin->id]);

        // Entered out of order: later date first, then a backdated entry.
        $this->actingAs($admin)->post(route('finance.expenses.store'), [
            'type' => 'credit', 'category' => 'General', 'amount' => 1000, 'expense_date' => '2026-08-20',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('finance.expenses.store'), [
            'type' => 'debit', 'category' => 'General', 'amount' => 300, 'expense_date' => '2026-08-10',
        ])->assertRedirect();

        $entries = Expense::orderBy('expense_date')->orderBy('id')->get();
        $this->assertSame(['2026-08-10', '2026-08-20'], $entries->map(fn ($e) => $e->expense_date->format('Y-m-d'))->all());

        // Balances recalculated in date order: the backdated debit applies
        // BEFORE the credit chronologically, not after it was inserted.
        $this->assertEquals(-300, (float) $entries->first()->balance);
        $this->assertEquals(700, (float) $entries->last()->balance);
    }
}
