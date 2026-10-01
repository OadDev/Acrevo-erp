<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetRepair;
use App\Models\AssetVerification;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir asked for a "Download PDF" option on the Movement/Repair/Verification
 * History list pages - previously only a per-asset PDF existed (on the
 * Asset Details page); these global pages had no export at all, unlike
 * Missing Equipment and Equipment Requests, which already had one. The PDF
 * must reflect whatever filters are currently applied on the page.
 */
class AssetHistoryPdfExportsTest extends TestCase
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

    private function asset(User $admin, string $name = 'Ladder'): Asset
    {
        return Asset::create(['name' => $name, 'status' => 'available', 'current_location' => 'company_store', 'quantity' => 1, 'created_by' => $admin->id]);
    }

    public function test_movement_history_page_shows_a_download_pdf_button_that_respects_filters(): void
    {
        $admin = $this->admin();
        $ladder = $this->asset($admin, 'Ladder');
        $drill = $this->asset($admin, 'Drill Machine');

        AssetMovement::create([
            'asset_id' => $ladder->id, 'type' => 'other', 'quantity' => 1,
            'from_location' => 'company_store', 'to_location' => 'in_transit',
            'status' => 'pending', 'moved_at' => now(), 'created_by' => $admin->id,
        ]);
        AssetMovement::create([
            'asset_id' => $drill->id, 'type' => 'other', 'quantity' => 1,
            'from_location' => 'company_store', 'to_location' => 'in_transit',
            'status' => 'confirmed', 'moved_at' => now(), 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/asset-movements')
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee('Ladder')
            ->assertSee('Drill Machine');

        $this->actingAs($admin)->get('/asset-movements?status=pending')
            ->assertOk()
            ->assertSee('Ladder')
            ->assertDontSee('Drill Machine');

        $response = $this->actingAs($admin)->get('/asset-movements/pdf?status=pending');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_repair_history_page_shows_a_download_pdf_button_that_respects_filters(): void
    {
        $admin = $this->admin();
        $ladder = $this->asset($admin, 'Ladder');
        $drill = $this->asset($admin, 'Drill Machine');

        AssetRepair::create([
            'asset_id' => $ladder->id, 'repair_type' => 'mechanical', 'quantity' => 1, 'location' => 'company_store',
            'issue_description' => 'Bent rung', 'status' => 'reported', 'asset_status_before' => 'available',
            'reported_date' => now(), 'created_by' => $admin->id,
        ]);
        AssetRepair::create([
            'asset_id' => $drill->id, 'repair_type' => 'electrical', 'quantity' => 1, 'location' => 'company_store',
            'issue_description' => 'Short circuit', 'status' => 'completed', 'asset_status_before' => 'available',
            'reported_date' => now(), 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/asset-repairs')
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee('Ladder')
            ->assertSee('Drill Machine');

        $this->actingAs($admin)->get('/asset-repairs?repair_type=mechanical')
            ->assertOk()
            ->assertSee('Ladder')
            ->assertDontSee('Drill Machine');

        $response = $this->actingAs($admin)->get('/asset-repairs/pdf?repair_type=mechanical');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_verification_history_page_shows_a_download_pdf_button_that_respects_filters(): void
    {
        $admin = $this->admin();
        $ladder = $this->asset($admin, 'Ladder');
        $drill = $this->asset($admin, 'Drill Machine');

        AssetVerification::create([
            'asset_id' => $ladder->id, 'result' => 'found', 'quantity' => 1, 'location' => 'company_store',
            'verified_at' => now(), 'verified_by' => $admin->id,
        ]);
        AssetVerification::create([
            'asset_id' => $drill->id, 'result' => 'not_found', 'quantity' => 1, 'location' => 'company_store',
            'verified_at' => now(), 'verified_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/asset-verifications')
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee('Ladder')
            ->assertSee('Drill Machine');

        $this->actingAs($admin)->get('/asset-verifications?result=found')
            ->assertOk()
            ->assertSee('Ladder')
            ->assertDontSee('Drill Machine');

        $response = $this->actingAs($admin)->get('/asset-verifications/pdf?result=found');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_role_without_download_pdf_permission_does_not_see_the_button_and_is_forbidden(): void
    {
        $admin = $this->admin();
        $email = 'auditor+'.uniqid().'@example.com';
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Auditor Person', 'email' => $email, 'role' => 'Auditor',
        ])->assertRedirect();
        $auditor = User::where('email', $email)->firstOrFail();

        $this->actingAs($auditor)->get('/asset-movements/pdf')->assertForbidden();
        $this->actingAs($auditor)->get('/asset-repairs/pdf')->assertForbidden();
        $this->actingAs($auditor)->get('/asset-verifications/pdf')->assertForbidden();
    }
}
