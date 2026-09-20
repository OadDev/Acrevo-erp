<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client edit/remove/portal-access/portal-permissions used to share the
 * plain enquiries.view gate with the rest of the Client and Enquiry pages,
 * so any role with enquiries.view (Sales, Marketing) got full Client
 * management for free. These tests cover the fix: that set of actions now
 * needs the new clients.manage permission, which only Admin holds - Sales
 * and Marketing keep their existing view access to Client details, and
 * Marketing is further scoped down to Enquiry Add/Edit and Site Visit Edit
 * only.
 */
class ClientAccessRestrictionTest extends TestCase
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

    private function staffUser(User $admin, string $role): User
    {
        $this->actingAs($admin)->post('/admin/users', [
            'name' => "{$role} Person", 'email' => strtolower(str_replace(' ', '', $role)).'+'.uniqid().'@example.com', 'role' => $role,
        ])->assertRedirect();

        return User::where('name', "{$role} Person")->firstOrFail();
    }

    private function client(User $admin): Client
    {
        return Client::create([
            'name' => 'Test Client', 'email' => 'client+'.uniqid().'@example.com', 'phone' => '9999999999',
            'type' => 'individual', 'is_active' => true, 'created_by' => $admin->id,
        ]);
    }

    public function test_marketing_and_sales_can_view_clients_but_not_edit_remove_or_manage_portal_access(): void
    {
        $admin = $this->admin();
        $client = $this->client($admin);

        foreach (['Marketing', 'Sales'] as $role) {
            $user = $this->staffUser($admin, $role);

            $this->actingAs($user)->get('/clients')->assertOk()->assertDontSee(route('clients.edit', $client));
            $this->actingAs($user)->get("/clients/{$client->id}")->assertOk()->assertDontSee('Generate Portal Access');

            $this->actingAs($user)->get("/clients/{$client->id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/clients/{$client->id}", ['name' => 'Hacked', 'type' => 'individual', 'phone' => '1'])->assertForbidden();
            $this->actingAs($user)->delete("/clients/{$client->id}")->assertForbidden();
            $this->actingAs($user)->post("/clients/{$client->id}/portal-access")->assertForbidden();
            $this->actingAs($user)->put("/clients/{$client->id}/portal-permissions", ['unrestricted' => '1'])->assertForbidden();
        }

        $this->assertSame('Test Client', $client->fresh()->name);
    }

    public function test_admin_can_still_edit_remove_and_manage_client_portal_access(): void
    {
        $admin = $this->admin();
        $client = $this->client($admin);

        $this->actingAs($admin)->get("/clients/{$client->id}")->assertOk()->assertSee('Generate Portal Access');

        $this->actingAs($admin)->put("/clients/{$client->id}", [
            'name' => 'Renamed Client', 'type' => 'individual', 'phone' => '9999999999',
        ])->assertRedirect();
        $this->assertSame('Renamed Client', $client->fresh()->name);

        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();
        $this->assertNotNull($client->fresh()->clientLogin);

        $this->actingAs($admin)->delete("/clients/{$client->id}")->assertRedirect();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_marketing_can_add_and_edit_enquiries_but_not_delete_them(): void
    {
        $admin = $this->admin();
        $marketing = $this->staffUser($admin, 'Marketing');
        $client = $this->client($admin);

        $this->actingAs($marketing)->post('/enquiries', [
            'client_id' => $client->id, 'service_type' => 'Renovation', 'contact_name' => 'C',
            'contact_phone' => '1', 'status' => 'new', 'source' => 'website',
        ])->assertRedirect();

        $enquiry = Enquiry::where('client_id', $client->id)->firstOrFail();

        $this->actingAs($marketing)->put("/enquiries/{$enquiry->id}", [
            'client_id' => $client->id, 'service_type' => 'Renovation Updated', 'contact_name' => 'C',
            'contact_phone' => '1', 'status' => 'contacted', 'source' => 'website',
        ])->assertRedirect();
        $this->assertSame('Renovation Updated', $enquiry->fresh()->service_type);

        $this->actingAs($marketing)->get('/enquiries')->assertOk()->assertDontSee('Remove');
        $this->actingAs($marketing)->delete("/enquiries/{$enquiry->id}")->assertForbidden();
        $this->assertNotNull($enquiry->fresh());
    }

    public function test_marketing_can_edit_a_site_visit_but_not_mark_it_complete(): void
    {
        $admin = $this->admin();
        $marketing = $this->staffUser($admin, 'Marketing');
        $client = $this->client($admin);
        $enquiry = Enquiry::create([
            'client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C',
            'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id,
        ]);
        $siteVisit = SiteVisit::create([
            'enquiry_id' => $enquiry->id, 'scheduled_at' => now()->addDay(), 'status' => 'scheduled',
            'assigned_to' => $marketing->id, 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($marketing)->get('/site-visits');
        $response->assertOk()->assertSee('Edit')->assertDontSee('Mark Complete')->assertDontSee('Schedule Site Visit');

        $this->actingAs($marketing)->put("/site-visits/{$siteVisit->id}", [
            'enquiry_id' => $enquiry->id, 'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'), 'assigned_to' => $marketing->id,
        ])->assertRedirect();
        $this->assertNotSame($siteVisit->scheduled_at, $siteVisit->fresh()->scheduled_at);
    }
}
