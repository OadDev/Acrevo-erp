<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnquiryClientSyncTest extends TestCase
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

    public function test_editing_an_enquirys_contact_email_fills_in_a_blank_client_email(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'Yoyo1', 'phone' => '6764677876', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create([
            'client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'Yoyo1', 'contact_phone' => '6764677876',
            'status' => 'new', 'source' => 'website', 'created_by' => $admin->id,
        ]);
        $this->assertNull($client->email);

        $response = $this->actingAs($admin)->put("/enquiries/{$enquiry->id}", [
            'contact_name' => 'Yoyo1', 'contact_phone' => '6764677876', 'contact_email' => 'yoyo1@example.com',
            'source' => 'website',
        ]);
        $response->assertRedirect(route('enquiries.show', $enquiry));

        $this->assertSame('yoyo1@example.com', $client->fresh()->email);
    }

    public function test_editing_an_enquirys_contact_email_never_overwrites_an_existing_client_email(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'phone' => '1', 'email' => 'original@example.com', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create([
            'client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1',
            'status' => 'new', 'source' => 'website', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/enquiries/{$enquiry->id}", [
            'contact_name' => 'C', 'contact_phone' => '1', 'contact_email' => 'different@example.com',
            'source' => 'website',
        ])->assertRedirect();

        $this->assertSame('original@example.com', $client->fresh()->email);
    }

    public function test_only_admin_can_remove_an_enquiry(): void
    {
        $admin = $this->admin();
        $sales = User::create([
            'name' => 'Sales User', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);

        $client = Client::create(['name' => 'C', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create([
            'client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1',
            'status' => 'new', 'source' => 'website', 'created_by' => $admin->id,
        ]);

        $this->actingAs($sales)->get('/enquiries')->assertOk()->assertDontSee('Remove');
        $this->actingAs($sales)->delete("/enquiries/{$enquiry->id}")->assertForbidden();
        $this->assertNotSoftDeleted('enquiries', ['id' => $enquiry->id]);

        $this->actingAs($admin)->get('/enquiries')->assertOk()->assertSee('Remove');
        $this->actingAs($admin)->delete("/enquiries/{$enquiry->id}")->assertRedirect();
        $this->assertSoftDeleted('enquiries', ['id' => $enquiry->id]);
    }
}
