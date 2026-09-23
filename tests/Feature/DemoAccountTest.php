<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir asked for a login clients can be given to explore the ERP without
 * being able to touch this live production data. The Demo role's view-only
 * permission set is one layer; BlockDemoWrites is the layer that actually
 * guarantees it - a resource route like enquiries.store is only gated by
 * enquiries.view at the route level, so permissions alone would not have
 * stopped a demo account from creating real records.
 */
class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): User
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $demo = User::create([
            'name' => 'Demo Account', 'email' => 'demo+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $demo->syncRoles(['Demo']);

        return $demo;
    }

    public function test_the_demo_account_can_browse_the_pages_it_has_view_access_to(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->get('/dashboard')->assertOk();
        $this->actingAs($demo)->get('/enquiries')->assertOk();
        $this->actingAs($demo)->get('/work-orders')->assertOk();
    }

    public function test_the_demo_account_cannot_create_a_real_enquiry(): void
    {
        $demo = $this->demo();

        $countBefore = Enquiry::count();

        $this->actingAs($demo)->post('/enquiries', [
            'contact_name' => 'Should Not Save', 'contact_phone' => '9999999999',
            'service_type' => 'Test', 'source' => 'website',
        ])->assertRedirect();

        $this->assertSame($countBefore, Enquiry::count());
    }

    public function test_the_demo_account_cannot_delete_a_client(): void
    {
        $demo = $this->demo();
        $client = Client::create(['name' => 'Real Client', 'email' => 'real@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $demo->id]);

        $this->actingAs($demo)->delete("/clients/{$client->id}");

        $this->assertNotNull($client->fresh());
    }

    public function test_the_demo_account_cannot_reach_modules_it_has_no_permission_for(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->get('/employees')->assertForbidden();
        $this->actingAs($demo)->get('/finance')->assertForbidden();
        $this->actingAs($demo)->get('/admin/users')->assertForbidden();
    }

    public function test_the_demo_account_can_still_log_out(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_the_demo_account_cannot_edit_its_own_profile(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->patch('/profile', ['name' => 'Changed Name', 'email' => $demo->email]);

        $this->assertSame('Demo Account', $demo->fresh()->name);
    }
}
