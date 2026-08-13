<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalWorkflowTest extends TestCase
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

    public function test_client_can_see_and_accept_a_sent_quotation(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'draft', 'total_amount' => 100, 'created_by' => $admin->id]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'item_type' => 'service', 'name' => 'Painting', 'unit' => 'sqft', 'quantity' => 10, 'unit_price' => 10, 'discount' => 0, 'tax_percent' => 0, 'total' => 100, 'sort_order' => 0]);

        // Draft quotations aren't visible to the client yet.
        $this->actingAs($clientUser)->get('/portal/quotations')->assertOk()->assertDontSee($quotation->quotation_no);

        $this->actingAs($admin)->post("/quotations/{$quotation->id}/send")->assertRedirect();

        $response = $this->actingAs($clientUser)->get('/portal/quotations');
        $response->assertOk()->assertSee($quotation->quotation_no);

        $this->actingAs($clientUser)->get("/portal/quotations/{$quotation->id}")->assertOk()->assertSee('Painting');

        $this->actingAs($clientUser)->post("/portal/quotations/{$quotation->id}/approve")->assertRedirect();

        $quotation->refresh();
        $this->assertSame('approved', $quotation->status);
        $this->assertSame($client->name, $quotation->approved_by);
        $this->assertNotNull($quotation->fresh()->site);
    }

    public function test_client_can_request_a_requote_with_a_reason(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => 'sent', 'total_amount' => 100, 'created_by' => $admin->id]);

        $this->actingAs($clientUser)->post("/portal/quotations/{$quotation->id}/reject", [
            'decision' => 'requote',
            'reason' => 'Please reduce the price',
        ])->assertRedirect();

        $quotation->refresh();
        $this->assertSame('rejected', $quotation->status);
        $this->assertStringContainsString('Re-quote requested', $quotation->rejected_reason);
        $this->assertStringContainsString('Please reduce the price', $quotation->rejected_reason);
    }

    public function test_client_cannot_act_on_another_clients_quotation(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);
        [$otherClient, $otherUser] = $this->clientWithLogin($admin);
        $enquiry = Enquiry::create(['client_id' => $otherClient->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $quotation = Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $otherClient->id, 'status' => 'sent', 'total_amount' => 100, 'created_by' => $admin->id]);

        $this->actingAs($clientUser)->get("/portal/quotations/{$quotation->id}")->assertForbidden();
        $this->actingAs($clientUser)->post("/portal/quotations/{$quotation->id}/approve")->assertForbidden();
    }

    public function test_client_can_accept_a_work_order_after_qc_passes(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        // A final QC pass must reach client review too, not skip straight to internal completion.
        $this->actingAs($admin)->post('/qc', [
            'work_order_id' => $workOrder->id,
            'inspection_type' => 'final',
            'status' => 'passed',
        ])->assertRedirect();

        $this->assertSame('client_review', $workOrder->fresh()->status);

        $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}")->assertOk()->assertSee('QC Passed');

        $this->actingAs($clientUser)->post("/portal/work-orders/{$workOrder->id}/accept")->assertRedirect();

        $workOrder->refresh();
        $this->assertSame('completed', $workOrder->status);
        $this->assertCount(1, $workOrder->completionCertificates);
    }
}
