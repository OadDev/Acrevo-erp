<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CandidateManagementTest extends TestCase
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

    public function test_a_candidate_can_be_recorded_with_documents(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/candidates', [
            'name' => 'Jane Applicant', 'position_applied' => 'Site Supervisor',
            'phone' => '9999999999', 'qualification' => 'B.E. Civil',
            'interview_date' => '2026-08-25',
            'files' => [
                UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            ],
        ]);
        $response->assertRedirect();

        $candidate = Candidate::where('name', 'Jane Applicant')->firstOrFail();
        $this->assertSame('pending', $candidate->status);
        $this->assertSame(1, $candidate->media()->count());
        $this->assertSame($admin->id, $candidate->created_by);

        $this->actingAs($admin)->get("/candidates/{$candidate->id}")
            ->assertOk()->assertSee('Jane Applicant')->assertSee('resume.pdf');
    }

    public function test_editing_a_candidate_can_add_and_remove_documents(): void
    {
        $admin = $this->admin();
        $candidate = Candidate::create(['name' => 'Editable Candidate', 'status' => 'pending', 'created_by' => $admin->id]);
        $media = $candidate->addMedia(UploadedFile::fake()->create('old.pdf', 50))->toMediaCollection('documents');

        $this->actingAs($admin)->put("/candidates/{$candidate->id}", [
            'name' => 'Editable Candidate Updated',
            'files' => [UploadedFile::fake()->create('new.pdf', 50)],
        ])->assertRedirect();

        $this->assertSame('Editable Candidate Updated', $candidate->fresh()->name);
        $this->assertSame(2, $candidate->fresh()->media()->count());

        $this->actingAs($admin)->delete("/candidates/{$candidate->id}/media/{$media->id}")->assertRedirect();
        $this->assertSame(1, $candidate->fresh()->media()->count());
    }

    public function test_recording_an_interview_decision_updates_status(): void
    {
        $admin = $this->admin();
        $candidate = Candidate::create(['name' => 'To Decide', 'status' => 'pending', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post("/candidates/{$candidate->id}/decide", [
            'status' => 'selected', 'interview_date' => '2026-08-21', 'interview_notes' => 'Strong technical skills.',
        ])->assertRedirect();

        $candidate->refresh();
        $this->assertSame('selected', $candidate->status);
        $this->assertSame('Strong technical skills.', $candidate->interview_notes);
    }

    public function test_a_not_selected_candidate_stays_in_the_database_and_can_be_reconsidered_later(): void
    {
        $admin = $this->admin();
        $candidate = Candidate::create(['name' => 'Not This Time', 'status' => 'pending', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post("/candidates/{$candidate->id}/decide", ['status' => 'not_selected'])
            ->assertRedirect();
        $this->assertSame('not_selected', $candidate->fresh()->status);

        $this->actingAs($admin)->get('/candidates?status=not_selected')->assertOk()->assertSee('Not This Time');
    }

    public function test_a_selected_candidate_can_be_hired_into_an_employee_record(): void
    {
        $admin = $this->admin();
        $candidate = Candidate::create([
            'name' => 'Ready To Hire', 'status' => 'selected', 'phone' => '8888888888',
            'position_applied' => 'Mason', 'qualification' => 'ITI', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post("/candidates/{$candidate->id}/hire", [
            'name' => 'Ready To Hire', 'designation' => 'Mason', 'employment_type' => 'daily_wage',
            'salary_type' => 'daily', 'salary_amount' => 800,
        ]);
        $response->assertRedirect();

        $candidate->refresh();
        $this->assertSame('hired', $candidate->status);
        $this->assertNotNull($candidate->employee_id);

        $employee = Employee::findOrFail($candidate->employee_id);
        $this->assertSame('Ready To Hire', $employee->name);
        $this->assertSame('daily_wage', $employee->employment_type);
        $this->assertSame('active', $employee->status);

        $this->actingAs($admin)->get("/candidates/{$candidate->id}")
            ->assertOk()->assertSee(route('employees.show', $employee));
    }

    public function test_a_pending_candidate_cannot_be_hired_directly(): void
    {
        $admin = $this->admin();
        $candidate = Candidate::create(['name' => 'Still Pending', 'status' => 'pending', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post("/candidates/{$candidate->id}/hire", [
            'name' => 'Still Pending', 'employment_type' => 'permanent', 'salary_type' => 'monthly',
        ])->assertStatus(422);

        $this->assertSame('pending', $candidate->fresh()->status);
        $this->assertNull($candidate->fresh()->employee_id);
    }

    public function test_a_hired_candidate_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $employee = Employee::create([
            'employee_code' => 'EMP-'.uniqid(), 'name' => 'Hired Person', 'status' => 'active',
            'employment_type' => 'permanent', 'created_by' => $admin->id,
        ]);
        $candidate = Candidate::create(['name' => 'Hired Person', 'status' => 'hired', 'employee_id' => $employee->id, 'created_by' => $admin->id]);

        $this->actingAs($admin)->delete("/candidates/{$candidate->id}")->assertStatus(422);
        $this->assertNotNull($candidate->fresh());
    }

    public function test_a_non_hired_candidate_can_be_removed(): void
    {
        $admin = $this->admin();
        $candidate = Candidate::create(['name' => 'Removable', 'status' => 'not_selected', 'created_by' => $admin->id]);

        $this->actingAs($admin)->delete("/candidates/{$candidate->id}")->assertRedirect();
        $this->assertNull(Candidate::find($candidate->id));
    }
}
