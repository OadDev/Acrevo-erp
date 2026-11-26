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
 * Sir reported that an additional work item requested by a client after
 * the schedule was already built could only be added by deleting and
 * recreating every later work - losing their progress, completed status,
 * and actual dates in the process. This covers inserting a new work at
 * any position (with later works shifting down and the dependency chain
 * flowing through it automatically) and moving an existing work to a
 * different position later on, without ever deleting a row.
 */
class SiteWorkScheduleReorderTest extends TestCase
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

    public function test_inserting_a_work_between_two_completed_works_shifts_later_works_down_and_rewires_the_dependency_chain(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Work 1', 'duration_days' => 5, 'start_date' => '2026-10-01',
        ])->assertRedirect();
        $work1 = SiteWorkSchedule::where('work_name', 'Work 1')->firstOrFail();

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Work 2', 'duration_days' => 3, 'schedule_mode' => 'depends_on',
            'depends_on_schedule_id' => $work1->id, 'lag_days' => 0,
        ])->assertRedirect();
        $work2 = SiteWorkSchedule::where('work_name', 'Work 2')->firstOrFail();

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Work 3', 'duration_days' => 2, 'schedule_mode' => 'depends_on',
            'depends_on_schedule_id' => $work2->id, 'lag_days' => 0,
        ])->assertRedirect();
        $work3 = SiteWorkSchedule::where('work_name', 'Work 3')->firstOrFail();

        // Work 2 is already finished - this is the progress/history that
        // must survive the insert untouched.
        $work2->forceFill([
            'status' => 'completed', 'actual_progress_percent' => 100,
            'actual_start_date' => '2026-10-06', 'actual_end_date' => '2026-10-08',
        ])->save();

        // The client now asks for a new work between Work 1 and Work 2.
        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'New Work', 'duration_days' => 4, 'schedule_mode' => 'depends_on',
            'depends_on_schedule_id' => $work1->id, 'lag_days' => 0,
            'insert_before_schedule_id' => $work2->id,
        ])->assertRedirect();
        $newWork = SiteWorkSchedule::where('work_name', 'New Work')->firstOrFail();

        // Nothing was deleted - same three original rows still exist, now
        // alongside the new one.
        $this->assertSame(4, SiteWorkSchedule::where('site_id', $site->id)->count());

        $this->assertSame(1, $work1->fresh()->sequence_order);
        $this->assertSame(2, $newWork->sequence_order);
        $this->assertSame(3, $work2->fresh()->sequence_order);
        $this->assertSame(4, $work3->fresh()->sequence_order);

        // Work 2 depended directly on Work 1 - since New Work was inserted
        // right between them on that same dependency, Work 2 is
        // automatically re-pointed to depend on New Work instead.
        $this->assertSame($newWork->id, $work2->fresh()->depends_on_schedule_id);

        // Work 3's own dependency (on Work 2) is untouched.
        $this->assertSame($work2->id, $work3->fresh()->depends_on_schedule_id);

        // Work 2's history is exactly as recorded - never touched by the
        // insert, even though its dependency and dates were recalculated.
        $work2Fresh = $work2->fresh();
        $this->assertSame('completed', $work2Fresh->status);
        $this->assertSame(100, $work2Fresh->actual_progress_percent);
        $this->assertSame('2026-10-06', $work2Fresh->actual_start_date->toDateString());
        $this->assertSame('2026-10-08', $work2Fresh->actual_end_date->toDateString());

        // Dates now flow through the newly inserted work: Work 1 ends
        // 2026-10-05, New Work runs 10-06..10-09 (4 days), and Work 2's
        // *planned* dates shift accordingly even though its actual dates
        // (already recorded above) are what the completed work really ran.
        $this->assertSame('2026-10-06', $newWork->fresh()->revised_start_date->toDateString());
        $this->assertSame('2026-10-09', $newWork->fresh()->revised_end_date->toDateString());
        $this->assertSame('2026-10-10', $work2Fresh->revised_start_date->toDateString());

        // Work 3 depends on Work 2, which is completed - so Work 3 chains
        // off Work 2's actual end date (10-08), not its revised one.
        $this->assertSame('2026-10-09', $work3->fresh()->revised_start_date->toDateString());
    }

    public function test_admin_can_move_a_work_to_an_earlier_position_in_the_sequence(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        foreach (['Work 1', 'Work 2', 'Work 3'] as $name) {
            $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
                'work_name' => $name, 'duration_days' => 2, 'start_date' => '2026-11-01',
                'schedule_mode' => 'independent',
            ])->assertRedirect();
        }
        [$work1, $work2, $work3] = [
            SiteWorkSchedule::where('work_name', 'Work 1')->firstOrFail(),
            SiteWorkSchedule::where('work_name', 'Work 2')->firstOrFail(),
            SiteWorkSchedule::where('work_name', 'Work 3')->firstOrFail(),
        ];

        // Client wants the newest work (Work 3) done before Work 1 after all.
        $this->actingAs($admin)->patch("/sites/{$site->id}/work-schedule/{$work3->id}/move", [
            'before_schedule_id' => $work1->id,
        ])->assertRedirect();

        $this->assertSame(3, SiteWorkSchedule::where('site_id', $site->id)->count());
        $this->assertSame(1, $work3->fresh()->sequence_order);
        $this->assertSame(2, $work1->fresh()->sequence_order);
        $this->assertSame(3, $work2->fresh()->sequence_order);
    }

    public function test_moving_a_work_past_something_that_depends_on_it_is_rejected(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Work 1', 'duration_days' => 5, 'start_date' => '2026-10-01',
        ])->assertRedirect();
        $work1 = SiteWorkSchedule::where('work_name', 'Work 1')->firstOrFail();

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Work 2', 'duration_days' => 3, 'schedule_mode' => 'depends_on',
            'depends_on_schedule_id' => $work1->id, 'lag_days' => 0,
        ])->assertRedirect();

        // Work 2 depends on Work 1 - moving Work 1 to the end (after
        // Work 2) would leave it depending on something scheduled after
        // it, so this must be rejected rather than silently corrupting
        // the chain.
        $this->actingAs($admin)->patch("/sites/{$site->id}/work-schedule/{$work1->id}/move", [
            'before_schedule_id' => null,
        ])->assertStatus(422);

        $this->assertSame(1, $work1->fresh()->sequence_order);
    }
}
