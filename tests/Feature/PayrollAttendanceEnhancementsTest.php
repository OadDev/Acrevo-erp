<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAttendanceEnhancementsTest extends TestCase
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

    public function test_a_client_can_be_removed_from_the_client_list(): void
    {
        $admin = $this->admin();
        $client = Client::create([
            'client_code' => 'CLI-'.uniqid(), 'name' => 'Removable Client', 'phone' => '9999999999', 'type' => 'individual',
        ]);

        $this->actingAs($admin)->get('/clients')->assertOk()->assertSee('Removable Client');

        $this->actingAs($admin)->delete("/clients/{$client->id}")->assertRedirect('/clients');

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->actingAs($admin)->get('/clients')->assertOk()->assertDontSee('Removable Client');
    }

    public function test_removing_a_settlement_record_recalculates_paid_amount_and_status(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');

        $payroll = Payroll::create([
            'employee_id' => $hr->employee->id, 'month' => now()->month, 'year' => now()->year,
            'basic_salary' => 10000, 'net_salary' => 10000, 'paid_amount' => 10000, 'status' => 'paid',
            'paid_at' => now(), 'processed_by' => $admin->id,
        ]);

        $payment1 = PayrollPayment::create(['payroll_id' => $payroll->id, 'amount' => 6000, 'paid_on' => now()->subDays(5), 'paid_by' => $admin->id]);
        PayrollPayment::create(['payroll_id' => $payroll->id, 'amount' => 4000, 'paid_on' => now(), 'paid_by' => $admin->id]);

        $this->actingAs($admin)->delete("/payroll/{$payroll->id}/payments/{$payment1->id}")->assertRedirect();

        $this->assertDatabaseMissing('payroll_payments', ['id' => $payment1->id]);

        $payroll->refresh();
        $this->assertEquals(4000, (float) $payroll->paid_amount);
        $this->assertEquals('partial', $payroll->status);
    }

    public function test_a_staff_employee_can_view_their_own_attendance_via_self_service(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');

        Attendance::create([
            'employee_id' => $hr->employee->id, 'date' => now(), 'status' => 'present',
            'work_details' => 'Filed reports', 'salary' => 800, 'advance' => 0, 'marked_by' => $admin->id,
        ]);

        $this->actingAs($hr)->get('/my-attendance')
            ->assertOk()->assertSee('My Attendance')->assertSee('Filed reports');
    }

    public function test_admin_can_filter_attendance_by_employee_and_date_range_and_download_pdf(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');
        $finance = $this->staffUser($admin, 'Finance');

        Attendance::create([
            'employee_id' => $hr->employee->id, 'date' => now(), 'status' => 'present',
            'work_details' => 'Filed reports', 'salary' => 800, 'advance' => 0, 'marked_by' => $admin->id,
        ]);
        Attendance::create([
            'employee_id' => $finance->employee->id, 'date' => now(), 'status' => 'present',
            'work_details' => 'Reconciled accounts', 'salary' => 900, 'advance' => 0, 'marked_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/attendance?employee_id='.$hr->employee->id
            .'&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString());
        $response->assertOk()->assertSee('Filed reports')->assertDontSee('Reconciled accounts');

        $pdfResponse = $this->actingAs($admin)->get('/attendance/pdf?employee_id='.$hr->employee->id
            .'&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString());
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }

    public function test_an_admin_can_remove_a_completed_payroll_record_and_its_settlement_history(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');

        $payroll = Payroll::create([
            'employee_id' => $hr->employee->id, 'month' => now()->month, 'year' => now()->year,
            'basic_salary' => 10000, 'net_salary' => 10000, 'paid_amount' => 10000, 'status' => 'paid',
            'paid_at' => now(), 'processed_by' => $admin->id,
        ]);
        $payment = PayrollPayment::create(['payroll_id' => $payroll->id, 'amount' => 10000, 'paid_on' => now(), 'paid_by' => $admin->id]);

        $this->actingAs($admin)->delete("/payroll/{$payroll->id}")->assertRedirect();

        $this->assertDatabaseMissing('payrolls', ['id' => $payroll->id]);
        $this->assertDatabaseMissing('payroll_payments', ['id' => $payment->id]);
    }

    public function test_a_non_admin_cannot_remove_a_payroll_record(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');
        $finance = $this->staffUser($admin, 'Finance');

        $payroll = Payroll::create([
            'employee_id' => $hr->employee->id, 'month' => now()->month, 'year' => now()->year,
            'basic_salary' => 10000, 'net_salary' => 10000, 'paid_amount' => 10000, 'status' => 'paid',
            'paid_at' => now(), 'processed_by' => $admin->id,
        ]);

        $this->actingAs($finance)->delete("/payroll/{$payroll->id}")->assertForbidden();

        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id]);
    }

    public function test_generating_payroll_from_attendance_accepts_manual_allowance_overtime_incentive_deductions_and_other_payments(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');

        Attendance::create([
            'employee_id' => $hr->employee->id, 'date' => now(), 'status' => 'present',
            'salary' => 1000, 'advance' => 50, 'marked_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post('/payroll/generate-from-attendance', [
            'employee_id' => $hr->employee->id, 'month' => now()->month, 'year' => now()->year,
            'allowances' => 200, 'overtime_amount' => 150, 'incentive' => 300, 'other_payments' => 100, 'deductions' => 75,
        ])->assertRedirect();

        $payroll = Payroll::where('employee_id', $hr->employee->id)
            ->where('month', now()->month)->where('year', now()->year)->firstOrFail();

        $this->assertEquals(1000, $payroll->basic_salary);
        $this->assertEquals(200, $payroll->allowances);
        $this->assertEquals(150, $payroll->overtime_amount);
        $this->assertEquals(300, $payroll->incentive);
        $this->assertEquals(100, $payroll->other_payments);
        $this->assertEquals(75, $payroll->deductions);
        // 1000 + 200 + 150 + 300 + 100 - 75 (deductions) - 50 (advance)
        $this->assertEquals(1625, $payroll->net_salary);

        $pdfResponse = $this->actingAs($admin)->get("/payroll/{$payroll->id}/pdf");
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }

    /**
     * The "Process Payroll" card (PayrollController::store(), route
     * payroll.store) 500'd with "NOT NULL constraint failed: payrolls
     * .allowances" whenever a real user left an optional field (allowances,
     * deductions, advance_deducted, overtime, incentive, other payments)
     * blank: Laravel's ConvertEmptyStringsToNull middleware turns a blank
     * input into null, 'nullable' validation accepts it, but the payrolls
     * table columns are default(0) and NOT nullable, so the null insert
     * violated the constraint. store() now coerces every optional field to
     * 0 before the write, matching generateFromAttendance()'s style.
     */
    public function test_process_payroll_store_accepts_blank_optional_fields_and_applies_deductions(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR');

        $response = $this->actingAs($admin)->post('/payroll', [
            'employee_id' => $hr->employee->id, 'month' => now()->month, 'year' => now()->year,
            'basic_salary' => '20000',
            'allowances' => '', 'overtime_amount' => '', 'incentive' => '', 'other_payments' => '',
            'deductions' => '1500', 'advance_deducted' => '',
        ]);
        $response->assertRedirect();

        $payroll = Payroll::where('employee_id', $hr->employee->id)
            ->where('month', now()->month)->where('year', now()->year)->firstOrFail();

        $this->assertEquals(20000, $payroll->basic_salary);
        $this->assertEquals(0, $payroll->allowances);
        $this->assertEquals(1500, $payroll->deductions);
        // 20000 - 1500 (deductions), every other optional field blank -> 0
        $this->assertEquals(18500, $payroll->net_salary);

        $pdfResponse = $this->actingAs($admin)->get("/payroll/{$payroll->id}/pdf");
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }
}
