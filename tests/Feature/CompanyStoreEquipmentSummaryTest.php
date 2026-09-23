<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStock;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir asked for the Company Store to get the same "Waiting for
 * Confirmation" tab WO Equipment already has, plus a stat/asset-wise
 * summary that stays consistent with WO Equipment and Movement History -
 * assets that have been removed must not keep counting toward Damaged/
 * Missing there either.
 */
class CompanyStoreEquipmentSummaryTest extends TestCase
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
        $email = strtolower(str_replace(' ', '', $role)).'+'.uniqid().'@example.com';

        $this->actingAs($admin)->post('/admin/users', [
            'name' => "{$role} Person", 'email' => $email, 'role' => $role,
        ])->assertRedirect();

        return User::where('email', $email)->firstOrFail();
    }

    private function atCompanyStore(User $admin, string $name, int $available, int $damaged = 0, int $missing = 0): Asset
    {
        $asset = Asset::create([
            'name' => $name, 'status' => 'available', 'current_location' => 'company_store',
            'quantity' => $available + $damaged + $missing, 'created_by' => $admin->id,
        ]);

        if ($available > 0) {
            AssetStock::adjust($asset, 'company_store', null, 'available', $available);
        }
        if ($damaged > 0) {
            AssetStock::adjust($asset, 'company_store', null, 'damaged', $damaged);
        }
        if ($missing > 0) {
            AssetStock::adjust($asset, 'company_store', null, 'missing', $missing);
        }

        return $asset;
    }

    public function test_the_company_store_shows_a_stat_and_asset_wise_summary(): void
    {
        $admin = $this->admin();
        $this->atCompanyStore($admin, 'Steel Sheets', 10, 3, 2);

        $response = $this->actingAs($admin)->get('/assets');

        $response->assertOk()
            ->assertSee('Steel Sheets')
            ->assertSee('Company Store — Asset-wise Summary', false)
            ->assertViewHas('companyStoreSummary', fn ($summary) => $summary === [
                'total' => 15, 'available' => 10, 'damaged' => 3, 'missing' => 2,
            ]);

        $row = $response->viewData('companyStoreAssetSummary')->firstWhere('asset.name', 'Steel Sheets');
        $this->assertSame(15, $row->total);
        $this->assertSame(10, $row->in_use);
        $this->assertSame(3, $row->damaged);
        $this->assertSame(2, $row->missing);
    }

    public function test_removing_an_asset_drops_it_from_the_company_store_summary(): void
    {
        $admin = $this->admin();
        $asset = $this->atCompanyStore($admin, 'Old Ladder', 5, 1, 1);
        $asset->delete();

        $response = $this->actingAs($admin)->get('/assets');

        $response->assertOk()
            ->assertViewHas('companyStoreSummary', fn ($summary) => $summary === [
                'total' => 0, 'available' => 0, 'damaged' => 0, 'missing' => 0,
            ]);

        $this->assertNull($response->viewData('companyStoreAssetSummary')->firstWhere('asset.name', 'Old Ladder'));
    }

    public function test_admin_can_confirm_or_cancel_a_movement_waiting_at_the_company_store(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Returned Drill'])->assertRedirect();
        $asset = Asset::where('name', 'Returned Drill')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/movements", [
            'type' => 'site_return', 'from_location' => 'company_store', 'to_location' => 'company_store',
            'quantity' => 1, 'moved_at' => now()->toDateString(),
        ])->assertRedirect();
        // Excludes the auto-recorded "purchase" movement from asset
        // creation (already confirmed) - this is the pending one we just
        // created.
        $movement = AssetMovement::where('asset_id', $asset->id)->where('type', 'site_return')->firstOrFail();

        $response = $this->actingAs($admin)->get('/assets');
        $response->assertOk()
            ->assertSee('Waiting for Confirmation')
            ->assertSee('Returned Drill')
            ->assertSee(route('asset-movements.confirm', $movement), false);

        $this->actingAs($admin)->post(route('asset-movements.confirm', $movement))->assertRedirect();
        $this->assertSame('confirmed', $movement->fresh()->status);
    }

    public function test_a_team_leader_cannot_confirm_a_movement_waiting_at_the_company_store(): void
    {
        $admin = $this->admin();
        $teamLeader = $this->staffUser($admin, 'Executive Team Leader');

        $this->actingAs($admin)->post('/assets', ['name' => 'Site Generator'])->assertRedirect();
        $asset = Asset::where('name', 'Site Generator')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/movements", [
            'type' => 'other', 'from_location' => 'company_store', 'to_location' => 'company_store',
            'quantity' => 1, 'moved_at' => now()->toDateString(),
        ])->assertRedirect();
        $movement = AssetMovement::where('asset_id', $asset->id)->where('type', 'other')->firstOrFail();

        // Confirming here is Admin-only - a Team Leader has no site of
        // their own at the Company Store to be scoped to.
        $this->actingAs($teamLeader)->post(route('asset-movements.confirm', $movement))->assertForbidden();

        $response = $this->actingAs($teamLeader)->get('/assets');
        $response->assertOk()->assertDontSee(route('asset-movements.confirm', $movement), false);
    }
}
