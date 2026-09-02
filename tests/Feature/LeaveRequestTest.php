<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
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
        $this->actingAs($admin)->post('/admin/users', [
            'name' => "{$role} Person", 'email' => strtolower(str_replace(' ', '', $role)).'+'.uniqid().'@example.com', 'role' => $role,
        ])->assertRedirect();

        return User::where('name', "{$role} Person")->firstOrFail();
    }

    public function test_a_staff_user_can_submit_and_see_their_own_leave_request(): void
    {
        $admin = $this->admin();
        $sales = $this->staffUser($admin, 'Sales');

        $response = $this->actingAs($sales)->post('/leave-requests', [
            'type' => 'casual', 'from_date' => now()->addDay()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(), 'reason' => 'Family function',
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('leave_requests', ['employee_id' => $sales->employee->id, 'status' => 'pending']);

        $this->actingAs($sales)->get('/leave-requests')->assertOk()->assertSee('Family function')->assertSee('My Leave Requests');
    }

    public function test_hr_can_review_and_approve_a_leave_request(): void
    {
        $admin = $this->admin();
        $hrReviewer = $this->staffUser($admin, 'HR');
        $qc = $this->staffUser($admin, 'QC Officer');

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $qc->employee->id, 'type' => 'sick',
            'from_date' => now(), 'to_date' => now(), 'status' => 'pending', 'created_by' => $qc->id,
        ]);

        $this->actingAs($hrReviewer)->get('/leave-requests')->assertOk()->assertSee('QC Officer Person')->assertSee('All Leave Requests');

        $this->actingAs($hrReviewer)->post("/leave-requests/{$leaveRequest->id}/review", [
            'status' => 'approved', 'review_remarks' => 'Approved, get well soon.',
        ])->assertRedirect();

        $fresh = $leaveRequest->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($hrReviewer->id, $fresh->reviewed_by);
    }

    public function test_a_non_reviewer_cannot_approve_or_reject(): void
    {
        $admin = $this->admin();
        $sales = $this->staffUser($admin, 'Sales');
        $qc = $this->staffUser($admin, 'QC Officer');

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $qc->employee->id, 'type' => 'sick',
            'from_date' => now(), 'to_date' => now(), 'status' => 'pending', 'created_by' => $qc->id,
        ]);

        $this->actingAs($sales)->get('/leave-requests')->assertOk()->assertDontSee('All Leave Requests');
        $this->actingAs($sales)->post("/leave-requests/{$leaveRequest->id}/review", ['status' => 'approved'])->assertForbidden();
    }

    public function test_an_employee_can_withdraw_a_pending_request_but_not_a_reviewed_one(): void
    {
        $admin = $this->admin();
        $finance = $this->staffUser($admin, 'Finance');

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $finance->employee->id, 'type' => 'casual',
            'from_date' => now(), 'to_date' => now(), 'status' => 'pending', 'created_by' => $finance->id,
        ]);

        $this->actingAs($finance)->delete("/leave-requests/{$leaveRequest->id}")->assertRedirect();
        $this->assertNull(LeaveRequest::find($leaveRequest->id));

        $reviewed = LeaveRequest::create([
            'employee_id' => $finance->employee->id, 'type' => 'casual',
            'from_date' => now(), 'to_date' => now(), 'status' => 'approved',
            'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'created_by' => $finance->id,
        ]);

        $this->actingAs($finance)->delete("/leave-requests/{$reviewed->id}")->assertStatus(422);
        $this->assertNotNull(LeaveRequest::find($reviewed->id));
    }

    public function test_hr_can_remove_any_leave_request_regardless_of_status(): void
    {
        $admin = $this->admin();
        $hrReviewer = $this->staffUser($admin, 'HR');
        $qc = $this->staffUser($admin, 'QC Officer');

        $pending = LeaveRequest::create([
            'employee_id' => $qc->employee->id, 'type' => 'sick',
            'from_date' => now(), 'to_date' => now(), 'status' => 'pending', 'created_by' => $qc->id,
        ]);
        $reviewed = LeaveRequest::create([
            'employee_id' => $qc->employee->id, 'type' => 'casual',
            'from_date' => now(), 'to_date' => now(), 'status' => 'approved',
            'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'created_by' => $qc->id,
        ]);

        $this->actingAs($hrReviewer)->delete("/leave-requests/{$pending->id}")->assertRedirect();
        $this->assertNull(LeaveRequest::find($pending->id));

        $this->actingAs($hrReviewer)->delete("/leave-requests/{$reviewed->id}")->assertRedirect();
        $this->assertNull(LeaveRequest::find($reviewed->id));
    }

    public function test_a_non_reviewer_cannot_remove_someone_elses_leave_request(): void
    {
        $admin = $this->admin();
        $sales = $this->staffUser($admin, 'Sales');
        $qc = $this->staffUser($admin, 'QC Officer');

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $qc->employee->id, 'type' => 'sick',
            'from_date' => now(), 'to_date' => now(), 'status' => 'pending', 'created_by' => $qc->id,
        ]);

        $this->actingAs($sales)->delete("/leave-requests/{$leaveRequest->id}")->assertForbidden();
        $this->assertNotNull(LeaveRequest::find($leaveRequest->id));
    }

    public function test_client_role_cannot_reach_leave_requests(): void
    {
        $admin = $this->admin();
        $client = User::create([
            'name' => 'Client User', 'email' => 'client+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $client->syncRoles(['Client']);

        $this->actingAs($client)->get('/leave-requests')->assertForbidden();
    }

    /**
     * Marketing and Management previously had no Employee record at all
     * (Employee::STAFF_ROLES never included them), so LeaveRequestController
     * ::store() aborted with "No worker/employee profile is linked to your
     * account" and the portal showed the "ask an Admin" message instead of
     * a form - the Leave Request menu link itself was already visible to
     * them (gated by tasks.view, which both roles hold), just unusable.
     */
    public function test_a_marketing_user_can_submit_and_see_their_own_leave_request(): void
    {
        $admin = $this->admin();
        $marketing = $this->staffUser($admin, 'Marketing');

        $this->assertNotNull($marketing->fresh()->employee, 'Marketing must get a linked Employee record on creation.');

        $this->actingAs($marketing)->get('/leave-requests')
            ->assertOk()
            ->assertDontSee('No worker/employee profile is linked');

        $response = $this->actingAs($marketing)->post('/leave-requests', [
            'type' => 'casual', 'from_date' => now()->addDay()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(), 'reason' => 'Marketing offsite',
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('leave_requests', ['employee_id' => $marketing->employee->id, 'status' => 'pending']);
        $this->actingAs($marketing)->get('/leave-requests')->assertOk()->assertSee('Marketing offsite');
    }

    public function test_a_management_user_can_submit_a_leave_request_and_see_it_reviewed_by_hr(): void
    {
        $admin = $this->admin();
        $management = $this->staffUser($admin, 'Management');
        $hrReviewer = $this->staffUser($admin, 'HR');

        $this->assertNotNull($management->fresh()->employee, 'Management must get a linked Employee record on creation.');

        $this->actingAs($management)->post('/leave-requests', [
            'type' => 'earned', 'from_date' => now()->addDay()->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(), 'reason' => 'Conference travel',
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::where('employee_id', $management->employee->id)->firstOrFail();

        // Visible to HR/Admin, who can act on it.
        $this->actingAs($hrReviewer)->get('/leave-requests')->assertOk()->assertSee('Conference travel');
        $this->actingAs($hrReviewer)->post("/leave-requests/{$leaveRequest->id}/review", [
            'status' => 'approved', 'review_remarks' => 'Approved for the conference.',
        ])->assertRedirect();

        // The Management user sees the outcome on their own list.
        $this->actingAs($management)->get('/leave-requests')
            ->assertOk()
            ->assertSee('Conference travel')
            ->assertSee('Approved for the conference.');
    }

    public function test_marketing_and_management_are_not_pulled_into_hr_staff_attendance_or_payroll_scope(): void
    {
        $admin = $this->admin();
        $marketing = $this->staffUser($admin, 'Marketing');
        $management = $this->staffUser($admin, 'Management');
        $sales = $this->staffUser($admin, 'Sales');

        // Employee::STAFF_ROLES (which HR Attendance / staff Payroll query
        // against) is deliberately untouched by this change - only the
        // separate EMPLOYEE_LINKED_ROLES list in UserController grew.
        $staffIds = \App\Models\Employee::staff()->pluck('id');

        $this->assertTrue($staffIds->contains($sales->employee->id));
        $this->assertFalse($staffIds->contains($marketing->employee->id));
        $this->assertFalse($staffIds->contains($management->employee->id));
    }

    /**
     * Auditor had the same gap as Marketing/Management: 'audit' => '*' gave
     * it full Audit access, but Employee::STAFF_ROLES never included it, so
     * $user->employee was null and LeaveRequestController::store() aborted.
     */
    public function test_an_auditor_can_submit_a_leave_request_and_see_it_reviewed_by_hr(): void
    {
        $admin = $this->admin();
        $auditor = $this->staffUser($admin, 'Auditor');
        $hrReviewer = $this->staffUser($admin, 'HR');

        $this->assertNotNull($auditor->fresh()->employee, 'Auditor must get a linked Employee record on creation.');

        $this->actingAs($auditor)->post('/leave-requests', [
            'type' => 'casual', 'from_date' => now()->addDay()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(), 'reason' => 'Personal work',
        ])->assertRedirect();

        $leaveRequest = LeaveRequest::where('employee_id', $auditor->employee->id)->firstOrFail();

        $this->actingAs($hrReviewer)->get('/leave-requests')->assertOk()->assertSee('Personal work');
        $this->actingAs($hrReviewer)->post("/leave-requests/{$leaveRequest->id}/review", [
            'status' => 'approved', 'review_remarks' => 'Approved.',
        ])->assertRedirect();

        $this->actingAs($auditor)->get('/leave-requests')
            ->assertOk()
            ->assertSee('Personal work')
            ->assertSee('Approved.');

        $this->assertFalse(\App\Models\Employee::staff()->pluck('id')->contains($auditor->employee->id), 'Auditor must not be pulled into the HR-Attendance staff scope.');
    }
}
