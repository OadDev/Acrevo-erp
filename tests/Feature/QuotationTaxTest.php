<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTaxTest extends TestCase
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

    private function enquiry(User $admin): Enquiry
    {
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        return Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
    }

    public function test_quotation_pages_survive_a_soft_deleted_client(): void
    {
        // Regression test: removing a client left any quotation still
        // pointing at it (client_id resolves to null via SoftDeletes'
        // global scope) crashing the Quotations list/show/PDF with
        // "Attempt to read property 'name' on null".
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);

        $this->actingAs($admin)->post('/quotations', [
            'enquiry_id' => $enquiry->id,
            'client_id' => $enquiry->client_id,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'items' => [
                ['item_type' => 'service', 'name' => 'Plastering', 'unit' => 'Sqft', 'quantity' => 100, 'unit_price' => 50, 'discount' => 0],
            ],
        ])->assertRedirect();
        $quotation = Quotation::firstOrFail();

        $enquiry->client->delete();

        $this->actingAs($admin)->get('/quotations')->assertOk()->assertSee('Unknown client');
        $this->actingAs($admin)->get("/quotations/{$quotation->id}")->assertOk()->assertSee('Unknown client');
        $this->actingAs($admin)->get("/quotations/{$quotation->id}/pdf")->assertOk();
    }

    public function test_a_client_with_a_quotation_or_enquiry_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);

        $this->actingAs($admin)->delete("/clients/{$enquiry->client_id}")->assertStatus(422);
        $this->assertNotNull($enquiry->client->fresh());

        Quotation::create([
            'enquiry_id' => $enquiry->id, 'client_id' => $enquiry->client_id,
            'discount_type' => 'flat', 'status' => 'draft', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete("/clients/{$enquiry->client_id}")->assertStatus(422);
        $this->assertNotNull($enquiry->client->fresh());
    }

    public function test_admin_can_remove_a_quotation_without_work_orders(): void
    {
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);
        $quotation = Quotation::create([
            'enquiry_id' => $enquiry->id, 'client_id' => $enquiry->client_id,
            'discount_type' => 'flat', 'status' => 'approved', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete("/quotations/{$quotation->id}")->assertRedirect(route('quotations.index'));
        $this->assertNull(Quotation::find($quotation->id));
    }

    public function test_a_quotation_with_work_orders_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);
        $quotation = Quotation::create([
            'enquiry_id' => $enquiry->id, 'client_id' => $enquiry->client_id,
            'discount_type' => 'flat', 'status' => 'approved', 'created_by' => $admin->id,
        ]);
        \App\Models\WorkOrder::create([
            'client_id' => $enquiry->client_id, 'quotation_id' => $quotation->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete("/quotations/{$quotation->id}")->assertStatus(422);
        $this->assertNotNull(Quotation::find($quotation->id));
    }

    public function test_non_admin_cannot_remove_a_quotation(): void
    {
        $admin = $this->admin();
        $sales = User::create([
            'name' => 'Sales', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);
        $enquiry = $this->enquiry($admin);
        $quotation = Quotation::create([
            'enquiry_id' => $enquiry->id, 'client_id' => $enquiry->client_id,
            'discount_type' => 'flat', 'status' => 'approved', 'created_by' => $admin->id,
        ]);

        $this->actingAs($sales)->delete("/quotations/{$quotation->id}")->assertForbidden();
        $this->assertNotNull(Quotation::find($quotation->id));
    }

    public function test_a_quotation_can_be_created_with_nil_tax(): void
    {
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);

        $response = $this->actingAs($admin)->post('/quotations', [
            'enquiry_id' => $enquiry->id,
            'client_id' => $enquiry->client_id,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'items' => [
                ['item_type' => 'service', 'name' => 'Plastering', 'unit' => 'Sqft', 'quantity' => 100, 'unit_price' => 50, 'discount' => 0],
            ],
        ]);
        $response->assertRedirect();

        $quotation = Quotation::firstOrFail();
        $this->assertSame(0.0, (float) $quotation->tax_percent);
        $this->assertSame(5000.0, (float) $quotation->subtotal);
        $this->assertSame(0.0, (float) $quotation->tax_amount);
        $this->assertSame(5000.0, (float) $quotation->total_amount);
    }

    public function test_a_quotation_can_be_created_with_explicit_zero_tax(): void
    {
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);

        $this->actingAs($admin)->post('/quotations', [
            'enquiry_id' => $enquiry->id,
            'client_id' => $enquiry->client_id,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'tax_percent' => 0,
            'items' => [
                ['item_type' => 'service', 'name' => 'Plastering', 'unit' => 'Sqft', 'quantity' => 100, 'unit_price' => 50, 'discount' => 0],
            ],
        ])->assertRedirect();

        $quotation = Quotation::firstOrFail();
        $this->assertSame(5000.0, (float) $quotation->total_amount);
    }

    public function test_tax_is_applied_exactly_once_not_twice(): void
    {
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);

        // 312 units at 638 = 199,056 pre-tax, matching the numbers from the
        // reported bug. At 18% tax that should be 234,886.08 total - not
        // 277,165.57 (which is what double-applying 18% tax produced).
        $this->actingAs($admin)->post('/quotations', [
            'enquiry_id' => $enquiry->id,
            'client_id' => $enquiry->client_id,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'tax_percent' => 18,
            'items' => [
                ['item_type' => 'service', 'name' => 'Tiling', 'unit' => 'Sqft', 'quantity' => 312, 'unit_price' => 638, 'discount' => 0],
            ],
        ])->assertRedirect();

        $quotation = Quotation::firstOrFail();
        $item = QuotationItem::firstOrFail();

        $this->assertEqualsWithDelta(199056.00, (float) $item->total, 0.01);
        $this->assertEqualsWithDelta(199056.00, (float) $quotation->subtotal, 0.01);
        $this->assertEqualsWithDelta(35830.08, (float) $quotation->tax_amount, 0.01);
        $this->assertEqualsWithDelta(234886.08, (float) $quotation->total_amount, 0.01);
    }

    public function test_updating_a_quotation_still_applies_tax_only_once(): void
    {
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);

        $this->actingAs($admin)->post('/quotations', [
            'enquiry_id' => $enquiry->id,
            'client_id' => $enquiry->client_id,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'tax_percent' => 18,
            'items' => [
                ['item_type' => 'service', 'name' => 'Tiling', 'unit' => 'Sqft', 'quantity' => 100, 'unit_price' => 100, 'discount' => 0],
            ],
        ])->assertRedirect();

        $quotation = Quotation::firstOrFail();

        $response = $this->actingAs($admin)->put("/quotations/{$quotation->id}", [
            'enquiry_id' => $enquiry->id,
            'client_id' => $enquiry->client_id,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'tax_percent' => 18,
            'items' => [
                ['item_type' => 'service', 'name' => 'Tiling', 'unit' => 'Sqft', 'quantity' => 200, 'unit_price' => 100, 'discount' => 0],
            ],
        ]);
        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();

        $quotation->refresh();
        $this->assertEqualsWithDelta(20000.00, (float) $quotation->subtotal, 0.01);
        $this->assertEqualsWithDelta(3600.00, (float) $quotation->tax_amount, 0.01);
        $this->assertEqualsWithDelta(23600.00, (float) $quotation->total_amount, 0.01);
    }
}
