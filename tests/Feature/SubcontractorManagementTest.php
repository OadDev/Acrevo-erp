<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Site;
use App\Models\SiteSubContractor;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SubcontractorManagementTest extends TestCase
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

    private function subContractor(string $name = 'Sub Contractor User'): User
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Sub Contractor']);

        return $user;
    }

    private function sales(): User
    {
        $user = User::create([
            'name' => 'Sales User', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Sales']);

        return $user;
    }

    private function site(User $admin, ?Client $client = null): Site
    {
        $client ??= Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        return Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
    }

    private function workOrder(User $admin, ?Client $client = null): WorkOrder
    {
        $client ??= Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    public function test_the_subcontractor_index_lists_only_sub_contractor_role_users(): void
    {
        $admin = $this->admin();
        $subcontractor = $this->subContractor('Alpha Constructions');
        $this->sales();

        $this->actingAs($admin)->get('/subcontractors')
            ->assertOk()->assertSee('Alpha Constructions')->assertDontSee('Sales User');
    }

    public function test_a_non_admin_without_subcontractors_permission_is_forbidden(): void
    {
        $admin = $this->admin();
        $sales = $this->sales();

        $this->actingAs($sales)->get('/subcontractors')->assertForbidden();
    }

    public function test_admin_can_save_full_subcontractor_details(): void
    {
        $admin = $this->admin();
        $subcontractor = $this->subContractor();

        $this->actingAs($admin)->put("/subcontractors/{$subcontractor->id}", [
            'company_name' => 'Beta Builders', 'contact_person' => 'Ravi', 'phone' => '9998887777',
            'gst_number' => 'GST123', 'specialization' => 'Electrical',
        ])->assertRedirect();

        $profile = $subcontractor->fresh()->subcontractorProfile;
        $this->assertSame('Beta Builders', $profile->company_name);
        $this->assertSame('Electrical', $profile->specialization);
        $this->assertFalse($profile->is_verified);
    }

    public function test_admin_can_upload_view_and_remove_subcontractor_documents(): void
    {
        $admin = $this->admin();
        $subcontractor = $this->subContractor();

        $this->actingAs($admin)->put("/subcontractors/{$subcontractor->id}", [
            'company_name' => 'Gamma Contractors',
            'files' => [
                UploadedFile::fake()->create('kyc.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('id-proof.jpg'),
            ],
        ])->assertRedirect();

        $profile = $subcontractor->fresh()->subcontractorProfile;
        $this->assertSame(2, $profile->media()->count());

        $this->actingAs($admin)->get("/subcontractors/{$subcontractor->id}")
            ->assertOk()->assertSee('kyc.pdf')->assertSee('id-proof.jpg');

        $media = $profile->media()->where('file_name', 'like', 'kyc%')->firstOrFail();
        $this->actingAs($admin)->delete("/subcontractors/{$subcontractor->id}/media/{$media->id}")->assertRedirect();
        $this->assertSame(1, $profile->fresh()->media()->count());
    }

    public function test_admin_can_verify_and_unverify_a_subcontractor(): void
    {
        $admin = $this->admin();
        $subcontractor = $this->subContractor();

        $this->actingAs($admin)->post("/subcontractors/{$subcontractor->id}/verify")->assertRedirect();
        $profile = $subcontractor->fresh()->subcontractorProfile;
        $this->assertTrue($profile->is_verified);
        $this->assertNotNull($profile->verified_at);
        $this->assertSame($admin->id, $profile->verified_by);

        $this->actingAs($admin)->get("/subcontractors/{$subcontractor->id}")
            ->assertOk()->assertSee('Authorised Subcontractor');

        $this->actingAs($admin)->post("/subcontractors/{$subcontractor->id}/unverify")->assertRedirect();
        $this->assertFalse($profile->fresh()->is_verified);
    }

    public function test_admin_can_assign_and_unassign_a_site_and_duplicate_assignment_is_blocked(): void
    {
        $admin = $this->admin();
        $subcontractor = $this->subContractor();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/subcontractors/{$subcontractor->id}/assign-site", [
            'site_id' => $site->id,
        ])->assertRedirect();

        $assignment = SiteSubContractor::firstOrFail();
        $this->assertSame($subcontractor->id, $assignment->user_id);
        $this->assertSame($site->id, $assignment->site_id);

        $this->actingAs($admin)->post("/subcontractors/{$subcontractor->id}/assign-site", [
            'site_id' => $site->id,
        ])->assertStatus(422);

        $this->actingAs($admin)->delete("/subcontractors/{$subcontractor->id}/unassign-site/{$assignment->id}")->assertRedirect();
        $this->assertNotNull($assignment->fresh()->unassigned_at);
    }

    public function test_admin_can_assign_and_unassign_a_work_order(): void
    {
        $admin = $this->admin();
        $subcontractor = $this->subContractor();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post("/subcontractors/{$subcontractor->id}/assign-work-order", [
            'work_order_id' => $workOrder->id,
        ])->assertRedirect();

        $assignment = \App\Models\WorkOrderSubContractor::firstOrFail();
        $this->assertSame($subcontractor->id, $assignment->user_id);

        $this->actingAs($admin)->delete("/subcontractors/{$subcontractor->id}/unassign-work-order/{$assignment->id}")->assertRedirect();
        $this->assertNotNull($assignment->fresh()->unassigned_at);
    }

    public function test_a_subcontractor_sees_only_their_own_assigned_sites_in_my_work_orders(): void
    {
        $admin = $this->admin();
        $subcontractor = $this->subContractor('My Sites Sub');
        $otherSubcontractor = $this->subContractor('Other Sub');
        $mySite = $this->site($admin);
        $otherSite = $this->site($admin);

        SiteSubContractor::create(['site_id' => $mySite->id, 'user_id' => $subcontractor->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);
        SiteSubContractor::create(['site_id' => $otherSite->id, 'user_id' => $otherSubcontractor->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);

        $response = $this->actingAs($subcontractor)->get('/my-work-orders');
        $response->assertOk()->assertSee($mySite->site_no)->assertDontSee($otherSite->site_no);
    }

    public function test_a_subcontractor_can_view_only_their_own_finance_payments(): void
    {
        $admin = $this->admin();
        $subcontractor = $this->subContractor('Finance Sub');
        $otherSubcontractor = $this->subContractor('Other Finance Sub');

        VendorPayment::create([
            'vendor_name' => $subcontractor->name, 'user_id' => $subcontractor->id,
            'amount' => 5000, 'payment_date' => now()->toDateString(), 'mode' => 'bank_transfer', 'paid_by' => $admin->id,
        ]);
        VendorPayment::create([
            'vendor_name' => $otherSubcontractor->name, 'user_id' => $otherSubcontractor->id,
            'amount' => 9999, 'payment_date' => now()->toDateString(), 'mode' => 'bank_transfer', 'paid_by' => $admin->id,
        ]);

        $response = $this->actingAs($subcontractor)->get('/finance/my-payments');
        $response->assertOk()->assertSee('5,000.00')->assertDontSee('9,999.00');

        // A subcontractor cannot reach the full company-wide Finance page.
        $this->actingAs($subcontractor)->get('/finance')->assertForbidden();
    }
}
