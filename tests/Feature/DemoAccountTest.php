<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\DemoDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sir asked for a login clients can be given to freely explore the ERP -
 * including adding/editing/deleting - with zero chance of touching this
 * live production data. SwitchDemoDatabaseConnection is what actually
 * guarantees that: every business-model query for the Demo role is moved
 * onto a wholly separate "demo" database for the duration of the request.
 * User/Role/Permission stay pinned to the main connection (see their
 * getConnectionName() overrides) so auth keeps working through the switch.
 */
class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): User
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Each test gets a fresh application (and so a fresh, unmigrated
        // in-memory "demo" connection) - unlike the main sqlite connection,
        // Laravel's RefreshDatabase doesn't persist it across tests, so
        // this can't be memoized with a static flag.
        if (! Schema::connection('demo')->hasTable('clients')) {
            Artisan::call('migrate', ['--database' => 'demo', '--force' => true]);
        }

        $demo = User::create([
            'name' => 'Demo Account', 'email' => 'demo+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $demo->syncRoles(['Demo']);
        DemoDatabase::mirrorDemoUser($demo);

        return $demo;
    }

    protected function tearDown(): void
    {
        if (Schema::connection('demo')->hasTable('clients')) {
            foreach (['tickets', 'work_orders', 'quotation_items', 'quotations', 'sites', 'enquiries', 'clients'] as $table) {
                if (Schema::connection('demo')->hasTable($table)) {
                    \DB::connection('demo')->table($table)->delete();
                }
            }
        }

        parent::tearDown();
    }

    public function test_the_demo_account_can_browse_pages_it_has_access_to(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->get('/dashboard')->assertOk()->assertSee('Demo Mode');
        $this->actingAs($demo)->get('/enquiries')->assertOk();
        $this->actingAs($demo)->get('/work-orders')->assertOk();
    }

    public function test_creating_an_enquiry_as_demo_lands_in_the_demo_database_not_production(): void
    {
        $demo = $this->demo();

        $mainCountBefore = Enquiry::count();

        $this->actingAs($demo)->post('/enquiries', [
            'contact_name' => 'Demo Test Enquiry', 'contact_phone' => '9999999999',
            'service_type' => 'Test', 'source' => 'website',
        ])->assertRedirect();

        // Never touched the real (main-connection) enquiries table.
        $this->assertSame($mainCountBefore, Enquiry::count());

        // But it did land somewhere - the isolated demo database.
        $this->assertSame(
            1,
            \DB::connection('demo')->table('enquiries')->where('contact_name', 'Demo Test Enquiry')->count()
        );
    }

    public function test_the_demo_account_can_delete_a_client_it_created_without_touching_production(): void
    {
        $demo = $this->demo();

        $demoClientId = (string) Str::uuid();
        DB::connection('demo')->table('clients')->insert([
            'id' => $demoClientId, 'client_code' => 'CL-DEMO-1',
            'name' => 'Demo Client', 'email' => 'democlient@example.com', 'phone' => '1',
            'is_active' => true, 'created_by' => $demo->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $realClient = Client::create(['name' => 'Real Client', 'email' => 'real@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $demo->id]);

        $this->actingAs($demo)->delete("/clients/{$demoClientId}")->assertRedirect();

        $this->assertNotNull(DB::connection('demo')->table('clients')->where('id', $demoClientId)->first()->deleted_at);
        // The real production client is completely untouched.
        $this->assertNull($realClient->fresh()->deleted_at);
    }

    public function test_the_demo_account_cannot_reach_user_and_role_administration(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->get('/admin/users')->assertForbidden();
        $this->actingAs($demo)->get('/admin/roles')->assertForbidden();
    }

    public function test_the_demo_account_can_still_log_out(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_the_demo_account_cannot_edit_or_delete_its_own_shared_login(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->patch('/profile', ['name' => 'Changed Name', 'email' => $demo->email]);
        $this->assertSame('Demo Account', $demo->fresh()->name);

        $this->actingAs($demo)->delete('/profile', ['password' => 'password']);
        $this->assertNotNull($demo->fresh());
    }

    public function test_a_regular_admin_request_is_unaffected_by_the_demo_switch(): void
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $admin->syncRoles(['Admin']);

        $countBefore = Enquiry::count();

        $this->actingAs($admin)->post('/enquiries', [
            'contact_name' => 'Real Admin Enquiry', 'contact_phone' => '8888888888',
            'service_type' => 'Test', 'source' => 'website',
        ])->assertRedirect();

        $this->assertSame($countBefore + 1, Enquiry::count());
    }
}
