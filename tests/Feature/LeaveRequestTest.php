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
}
