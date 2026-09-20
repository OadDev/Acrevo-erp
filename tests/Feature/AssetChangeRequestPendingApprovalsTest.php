<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetChangeRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: the "Pending Approvals" page (asset-change-requests.index)
 * 500'd once a change request's underlying Asset was removed - the belongsTo
 * relation silently resolves to null under Asset's SoftDeletes scope, and
 * the view/controller both read $changeRequest->asset->... unguarded.
 */
class AssetChangeRequestPendingApprovalsTest extends TestCase
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

    private function management(User $admin): User
    {
        $email = 'management+'.uniqid().'@example.com';
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Management Person', 'email' => $email, 'role' => 'Management',
        ])->assertRedirect();

        return User::where('email', $email)->firstOrFail();
    }

    public function test_pending_approvals_page_survives_a_removed_asset(): void
    {
        $admin = $this->admin();
        $management = $this->management($admin);

        $this->actingAs($admin)->post('/assets', ['name' => 'Welding Machine', 'brand' => 'OldBrand'])->assertRedirect();
        $asset = Asset::where('name', 'Welding Machine')->firstOrFail();

        $this->actingAs($management)->put("/assets/{$asset->id}/request-update", ['name' => 'Welding Machine', 'brand' => 'NewBrand'])->assertRedirect();
        $changeRequest = AssetChangeRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($admin)->delete("/assets/{$asset->id}")->assertRedirect();

        $this->actingAs($admin)->get('/asset-change-requests')
            ->assertOk()
            ->assertSee('Welding Machine')
            ->assertSee('Approve unavailable');
    }

    public function test_approving_a_change_request_for_a_removed_asset_is_blocked_not_a_500(): void
    {
        $admin = $this->admin();
        $management = $this->management($admin);

        $this->actingAs($admin)->post('/assets', ['name' => 'Grinder', 'brand' => 'Original'])->assertRedirect();
        $asset = Asset::where('name', 'Grinder')->firstOrFail();

        $this->actingAs($management)->put("/assets/{$asset->id}/request-update", ['name' => 'Grinder', 'brand' => 'Requested'])->assertRedirect();
        $changeRequest = AssetChangeRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($admin)->delete("/assets/{$asset->id}")->assertRedirect();

        $this->actingAs($admin)->post("/asset-change-requests/{$changeRequest->id}/approve", [])->assertStatus(422);
        $this->assertSame('pending', $changeRequest->fresh()->status);
    }

    public function test_rejecting_a_change_request_for_a_removed_asset_still_works(): void
    {
        $admin = $this->admin();
        $management = $this->management($admin);

        $this->actingAs($admin)->post('/assets', ['name' => 'Compressor', 'brand' => 'Original'])->assertRedirect();
        $asset = Asset::where('name', 'Compressor')->firstOrFail();

        $this->actingAs($management)->put("/assets/{$asset->id}/request-update", ['name' => 'Compressor', 'brand' => 'Requested'])->assertRedirect();
        $changeRequest = AssetChangeRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($admin)->delete("/assets/{$asset->id}")->assertRedirect();

        $this->actingAs($admin)->post("/asset-change-requests/{$changeRequest->id}/reject", ['remarks' => 'Asset removed.'])->assertRedirect();
        $this->assertSame('rejected', $changeRequest->fresh()->status);
    }
}
