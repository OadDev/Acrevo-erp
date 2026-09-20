<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 7 of the Equipment & Asset Management module: every important
 * action across Assets/Movements/Repairs/Verifications/Equipment Requests
 * is captured in the shared, Admin-only Activity Log - with the causer's
 * role and the work order/site attached, and the previous/new value of
 * whatever changed. The log itself is create-only: there is no route to
 * edit or delete an entry.
 */
class ActivityLogTest extends TestCase
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

    public function test_updating_an_assets_status_is_captured_in_the_activity_log_with_old_and_new_value(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Concrete Mixer', 'status' => 'available'])->assertRedirect();
        $asset = Asset::where('name', 'Concrete Mixer')->firstOrFail();

        $this->actingAs($admin)->post("/assets/{$asset->id}/status", ['status' => 'in_use'])->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Asset::class,
            'subject_id' => (string) $asset->id,
            'log_name' => 'assets',
        ]);

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', Asset::class)
            ->where('subject_id', $asset->id)
            ->where('description', 'updated')
            ->latest()
            ->firstOrFail();

        $this->assertSame('in_use', $activity->properties->get('attributes')['status']);
        $this->assertSame('available', $activity->properties->get('old')['status']);
        $this->assertNotEmpty($activity->properties->get('role'));
        $this->assertSame($admin->id, $activity->causer_id);
    }

    public function test_admin_can_view_the_activity_log_and_filter_by_module(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Wall Cutter'])->assertRedirect();

        $this->actingAs($admin)->get('/admin/activity-logs')->assertOk()->assertSee('Wall Cutter', false);
        $this->actingAs($admin)->get('/admin/activity-logs?log_name=assets')->assertOk();
    }

    public function test_a_role_without_activity_logs_permission_cannot_view_the_log(): void
    {
        $admin = $this->admin();

        $email = 'teamleader+'.uniqid().'@example.com';
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Team Leader', 'email' => $email, 'role' => 'Executive Team Leader',
        ])->assertRedirect();
        $teamLeader = User::where('email', $email)->firstOrFail();

        $this->actingAs($teamLeader)->get('/admin/activity-logs')->assertForbidden();
    }

    public function test_activity_log_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/assets', ['name' => 'Ladder'])->assertRedirect();

        $response = $this->actingAs($admin)->get('/admin/activity-logs/pdf');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_the_activity_log_has_no_edit_or_delete_route(): void
    {
        $this->assertFalse(Route::has('admin.activity-logs.destroy'));
        $this->assertFalse(Route::has('admin.activity-logs.update'));
    }
}
