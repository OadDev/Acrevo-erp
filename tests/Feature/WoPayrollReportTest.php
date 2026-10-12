<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir asked for WO Workers Payroll to stop requiring every work order to
 * be opened separately to filter attendance and add up a worker's salary
 * by hand. This covers the new Attendance & Salary Report: filters (Work
 * Order / worker / date range), a combined cross-WO report per worker,
 * automatic salary totals (all workers, one worker with its WO-wise
 * breakdown, or one WO's worker-wise totals), and PDF export of whatever
 * is currently filtered.
 */
class WoPayrollReportTest extends TestCase
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

    private function workOrder(User $admin, string $title): WorkOrder
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => $title, 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    private function worker(string $name): Employee
    {
        return Employee::create([
            'employee_code' => 'EMP-'.uniqid(), 'name' => $name, 'status' => 'active',
            'department_id' => Department::first()->id, 'employment_type' => 'daily_wage',
        ]);
    }

    public function test_a_worker_across_two_work_orders_shows_a_combined_report_with_a_wo_wise_breakdown(): void
    {
        $admin = $this->admin();
        $woOne = $this->workOrder($admin, 'Site One');
        $woTwo = $this->workOrder($admin, 'Site Two');
        $worker = $this->worker('Ravi Worker');

        Attendance::create([
            'employee_id' => $worker->id, 'work_order_id' => $woOne->id, 'date' => now()->startOfMonth()->addDays(2),
            'status' => 'present', 'salary' => 600, 'marked_by' => $admin->id,
        ]);
        Attendance::create([
            'employee_id' => $worker->id, 'work_order_id' => $woTwo->id, 'date' => now()->startOfMonth()->addDays(5),
            'status' => 'present', 'salary' => 700, 'marked_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/payroll');

        $response->assertOk()->assertSee('Ravi Worker');

        $row = $response->viewData('reportByWorker')->firstWhere('employee.name', 'Ravi Worker');
        $this->assertNotNull($row);
        $this->assertSame(1300.0, $row->totalSalary);
        $this->assertCount(2, $row->byWorkOrder);
        $this->assertSame(600.0, $row->byWorkOrder->firstWhere('workOrder.id', $woOne->id)->totalSalary);
        $this->assertSame(700.0, $row->byWorkOrder->firstWhere('workOrder.id', $woTwo->id)->totalSalary);
    }

    public function test_filtering_by_work_order_shows_only_that_work_orders_workers_and_total(): void
    {
        $admin = $this->admin();
        $woOne = $this->workOrder($admin, 'Site One');
        $woTwo = $this->workOrder($admin, 'Site Two');
        $ravi = $this->worker('Ravi Worker');
        $sita = $this->worker('Sita Worker');

        Attendance::create([
            'employee_id' => $ravi->id, 'work_order_id' => $woOne->id, 'date' => now()->startOfMonth()->addDays(2),
            'status' => 'present', 'salary' => 600, 'marked_by' => $admin->id,
        ]);
        Attendance::create([
            'employee_id' => $sita->id, 'work_order_id' => $woTwo->id, 'date' => now()->startOfMonth()->addDays(3),
            'status' => 'present', 'salary' => 500, 'marked_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/payroll?work_order_id='.$woOne->id);

        $response->assertOk()
            ->assertSee('Ravi Worker')
            ->assertViewHas('reportGrandTotalSalary', 600.0);

        // "Sita Worker" still appears on the page in the "All Workers"
        // filter dropdown - what matters is she's not in the report
        // table/total for this Work Order.
        $report = $response->viewData('reportByWorker');
        $this->assertCount(1, $report);
        $this->assertNull($report->firstWhere('employee.name', 'Sita Worker'));
    }

    public function test_filtering_by_a_specific_worker_shows_their_total_for_the_date_range(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin, 'Site One');
        $ravi = $this->worker('Ravi Worker');
        $sita = $this->worker('Sita Worker');

        Attendance::create([
            'employee_id' => $ravi->id, 'work_order_id' => $wo->id, 'date' => now()->startOfMonth()->addDays(1),
            'status' => 'present', 'salary' => 400, 'marked_by' => $admin->id,
        ]);
        Attendance::create([
            'employee_id' => $sita->id, 'work_order_id' => $wo->id, 'date' => now()->startOfMonth()->addDays(1),
            'status' => 'present', 'salary' => 900, 'marked_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/payroll?employee_id='.$ravi->id);

        $response->assertOk()
            ->assertSee('Ravi Worker')
            ->assertViewHas('reportGrandTotalSalary', 400.0);

        $report = $response->viewData('reportByWorker');
        $this->assertCount(1, $report);
        $this->assertNull($report->firstWhere('employee.name', 'Sita Worker'));
    }

    public function test_a_custom_date_range_outside_the_selected_month_narrows_the_totals(): void
    {
        $admin = $this->admin();
        $wo = $this->workOrder($admin, 'Site One');
        $worker = $this->worker('Ravi Worker');

        Attendance::create([
            'employee_id' => $worker->id, 'work_order_id' => $wo->id, 'date' => now()->startOfMonth()->addDays(2),
            'status' => 'present', 'salary' => 300, 'marked_by' => $admin->id,
        ]);
        Attendance::create([
            'employee_id' => $worker->id, 'work_order_id' => $wo->id, 'date' => now()->startOfMonth()->addDays(20),
            'status' => 'present', 'salary' => 500, 'marked_by' => $admin->id,
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->startOfMonth()->addDays(10)->toDateString();

        $response = $this->actingAs($admin)->get("/payroll?from={$from}&to={$to}");

        $response->assertOk()->assertViewHas('reportGrandTotalSalary', 300.0);
    }

    public function test_the_filtered_report_can_be_downloaded_as_a_single_worker_pdf_or_an_all_workers_pdf(): void
    {
        $admin = $this->admin();
        $woOne = $this->workOrder($admin, 'Site One');
        $woTwo = $this->workOrder($admin, 'Site Two');
        $worker = $this->worker('Ravi Worker');

        Attendance::create([
            'employee_id' => $worker->id, 'work_order_id' => $woOne->id, 'date' => now()->startOfMonth()->addDays(2),
            'status' => 'present', 'salary' => 600, 'marked_by' => $admin->id,
        ]);
        Attendance::create([
            'employee_id' => $worker->id, 'work_order_id' => $woTwo->id, 'date' => now()->startOfMonth()->addDays(5),
            'status' => 'present', 'salary' => 700, 'marked_by' => $admin->id,
        ]);

        $allWorkersPdf = $this->actingAs($admin)->get('/payroll/report/pdf');
        $allWorkersPdf->assertOk()->assertHeader('content-type', 'application/pdf');

        $singleWorkerPdf = $this->actingAs($admin)->get('/payroll/report/pdf?employee_id='.$worker->id);
        $singleWorkerPdf->assertOk()->assertHeader('content-type', 'application/pdf');

        // Two distinct downloads, not one shared file - the single-worker
        // one carries the WO-wise breakdown the all-workers one doesn't.
        $this->assertNotSame($allWorkersPdf->getContent(), $singleWorkerPdf->getContent());
    }

    public function test_a_worker_with_no_attendance_in_the_filtered_range_does_not_appear(): void
    {
        $admin = $this->admin();
        $this->worker('Idle Worker');

        $response = $this->actingAs($admin)->get('/payroll');

        // "Idle Worker" still appears in the "All Workers" filter dropdown -
        // what matters is she contributes nothing to the report/totals.
        $response->assertOk();
        $this->assertTrue($response->viewData('reportByWorker')->isEmpty());
        $this->assertNull($response->viewData('reportByWorker')->firstWhere('employee.name', 'Idle Worker'));
    }
}
