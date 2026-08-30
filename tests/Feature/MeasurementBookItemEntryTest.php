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
 * MeasurementBookItemController::store()/update() built the insert/update
 * payload as `$data + ['rate' => $rate, ...]`. Since 'rate' is a validated
 * field, it was already a key in $data - and PHP's array union operator
 * keeps the LEFT side's value on a key collision, so the computed
 * fallback ($rate = $data['rate'] ?? 0) was silently discarded whenever the
 * Rate field was left blank. That put a literal NULL into the NOT NULL
 * `rate` column and crashed the "Add Entry" submission with a 500.
 */
class MeasurementBookItemEntryTest extends TestCase
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

    public function test_adding_a_work_done_entry_with_a_blank_rate_does_not_500(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books", [
            'description' => 'Ground floor slab', 'date' => now()->format('Y-m-d'),
        ])->assertRedirect();
        $mb = $workOrder->measurementBooks()->firstOrFail();

        $response = $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books/{$mb->id}/items", [
            'item_description' => 'Plastering', 'length' => '', 'breadth' => '', 'height' => '',
            'quantity' => '100', 'unit' => 'Sqft', 'rate' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $item = $mb->items()->firstOrFail();
        $this->assertSame(0.0, (float) $item->rate);
        $this->assertSame(0.0, (float) $item->amount);
    }

    public function test_adding_a_work_done_entry_with_a_rate_computes_the_amount(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books", [
            'description' => 'Ground floor slab', 'date' => now()->format('Y-m-d'),
        ])->assertRedirect();
        $mb = $workOrder->measurementBooks()->firstOrFail();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books/{$mb->id}/items", [
            'item_description' => 'Plastering', 'quantity' => '100', 'unit' => 'Sqft', 'rate' => '25',
        ])->assertRedirect();

        $item = $mb->items()->firstOrFail();
        $this->assertSame(25.0, (float) $item->rate);
        $this->assertSame(2500.0, (float) $item->amount);
    }

    public function test_editing_a_work_done_entry_to_clear_the_rate_does_not_500(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books", [
            'description' => 'Ground floor slab', 'date' => now()->format('Y-m-d'),
        ])->assertRedirect();
        $mb = $workOrder->measurementBooks()->firstOrFail();

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/measurement-books/{$mb->id}/items", [
            'item_description' => 'Plastering', 'quantity' => '100', 'unit' => 'Sqft', 'rate' => '25',
        ])->assertRedirect();
        $item = $mb->items()->firstOrFail();

        $response = $this->actingAs($admin)->put("/work-orders/{$workOrder->id}/measurement-books/{$mb->id}/items/{$item->id}", [
            'item_description' => 'Plastering', 'quantity' => '100', 'unit' => 'Sqft', 'rate' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertSame(0.0, (float) $item->fresh()->rate);
        $this->assertSame(0.0, (float) $item->fresh()->amount);
    }
}
