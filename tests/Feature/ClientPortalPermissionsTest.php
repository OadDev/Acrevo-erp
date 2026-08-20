<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Ledger;
use App\Models\MeasurementBook;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalPermissionsTest extends TestCase
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

    private function workOrderWithLedgerAndMb(Client $client, User $admin): WorkOrder
    {
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        Ledger::create([
            'work_order_id' => $workOrder->id, 'entry_date' => now(), 'type' => 'credit',
            'category' => 'Advance', 'description' => 'Client advance payment', 'amount' => 5000, 'balance' => 5000, 'created_by' => $admin->id,
        ]);

        $mb = MeasurementBook::create([
            'work_order_id' => $workOrder->id, 'type' => 'actual', 'description' => 'Work done',
            'date' => now(), 'recorded_by' => $admin->id, 'status' => 'draft',
        ]);
        $mb->items()->create(['item_description' => 'Foundation excavation', 'unit' => 'Sqft', 'quantity' => 100, 'rate' => 0, 'amount' => 0]);

        WorkOrderSummary::create([
            'work_order_id' => $workOrder->id, 'entry_date' => now(), 'status' => 'done',
            'responsibility' => 'company', 'created_by' => $admin->id,
        ]);

        return $workOrder;
    }

    public function test_a_client_with_no_restriction_sees_every_portal_section(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);
        $workOrder = $this->workOrderWithLedgerAndMb($client, $admin);

        $this->assertNull($client->visible_sections);

        $response = $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Site Ledger')
            ->assertSee('Client advance payment')
            ->assertSee('Measurement Book')
            ->assertSee('Foundation excavation')
            ->assertSee('Monthly Summary')
            ->assertSee('Approval Requests')
            ->assertSee('Tickets');
    }

    public function test_admin_can_restrict_a_client_to_only_certain_sections(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);
        $workOrder = $this->workOrderWithLedgerAndMb($client, $admin);

        $this->actingAs($admin)->put("/clients/{$client->id}/portal-permissions", [
            'visible_sections' => ['ledger', 'tickets'],
        ])->assertRedirect();

        $this->assertSame(['ledger', 'tickets'], $client->fresh()->visible_sections);

        $response = $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Site Ledger')
            ->assertSee('Client advance payment')
            ->assertSee('Tickets')
            ->assertDontSee('Measurement Book')
            ->assertDontSee('Monthly Summary')
            ->assertDontSee('Approval Requests')
            ->assertDontSee('Progress Photos');
    }

    public function test_admin_can_reset_a_client_back_to_unrestricted(): void
    {
        $admin = $this->admin();
        [$client, $clientUser] = $this->clientWithLogin($admin);
        $client->update(['visible_sections' => ['tickets']]);

        $this->actingAs($admin)->put("/clients/{$client->id}/portal-permissions", [
            'unrestricted' => '1',
        ])->assertRedirect();

        $this->assertNull($client->fresh()->visible_sections);
    }
}
