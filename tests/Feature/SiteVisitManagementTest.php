<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVisitManagementTest extends TestCase
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

    private function siteVisit(User $admin): SiteVisit
    {
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return SiteVisit::create(['enquiry_id' => $enquiry->id, 'scheduled_at' => now()->addDay(), 'assigned_to' => $admin->id, 'status' => 'scheduled', 'created_by' => $admin->id]);
    }

    public function test_admin_can_remove_a_site_visit(): void
    {
        $admin = $this->admin();
        $siteVisit = $this->siteVisit($admin);

        $this->actingAs($admin)->get('/site-visits')->assertOk()->assertSee('Remove');

        $this->actingAs($admin)->delete("/site-visits/{$siteVisit->id}")->assertRedirect(route('site-visits.index'));
        $this->assertNull(SiteVisit::find($siteVisit->id));
    }

    public function test_non_admin_cannot_remove_a_site_visit(): void
    {
        $admin = $this->admin();
        $sales = User::create([
            'name' => 'Sales', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);
        $siteVisit = $this->siteVisit($admin);

        $this->actingAs($sales)->get('/site-visits')->assertOk()->assertDontSee('Remove');
        $this->actingAs($sales)->delete("/site-visits/{$siteVisit->id}")->assertForbidden();
        $this->assertNotNull(SiteVisit::find($siteVisit->id));
    }
}
