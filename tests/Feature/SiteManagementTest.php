<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
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

    public function test_add_new_site_page_opens_without_a_500_from_a_clients_page(): void
    {
        // Regression test: @disabled($client) directly on the <x-select-input>
        // component tag failed to compile, 500ing this page every time.
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $this->actingAs($admin)->get("/sites/create?client_id={$client->id}")
            ->assertOk()->assertSee('Add Site');
    }

    public function test_a_site_with_work_orders_cannot_be_removed_but_an_empty_one_can(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $site = Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'site_id' => $site->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get("/sites/{$site->id}")->assertOk()->assertSee('Remove all work orders under this site first');
        $this->actingAs($admin)->delete("/sites/{$site->id}")->assertStatus(422);
        $this->assertNotNull($site->fresh());

        $workOrder->delete();

        $this->actingAs($admin)->get("/sites/{$site->id}")->assertOk()->assertSee('Remove Site');
        $this->actingAs($admin)->delete("/sites/{$site->id}")->assertRedirect('/sites');
        $this->assertNull(Site::find($site->id));
    }

    public function test_site_details_support_construction_site_and_client_living_location(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post('/sites', [
            'client_id' => $client->id,
            'address' => 'Addr',
            'construction_site_location' => 'https://maps.example/site',
            'client_living_location' => '12 Client Street, Chennai',
        ])->assertRedirect();

        $site = Site::where('client_id', $client->id)->firstOrFail();
        $this->assertSame('https://maps.example/site', $site->construction_site_location);
        $this->assertSame('12 Client Street, Chennai', $site->client_living_location);

        $this->actingAs($admin)->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('https://maps.example/site')
            ->assertSee('12 Client Street, Chennai');

        $this->actingAs($admin)->patch("/sites/{$site->id}", [
            'address' => 'Addr',
            'construction_site_location' => 'Updated site location',
            'client_living_location' => 'Updated client location',
        ])->assertRedirect();

        $fresh = $site->fresh();
        $this->assertSame('Updated site location', $fresh->construction_site_location);
        $this->assertSame('Updated client location', $fresh->client_living_location);
    }

    public function test_a_client_with_a_site_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($admin)->delete("/clients/{$client->id}")->assertStatus(422);
        $this->assertNotNull($client->fresh());
    }

    public function test_sites_index_does_not_500_when_its_client_was_removed(): void
    {
        // Regression test: a client soft-deleted while still owning a site
        // left the site's `client` relation resolving to null (SoftDeletes
        // excludes trashed rows), which crashed the Sites list with
        // "Attempt to read property 'name' on null".
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $site = Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $client->delete();

        $this->actingAs($admin)->get('/sites')->assertOk()->assertSee('Removed client');
        $this->actingAs($admin)->get("/sites/{$site->id}")->assertOk()->assertSee('Removed client');
    }

    public function test_only_admin_can_remove_a_site(): void
    {
        $admin = $this->admin();
        $sales = User::create([
            'name' => 'Sales', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);

        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $site = Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);

        $this->actingAs($sales)->delete("/sites/{$site->id}")->assertForbidden();
        $this->assertNotNull($site->fresh());
    }
}
