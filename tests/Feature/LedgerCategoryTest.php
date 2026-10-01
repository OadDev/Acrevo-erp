<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\LedgerCategory;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerCategoryTest extends TestCase
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

    public function test_admin_can_add_and_remove_a_ledger_category(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/admin/ledger-categories', ['name' => 'Fuel'])->assertRedirect();
        $category = LedgerCategory::where('name', 'Fuel')->firstOrFail();

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=ledger")->assertOk()->assertSee('Fuel');

        $this->actingAs($admin)->delete("/admin/ledger-categories/{$category->id}")->assertRedirect();
        $this->assertNull(LedgerCategory::find($category->id));
    }

    public function test_removing_a_category_does_not_affect_ledger_entries_that_already_used_it(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $category = LedgerCategory::create(['name' => 'Materials', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'debit', 'category' => 'Materials', 'amount' => '500',
        ])->assertRedirect();

        $category->delete();

        $entry = $workOrder->fresh()->ledgers()->firstOrFail();
        $this->assertSame('Materials', $entry->category);

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=ledger")->assertOk()->assertSee('Materials');
    }

    public function test_a_duplicate_category_name_is_rejected(): void
    {
        $admin = $this->admin();
        LedgerCategory::create(['name' => 'Fuel', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/admin/ledger-categories', ['name' => 'Fuel'])->assertSessionHasErrors('name');
        $this->assertSame(1, LedgerCategory::where('name', 'Fuel')->count());
    }

    public function test_non_admin_cannot_manage_ledger_categories(): void
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $sales = User::create([
            'name' => 'Sales', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);

        $this->actingAs($sales)->post('/admin/ledger-categories', ['name' => 'Fuel'])->assertForbidden();
        $this->assertSame(0, LedgerCategory::count());
    }
}
