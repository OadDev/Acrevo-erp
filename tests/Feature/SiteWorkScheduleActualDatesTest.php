<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Site;
use App\Models\SiteWorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's exact "Wall Work" example: planned Sep 1 - Sep 10 (10 days),
 * actually ran Sep 1 - Sep 12 (12 days), 2 days late. Actual Start/End
 * Date must be visible, drive a computed Actual Duration, and never
 * silently change the Original or Revised Days allocated to the work.
 */
class SiteWorkScheduleActualDatesTest extends TestCase
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

    public function test_actual_dates_are_shown_and_actual_duration_matches_the_wall_work_example(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-09-01',
        ])->assertRedirect();
        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();

        $this->assertSame('2026-09-10', $wallWork->original_end_date->format('Y-m-d'));

        $this->actingAs($admin)->put("/sites/{$site->id}/work-schedule/{$wallWork->id}", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'schedule_mode' => 'independent',
            'start_date' => '2026-09-01', 'status' => 'completed',
            'actual_start_date' => '2026-09-01', 'actual_end_date' => '2026-09-12',
        ])->assertRedirect();

        $wallWork->refresh();

        $this->assertSame(12, $wallWork->actualDurationDays());
        $this->assertSame(2, $wallWork->varianceDays());
        $this->assertSame(2, $wallWork->ownDelayDays());
        $this->assertTrue($wallWork->isDelayed());

        // Original and Revised Days stay exactly as allocated - recording
        // actual dates never touches them.
        $this->assertSame(10, $wallWork->original_duration_days);
        $this->assertSame(10, $wallWork->revised_duration_days);

        $this->actingAs($admin)->get("/sites/{$site->id}/work-schedule")
            ->assertOk()
            ->assertSee('Actual Start')
            ->assertSee('Actual End')
            ->assertSee('Actual Duration')
            ->assertSee('01 Sep 2026')
            ->assertSee('12 Sep 2026')
            ->assertSee('12d');
    }

    public function test_actual_end_date_can_stay_blank_while_the_work_is_still_ongoing(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-09-01',
        ])->assertRedirect();
        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();

        $this->actingAs($admin)->put("/sites/{$site->id}/work-schedule/{$wallWork->id}", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'schedule_mode' => 'independent',
            'start_date' => '2026-09-01', 'status' => 'in_progress', 'actual_start_date' => '2026-09-01',
        ])->assertRedirect();

        $wallWork->refresh();

        $this->assertNull($wallWork->actual_end_date);
        $this->assertNull($wallWork->actualDurationDays());
        $this->assertSame(0, $wallWork->startVarianceDays(), 'Started exactly on the planned start date.');
    }

    public function test_actual_end_date_before_actual_start_date_is_rejected(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-09-01',
        ])->assertRedirect();
        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();

        $this->actingAs($admin)->put("/sites/{$site->id}/work-schedule/{$wallWork->id}", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'schedule_mode' => 'independent',
            'start_date' => '2026-09-01', 'status' => 'completed',
            'actual_start_date' => '2026-09-05', 'actual_end_date' => '2026-09-03',
        ])->assertSessionHasErrors('actual_end_date');

        $this->assertNull($wallWork->fresh()->actual_end_date);
    }

    public function test_a_work_that_started_late_shows_a_positive_start_variance(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-09-01',
        ])->assertRedirect();
        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();

        $this->actingAs($admin)->put("/sites/{$site->id}/work-schedule/{$wallWork->id}", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'schedule_mode' => 'independent',
            'start_date' => '2026-09-01', 'status' => 'in_progress', 'actual_start_date' => '2026-09-03',
        ])->assertRedirect();

        $wallWork->refresh();

        $this->assertSame(2, $wallWork->startVarianceDays());
    }
}
