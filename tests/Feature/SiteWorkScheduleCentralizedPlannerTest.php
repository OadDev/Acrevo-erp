<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\ExecutiveTeam;
use App\Models\Site;
use App\Models\SiteWorkSchedule;
use App\Models\User;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir asked for one centralized Work Schedule Planner: an all-site
 * calendar/date view, a combined all-sites table instead of opening each
 * site separately, and a work-team assignment per scheduled activity that
 * is deliberately NOT tied to Work Order (so it lives directly on
 * SiteWorkSchedule, never through WorkOrderExecutiveTeam).
 */
class SiteWorkScheduleCentralizedPlannerTest extends TestCase
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

    private function site(User $admin, string $name = 'C'): Site
    {
        $client = Client::create(['name' => $name, 'email' => $name.'+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        return Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
    }

    private function team(User $admin, string $name = 'Alpha Team'): ExecutiveTeam
    {
        return ExecutiveTeam::create([
            'team_number' => 'TEAM-'.uniqid(), 'name' => $name, 'team_leader_id' => $admin->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);
    }

    public function test_a_work_team_can_be_assigned_to_a_scheduled_work_item_without_touching_work_orders(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);
        $team = $this->team($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-10-01',
            'executive_team_id' => $team->id,
        ])->assertRedirect();

        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();
        $this->assertSame($team->id, $wallWork->executive_team_id);
        $this->assertTrue($wallWork->executiveTeam->is($team));

        // The whole point: this assignment never creates a Work Order team
        // link - it's a direct schedule-to-team assignment.
        $this->assertSame(0, WorkOrderExecutiveTeam::count());

        $this->actingAs($admin)->get("/sites/{$site->id}/work-schedule")
            ->assertOk()
            ->assertSee('Alpha Team');
    }

    public function test_a_work_teams_assignment_can_be_changed_via_the_edit_form(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);
        $teamA = $this->team($admin, 'Alpha Team');
        $teamB = $this->team($admin, 'Bravo Team');

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-10-01',
            'executive_team_id' => $teamA->id,
        ])->assertRedirect();
        $wallWork = SiteWorkSchedule::where('work_name', 'Wall Work')->firstOrFail();

        $this->actingAs($admin)->put("/sites/{$site->id}/work-schedule/{$wallWork->id}", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'schedule_mode' => 'independent',
            'start_date' => '2026-10-01', 'status' => 'in_progress',
            'executive_team_id' => $teamB->id,
        ])->assertRedirect();

        $this->assertSame($teamB->id, $wallWork->fresh()->executive_team_id);
    }

    public function test_the_combined_all_works_view_shows_works_from_every_accessible_site_with_their_team(): void
    {
        $admin = $this->admin();
        $siteA = $this->site($admin, 'Client A');
        $siteB = $this->site($admin, 'Client B');
        $team = $this->team($admin);

        $this->actingAs($admin)->post("/sites/{$siteA->id}/work-schedule", [
            'work_name' => 'Foundation', 'duration_days' => 5, 'start_date' => '2026-10-01',
            'executive_team_id' => $team->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post("/sites/{$siteB->id}/work-schedule", [
            'work_name' => 'Roofing', 'duration_days' => 5, 'start_date' => '2026-10-05',
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get('/work-schedules');

        $response->assertOk()
            ->assertSee('Foundation')
            ->assertSee('Roofing')
            ->assertSee($siteA->site_no)
            ->assertSee($siteB->site_no)
            ->assertSee('Alpha Team');
    }

    public function test_the_calendar_dataset_covers_the_full_span_of_a_multi_day_work_so_any_date_in_range_can_be_looked_up(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);
        $team = $this->team($admin);

        $this->actingAs($admin)->post("/sites/{$site->id}/work-schedule", [
            'work_name' => 'Wall Work', 'duration_days' => 10, 'start_date' => '2026-10-01',
            'executive_team_id' => $team->id,
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get('/work-schedules');
        $response->assertOk();

        // The client-side calendar tab filters this embedded (JSON-escaped)
        // dataset by date range, so both ends of the work's span must be
        // present verbatim for Alpine to match against.
        $response->assertSee('2026-10-01', false);
        $response->assertSee('2026-10-10', false);
        $response->assertSee('Alpha Team', false);
    }

    public function test_a_sales_user_only_sees_works_for_sites_of_clients_assigned_to_them_in_the_combined_view(): void
    {
        $admin = $this->admin();
        $mySite = $this->site($admin, 'My Client');
        $otherSite = $this->site($admin, 'Other Client');

        $sales = User::create([
            'name' => 'Sales Rep', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);
        $mySite->client->update(['assigned_sales_user_id' => $sales->id]);

        $this->actingAs($admin)->post("/sites/{$mySite->id}/work-schedule", [
            'work_name' => 'My Work', 'duration_days' => 5, 'start_date' => '2026-10-01',
        ])->assertRedirect();
        $this->actingAs($admin)->post("/sites/{$otherSite->id}/work-schedule", [
            'work_name' => 'Other Work', 'duration_days' => 5, 'start_date' => '2026-10-01',
        ])->assertRedirect();

        $response = $this->actingAs($sales)->get('/work-schedules');

        $response->assertOk()->assertSee('My Work')->assertDontSee('Other Work');
    }
}
