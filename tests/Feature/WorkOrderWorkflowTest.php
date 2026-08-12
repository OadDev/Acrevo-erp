<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderWorkflowTest extends TestCase
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

    public function test_work_orders_index_loads_with_and_without_a_site(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get('/work-orders');
        $response->assertOk();
    }

    public function test_approving_a_quotation_creates_a_site_and_work_order_requires_it(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'address' => 'Addr', 'city' => 'City', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'sent', 'total_amount' => 100, 'created_by' => $admin->id]);

        $this->assertNull($quotation->fresh()->site);

        $this->actingAs($admin)->post("/quotations/{$quotation->id}/approve")->assertRedirect();

        $site = $quotation->fresh()->site;
        $this->assertNotNull($site);
        $this->assertStringStartsWith('ST-', $site->site_no);

        $response = $this->actingAs($admin)->get("/work-orders/create?quotation_id={$quotation->id}");
        $response->assertOk();
        $response->assertSee($site->site_no);
    }

    public function test_work_order_show_renders_with_and_without_a_linked_site(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        // Legacy-style work order with no quotation/site (data created before the Site feature existed).
        $legacyWorkOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'Old WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'pending_hr_assignment', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/work-orders/{$legacyWorkOrder->id}")->assertOk();

        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'approved', 'total_amount' => 100, 'created_by' => $admin->id]);
        $site = Site::create(['quotation_id' => $quotation->id, 'client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'quotation_id' => $quotation->id, 'site_id' => $site->id, 'client_id' => $client->id, 'title' => 'WO',
            'execution_way' => 'way_1', 'priority' => 'medium', 'enquiry_id' => $enquiry->id, 'type' => 'new',
            'status' => 'pending_hr_assignment', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk();
        $response->assertSee($site->site_no);
    }

    public function test_generate_portal_access_creates_a_working_login(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'portal@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->post("/clients/{$client->id}/portal-access");
        $response->assertRedirect(route('clients.show', $client));

        $login = ClientLogin::where('client_id', $client->id)->first();
        $this->assertNotNull($login);
        $this->assertTrue($login->user->hasRole('Client'));

        // Repeat calls must not fail (e.g. re-clicking the button).
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertStatus(422);
    }

    public function test_admin_cannot_assign_the_client_role_from_the_generic_user_form(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => 'Client',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }
}
