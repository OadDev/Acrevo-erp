<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TaskManagementTest extends TestCase
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

    // Call only after admin() has already seeded roles/permissions in the
    // same test - reseeding here would duplicate the Role rows.
    private function userWithRole(string $role, string $name = 'User'): User
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_the_assigner_can_attach_files_when_assigning_a_task_and_the_assignee_can_see_them(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->post('/tasks', [
            'assigned_to' => $hr->id,
            'title' => 'Review the attached drawing',
            'due_date' => now()->addDay()->toDateString(),
            'attachments' => [
                UploadedFile::fake()->create('drawing.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('site-photo.jpg'),
            ],
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame(2, $task->getMedia('attachments')->count());
        $this->assertSame(0, $task->getMedia('proof')->count());

        $this->actingAs($hr)->get("/tasks/{$task->id}")
            ->assertOk()->assertSee('drawing.pdf')->assertSee('Attachments from Sales Person');
    }

    public function test_more_attachments_can_be_added_and_removed_while_editing_a_task(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->post('/tasks', [
            'assigned_to' => $hr->id, 'title' => 'Original task', 'due_date' => now()->addDay()->toDateString(),
            'attachments' => [UploadedFile::fake()->create('first.pdf', 50)],
        ])->assertRedirect();
        $task = Task::firstOrFail();

        $this->actingAs($sales)->put("/tasks/{$task->id}", [
            'assigned_to' => $hr->id, 'title' => 'Original task', 'due_date' => now()->addDay()->toDateString(),
            'attachments' => [UploadedFile::fake()->create('second.pdf', 50)],
        ])->assertRedirect();
        $this->assertSame(2, $task->fresh()->getMedia('attachments')->count());

        $media = $task->getMedia('attachments')->firstWhere('file_name', 'first.pdf');
        $this->actingAs($sales)->delete("/tasks/{$task->id}/media/{$media->id}")->assertRedirect();
        $this->assertSame(1, $task->fresh()->getMedia('attachments')->count());

        // Someone uninvolved with the task can't remove its attachments.
        $stranger = $this->userWithRole('QC Officer', 'Stranger');
        $remaining = $task->fresh()->getMedia('attachments')->first();
        $this->actingAs($stranger)->delete("/tasks/{$task->id}/media/{$remaining->id}")->assertForbidden();
    }

    public function test_a_common_task_can_be_assigned_completed_and_verified(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->post('/tasks', [
            'assigned_to' => $hr->id,
            'title' => 'Print the plan',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $task = Task::firstOrFail();

        $this->actingAs($sales)->get('/tasks?scope=assigned')->assertOk()->assertSee('Print the plan');
        $this->actingAs($sales)->get('/tasks/create')->assertOk();
        $this->actingAs($sales)->get("/tasks/{$task->id}")->assertOk()->assertSee('Print the plan');

        $this->assertSame($sales->id, $task->assigned_by);
        $this->assertSame($hr->id, $task->assigned_to);
        $this->assertSame($sales->id, $task->verifier_id);
        $this->assertSame('pending', $task->status);
        $this->assertNull($task->task_schedule_id);

        // Someone uninvolved can't act on it.
        $stranger = $this->userWithRole('QC Officer', 'Stranger');
        $this->actingAs($stranger)->post("/tasks/{$task->id}/complete", ['completion_notes' => 'Done'])
            ->assertForbidden();

        $this->actingAs($hr)->post("/tasks/{$task->id}/complete", [
            'completion_notes' => 'Printed and delivered to site.',
            'proof' => [\Illuminate\Http\UploadedFile::fake()->image('plan.jpg')],
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('submitted', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertCount(1, $task->media);

        // Only the assigner/verifier can verify.
        $this->actingAs($hr)->post("/tasks/{$task->id}/verify")->assertForbidden();

        $this->actingAs($sales)->post("/tasks/{$task->id}/verify")->assertRedirect();
        $task->refresh();
        $this->assertSame('verified', $task->status);
        $this->assertSame($sales->id, $task->verified_by);
    }

    public function test_an_unsatisfactory_task_can_be_retasked_to_another_user(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');
        $worker = $this->userWithRole('Worker', 'Worker Person');

        $this->actingAs($sales)->post('/tasks', [
            'assigned_to' => $hr->id,
            'title' => 'Collect document',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();
        $task = Task::firstOrFail();

        $this->actingAs($hr)->post("/tasks/{$task->id}/complete", ['completion_notes' => 'Collected.'])->assertRedirect();

        $this->actingAs($sales)->post("/tasks/{$task->id}/retask", [
            'assigned_to' => $worker->id,
            'due_date' => now()->addDays(2)->toDateString(),
            'retask_note' => 'Wrong document collected, please get the correct one.',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('retasked', $task->status);

        $newTask = Task::where('parent_task_id', $task->id)->firstOrFail();
        $this->assertSame($worker->id, $newTask->assigned_to);
        $this->assertSame('pending', $newTask->status);
        $this->assertSame('Wrong document collected, please get the correct one.', $newTask->retask_note);
    }

    public function test_reporting_a_delay_records_the_reason_without_closing_the_task(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->post('/tasks', [
            'assigned_to' => $hr->id,
            'title' => 'Fill petrol in the bike',
            'due_date' => now()->subDay()->toDateString(),
        ])->assertRedirect();
        $task = Task::firstOrFail();

        $this->actingAs($hr)->post("/tasks/{$task->id}/delay", [
            'delay_reason' => 'Bike was with another team all day.',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('pending', $task->status);
        $this->assertSame('Bike was with another team all day.', $task->delay_reason);
        $this->assertNotNull($task->delay_reported_at);
        $this->assertTrue($task->isOverdue());
    }

    public function test_the_client_role_cannot_reach_the_task_module_at_all(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $this->actingAs($admin)->post("/clients/{$client->id}/portal-access")->assertRedirect();
        $clientUser = \App\Models\ClientLogin::where('client_id', $client->id)->firstOrFail()->user;

        $this->actingAs($clientUser)->get('/tasks')->assertForbidden();
        $this->actingAs($clientUser)->get('/tasks/create')->assertForbidden();
    }

    public function test_only_admin_can_manage_calendar_task_schedules(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->get('/admin/task-schedules/create')->assertForbidden();
        $this->actingAs($sales)->post('/admin/task-schedules', [
            'title' => 'Check enquiry status', 'assignee_type' => 'user', 'assigned_to_user_id' => $hr->id,
            'frequency' => 'daily', 'verifier_user_id' => $admin->id,
        ])->assertForbidden();

        $this->actingAs($admin)->post('/admin/task-schedules', [
            'title' => 'Check enquiry status', 'assignee_type' => 'user', 'assigned_to_user_id' => $hr->id,
            'frequency' => 'daily', 'verifier_user_id' => $admin->id,
        ])->assertRedirect();

        $this->assertSame(1, TaskSchedule::count());
        $schedule = TaskSchedule::firstOrFail();

        $this->actingAs($admin)->get('/admin/task-schedules')->assertOk()->assertSee('Check enquiry status');
        $this->actingAs($admin)->get('/admin/task-schedules/create')->assertOk();
        $this->actingAs($admin)->get("/admin/task-schedules/{$schedule->id}/edit")->assertOk();

        $this->actingAs($admin)->put("/admin/task-schedules/{$schedule->id}", [
            'title' => 'Check enquiry status daily', 'assignee_type' => 'role', 'assignee_role' => 'Sales',
            'frequency' => 'weekly', 'day_of_week' => 1, 'verifier_user_id' => $admin->id,
        ])->assertRedirect();

        $schedule->refresh();
        $this->assertSame('Check enquiry status daily', $schedule->title);
        $this->assertNull($schedule->assigned_to_user_id);
        $this->assertSame('Sales', $schedule->assignee_role);
    }

    public function test_admin_can_filter_calendar_tasks_by_employee_or_role(): void
    {
        $admin = $this->admin();
        $hr = $this->userWithRole('HR', 'HR Person');
        $sales = $this->userWithRole('Sales', 'Sales Person');

        TaskSchedule::create([
            'title' => 'HR daily check', 'assigned_to_user_id' => $hr->id,
            'frequency' => 'daily', 'verifier_user_id' => $admin->id, 'is_active' => true, 'created_by' => $admin->id,
        ]);
        TaskSchedule::create([
            'title' => 'Sales weekly report', 'assignee_role' => 'Sales',
            'frequency' => 'weekly', 'day_of_week' => 1, 'verifier_user_id' => $admin->id, 'is_active' => true, 'created_by' => $admin->id,
        ]);

        $byUser = $this->actingAs($admin)->get('/admin/task-schedules?user_id='.$hr->id);
        $byUser->assertOk()->assertSee('HR daily check')->assertDontSee('Sales weekly report');

        $byRole = $this->actingAs($admin)->get('/admin/task-schedules?role=Sales');
        $byRole->assertOk()->assertSee('Sales weekly report')->assertDontSee('HR daily check');

        $this->actingAs($admin)->get('/admin/task-schedules')->assertOk()->assertSee('HR daily check')->assertSee('Sales weekly report');

        // "+ New Calendar Task" from a filtered view should pre-select that employee.
        $prefilled = $this->actingAs($admin)->get('/admin/task-schedules/create?user_id='.$hr->id);
        $prefilled->assertOk();
        $prefilled->assertSee('selected', false);
    }

    public function test_admin_can_filter_calendar_tasks_by_frequency(): void
    {
        $admin = $this->admin();
        $hr = $this->userWithRole('HR', 'HR Person');

        TaskSchedule::create([
            'title' => 'HR daily check', 'assigned_to_user_id' => $hr->id,
            'frequency' => 'daily', 'verifier_user_id' => $admin->id, 'is_active' => true, 'created_by' => $admin->id,
        ]);
        TaskSchedule::create([
            'title' => 'HR monthly report', 'assigned_to_user_id' => $hr->id,
            'frequency' => 'monthly', 'day_of_month' => 1, 'verifier_user_id' => $admin->id, 'is_active' => true, 'created_by' => $admin->id,
        ]);

        $byFrequency = $this->actingAs($admin)->get('/admin/task-schedules?frequency=monthly');
        $byFrequency->assertOk()->assertSee('HR monthly report')->assertDontSee('HR daily check');

        // "+ New Calendar Task" from a frequency-filtered view should pre-select that frequency.
        $prefilled = $this->actingAs($admin)->get('/admin/task-schedules/create?frequency=weekly');
        $prefilled->assertOk();
    }

    public function test_a_daily_calendar_task_is_generated_once_per_day_for_its_assignee(): void
    {
        $admin = $this->admin();
        $hr = $this->userWithRole('HR', 'HR Person');

        TaskSchedule::create([
            'title' => 'Check enquiry status', 'assigned_to_user_id' => $hr->id,
            'frequency' => 'daily', 'verifier_user_id' => $admin->id, 'is_active' => true, 'created_by' => $admin->id,
        ]);

        $this->actingAs($hr)->get('/tasks')->assertOk();
        $this->assertSame(1, Task::where('assigned_to', $hr->id)->count());

        // Visiting again the same day must not duplicate the instance.
        $this->actingAs($hr)->get('/tasks')->assertOk();
        $this->assertSame(1, Task::where('assigned_to', $hr->id)->count());

        $generated = Task::where('assigned_to', $hr->id)->firstOrFail();
        $this->assertSame($admin->id, $generated->assigned_by);
        $this->assertSame($admin->id, $generated->verifier_id);
        $this->assertSame(now()->toDateString(), $generated->due_date->toDateString());
    }

    public function test_a_role_targeted_calendar_task_generates_for_every_user_holding_that_role(): void
    {
        $admin = $this->admin();
        $sales1 = $this->userWithRole('Sales', 'Sales One');
        $sales2 = $this->userWithRole('Sales', 'Sales Two');

        TaskSchedule::create([
            'title' => 'Submit weekly report', 'assignee_role' => 'Sales',
            'frequency' => 'weekly', 'day_of_week' => now()->dayOfWeek,
            'verifier_user_id' => $admin->id, 'is_active' => true, 'created_by' => $admin->id,
        ]);

        $this->actingAs($sales1)->get('/tasks')->assertOk();
        $this->actingAs($sales2)->get('/tasks')->assertOk();

        $this->assertSame(1, Task::where('assigned_to', $sales1->id)->count());
        $this->assertSame(1, Task::where('assigned_to', $sales2->id)->count());
    }

    public function test_weekly_and_monthly_due_dates_are_not_generated_before_their_configured_day(): void
    {
        $schedule = new TaskSchedule([
            'frequency' => 'weekly', 'day_of_week' => 5,
        ]);
        $wednesday = \Carbon\Carbon::parse('next Wednesday');
        $this->assertNull($schedule->dueDateFor($wednesday));

        $friday = $wednesday->copy()->next(\Carbon\Carbon::FRIDAY);
        $this->assertNotNull($schedule->dueDateFor($friday));

        $monthlySchedule = new TaskSchedule(['frequency' => 'monthly', 'day_of_month' => 25]);
        $early = \Carbon\Carbon::parse('first day of next month')->addDays(4);
        $this->assertNull($monthlySchedule->dueDateFor($early));

        $late = \Carbon\Carbon::parse('first day of next month')->addDays(25);
        $this->assertNotNull($monthlySchedule->dueDateFor($late));
    }

    public function test_a_common_task_can_be_edited_and_removed_by_its_assigner(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');
        $stranger = $this->userWithRole('QC Officer', 'Stranger');

        $this->actingAs($sales)->post('/tasks', [
            'assigned_to' => $hr->id,
            'title' => 'Collect document',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();
        $task = Task::firstOrFail();

        $this->actingAs($stranger)->get("/tasks/{$task->id}/edit")->assertForbidden();
        $this->actingAs($stranger)->delete("/tasks/{$task->id}")->assertForbidden();

        $this->actingAs($sales)->get("/tasks/{$task->id}/edit")->assertOk();
        $this->actingAs($sales)->put("/tasks/{$task->id}", [
            'assigned_to' => $hr->id,
            'title' => 'Collect the correct document',
            'due_date' => now()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('Collect the correct document', $task->title);

        $this->actingAs($sales)->delete("/tasks/{$task->id}")->assertRedirect();
        $this->assertNull(Task::find($task->id));
    }

    public function test_a_verified_calendar_task_instance_can_be_removed_but_not_edited(): void
    {
        $admin = $this->admin();
        $hr = $this->userWithRole('HR', 'HR Person');

        TaskSchedule::create([
            'title' => 'Check enquiry status', 'assigned_to_user_id' => $hr->id,
            'frequency' => 'daily', 'verifier_user_id' => $admin->id, 'is_active' => true, 'created_by' => $admin->id,
        ]);

        $this->actingAs($hr)->get('/tasks')->assertOk();
        $task = Task::where('assigned_to', $hr->id)->firstOrFail();

        // Calendar instances follow their schedule - not directly editable.
        $this->actingAs($admin)->get("/tasks/{$task->id}/edit")->assertNotFound();
        $this->actingAs($admin)->put("/tasks/{$task->id}", ['assigned_to' => $hr->id, 'title' => 'x', 'due_date' => now()->toDateString()])->assertNotFound();

        $this->actingAs($hr)->post("/tasks/{$task->id}/complete", ['completion_notes' => 'Checked.'])->assertRedirect();
        $this->actingAs($admin)->post("/tasks/{$task->id}/verify")->assertRedirect();

        $task->refresh();
        $this->assertSame('verified', $task->status);

        // Still visible in the list until explicitly removed.
        $this->actingAs($hr)->get('/tasks?status=verified')->assertOk()->assertSee('Check enquiry status');

        $this->actingAs($admin)->delete("/tasks/{$task->id}")->assertRedirect();
        $this->assertNull(Task::find($task->id));
    }

    public function test_only_admin_can_download_the_task_performance_pdf(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');
        $hr = $this->userWithRole('HR', 'HR Person');

        $this->actingAs($sales)->post('/tasks', [
            'assigned_to' => $hr->id,
            'title' => 'Collect document',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($sales)->get('/tasks/pdf')->assertForbidden();
        $this->actingAs($hr)->get('/tasks/pdf')->assertForbidden();

        $pdf = $this->actingAs($admin)->get('/tasks/pdf?user_id='.$hr->id);
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(500, strlen($pdf->getContent()));
    }

    public function test_the_admin_only_filter_is_hidden_from_non_admin_users(): void
    {
        $admin = $this->admin();
        $sales = $this->userWithRole('Sales', 'Sales Person');

        $this->actingAs($admin)->get('/tasks')->assertOk()->assertSee('Admin Filter');
        $this->actingAs($sales)->get('/tasks')->assertOk()->assertDontSee('Admin Filter');
    }
}
