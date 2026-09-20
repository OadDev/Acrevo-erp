<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Site;
use App\Models\SiteWorkSchedule;
use App\Models\User;
use App\Services\WorkScheduleRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir asked for the Duration and Variance columns to be split so Admin and
 * clients can tell, per work: how many days were originally allocated vs
 * revised, and how much of any delay is this work's own versus carried
 * forward from a delayed dependency (an upcoming work with no delay of its
 * own must not show as independently delayed just because an earlier work
 * pushed its start date out).
 */
class SiteWorkScheduleVarianceTest extends TestCase
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

    private function site(User $admin): Site
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        return Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
    }

    /**
     * The exact chain from the request: Wall Work (10d, finishes 2 days
     * late on its own), Plastering (5d, depends on Wall Work, no delay of
     * its own), Painting (3d, depends on Plastering, 1 extra day of its
     * own on top of what it inherited).
     */
    public function test_own_delay_and_previous_work_delay_split_matches_the_requested_example(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-01-01',
        ])->assertRedirect();
        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Plastering', 'duration_days' => 5, 'schedule_mode' => 'depends_on',
            'depends_on_schedule_id' => $wallWork->id, 'lag_days' => 0,
        ])->assertRedirect();
        $plastering = SiteWorkSchedule::where('work_name', 'Plastering')->firstOrFail();

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Painting', 'duration_days' => 3, 'schedule_mode' => 'depends_on',
            'depends_on_schedule_id' => $plastering->id, 'lag_days' => 0,
        ])->assertRedirect();
        $painting = SiteWorkSchedule::where('work_name', 'Painting')->firstOrFail();

        // Wall Work itself runs 2 days over its own original schedule.
        $this->actingAs($admin)->put("/sites/{$site->id}/work-schedule/{$wallWork->id}", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'schedule_mode' => 'independent',
            'start_date' => $wallWork->revised_start_date->format('Y-m-d'),
            'status' => 'completed', 'actual_end_date' => $wallWork->original_end_date->copy()->addDays(2)->format('Y-m-d'),
        ])->assertRedirect();

        // Painting runs 1 extra day beyond what it inherited from upstream.
        $painting->refresh();
        $this->actingAs($admin)->put("/sites/{$site->id}/work-schedule/{$painting->id}", [
            'work_name' => 'Painting', 'duration_days' => 4, 'schedule_mode' => 'depends_on',
            'depends_on_schedule_id' => $plastering->id, 'lag_days' => 0, 'status' => 'not_started',
        ])->assertRedirect();

        $wallWork->refresh();
        $plastering->refresh();
        $painting->refresh();

        // Original allocated days never move.
        $this->assertSame(10, $wallWork->original_duration_days);
        $this->assertSame(5, $plastering->original_duration_days);
        $this->assertSame(3, $painting->original_duration_days);

        // Revised days reflect the explicit change (Painting only).
        $this->assertSame(10, $wallWork->revised_duration_days);
        $this->assertSame(5, $plastering->revised_duration_days);
        $this->assertSame(4, $painting->revised_duration_days);

        // Wall Work: no dependency, so its whole +2 variance is its own.
        $this->assertSame(0, $wallWork->previousWorkDelayDays());
        $this->assertSame(2, $wallWork->ownDelayDays());

        // Plastering: inherits Wall Work's +2, contributes nothing itself.
        $this->assertSame(2, $plastering->previousWorkDelayDays());
        $this->assertSame(0, $plastering->ownDelayDays());

        // Painting: inherits the same +2 (Plastering added none), plus its
        // own +1 from the extended duration.
        $this->assertSame(2, $painting->previousWorkDelayDays());
        $this->assertSame(1, $painting->ownDelayDays());
    }

    public function test_a_work_delayed_only_by_an_earlier_dependency_is_not_flagged_as_independently_delayed(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-01-01',
        ])->assertRedirect();
        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Plastering', 'duration_days' => 5, 'schedule_mode' => 'depends_on',
            'depends_on_schedule_id' => $wallWork->id, 'lag_days' => 0,
        ])->assertRedirect();
        $plastering = SiteWorkSchedule::where('work_name', 'Plastering')->firstOrFail();

        $this->actingAs($admin)->put("/sites/{$site->id}/work-schedule/{$wallWork->id}", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'schedule_mode' => 'independent',
            'start_date' => $wallWork->revised_start_date->format('Y-m-d'),
            'status' => 'completed', 'actual_end_date' => $wallWork->original_end_date->copy()->addDays(2)->format('Y-m-d'),
        ])->assertRedirect();

        WorkScheduleRecalculator::recalculate($site);
        $plastering->refresh();

        // Plastering finishes exactly on its own (already-shifted) schedule.
        $plastering->forceFill(['status' => 'completed', 'actual_end_date' => $plastering->revised_end_date])->save();
        $plastering->refresh();

        $this->assertSame(2, $plastering->varianceDays(), 'Total variance still reflects the inherited shift.');
        $this->assertSame(2, $plastering->previousWorkDelayDays());
        $this->assertSame(0, $plastering->ownDelayDays());
        $this->assertFalse($plastering->isDelayed(), 'Plastering has no delay of its own and must not show as delayed.');

        $this->actingAs($admin)->get("/sites/{$site->id}/work-schedule")
            ->assertOk()
            ->assertSee('Previous Work Delay')
            ->assertSee('Own Delay');
    }

    public function test_an_independent_work_never_carries_a_previous_work_delay(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-01-01',
        ])->assertRedirect();
        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();

        $this->assertSame(0, $wallWork->previousWorkDelayDays());
        $this->assertSame($wallWork->varianceDays(), $wallWork->ownDelayDays());
    }

    public function test_a_client_can_see_the_original_revised_and_split_delay_columns_for_their_own_site(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'Pari', 'email' => 'pari+'.uniqid().'@example.com', 'phone' => '1', 'address' => 'Addr', 'is_active' => true, 'created_by' => $admin->id]);
        $site = Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
        $clientUser = User::create([
            'name' => $client->name, 'email' => $client->email,
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $clientUser->syncRoles(['Client']);
        ClientLogin::create(['client_id' => $client->id, 'user_id' => $clientUser->id]);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-01-01',
        ])->assertRedirect();

        $this->actingAs($clientUser)->get("/sites/{$site->id}/work-schedule")
            ->assertOk()
            ->assertSee('Original Days')
            ->assertSee('Revised Days')
            ->assertSee('Previous Work Delay')
            ->assertSee('Own Delay');
    }
}
