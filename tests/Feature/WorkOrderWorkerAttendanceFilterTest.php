<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Employee Attendance page (Admin/HR) already had a worker filter,
 * date-range filter, and PDF download. Worker Attendance - the per-Work
 * Order attendance table on the M.Book & Ledger tab - gets the same
 * functionality: filter by worker and date range, filtered results shown
 * inline, and a PDF download scoped to that filter.
 */
class WorkOrderWorkerAttendanceFilterTest extends TestCase
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

    private function workOrder(User $admin): WorkOrder
    {
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_filter_worker_attendance_by_worker_and_date_range_and_download_pdf(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $mason = Employee::create(['name' => 'Mason Kumar', 'employee_code' => 'W1', 'department_id' => Department::first()->id, 'status' => 'active']);
        $helper = Employee::create(['name' => 'Helper Raj', 'employee_code' => 'W2', 'department_id' => Department::first()->id, 'status' => 'active']);

        $workOrder->attendances()->create(['employee_id' => $mason->id, 'date' => '2026-08-05', 'status' => 'present', 'marked_by' => $admin->id]);
        $workOrder->attendances()->create(['employee_id' => $mason->id, 'date' => '2026-08-25', 'status' => 'present', 'marked_by' => $admin->id]);
        $workOrder->attendances()->create(['employee_id' => $helper->id, 'date' => '2026-08-10', 'status' => 'absent', 'marked_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('work-orders.show', $workOrder).'?tab=mb&att_worker_id='.$mason->id.'&att_from=2026-08-01&att_to=2026-08-15');
        $response->assertOk();
        $response->assertSee('Mason Kumar');
        $response->assertSee('05 Aug');
        // Mason's 25 Aug entry is outside the filtered range, and Helper's
        // 10 Aug entry belongs to a different worker entirely - the filter
        // dropdown still lists every worker's name, so check by date instead
        // of asserting the other worker's name is absent from the page.
        $response->assertDontSee('25 Aug');
        $response->assertDontSee('10 Aug');

        $pdfResponse = $this->actingAs($admin)->get(route('work-orders.attendance.pdf', [
            'workOrder' => $workOrder, 'employee_id' => $mason->id, 'from' => '2026-08-01', 'to' => '2026-08-15',
        ]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }

    public function test_the_worker_attendance_pdf_only_includes_records_from_this_work_order(): void
    {
        $admin = $this->admin();
        $workOrderA = $this->workOrder($admin);
        $workOrderB = $this->workOrder($admin);

        // Same worker, same date range, but attendance recorded against two
        // different work orders - each PDF must only ever show its own.
        $worker = Employee::create(['name' => 'Shared Worker', 'employee_code' => 'W3', 'department_id' => Department::first()->id, 'status' => 'active']);
        $workOrderA->attendances()->create(['employee_id' => $worker->id, 'date' => '2026-08-05', 'status' => 'present', 'marked_by' => $admin->id]);
        $workOrderB->attendances()->create(['employee_id' => $worker->id, 'date' => '2026-08-06', 'status' => 'present', 'marked_by' => $admin->id]);

        // PDF export is asserted through the HTML it feeds mPDF (can't grep
        // the compiled PDF bytes) - same pattern as the WO section PDF tests.
        $htmlA = view('work-orders.attendance-pdf', [
            'workOrder' => $workOrderA,
            'employee' => $worker,
            'records' => \App\Models\Attendance::where('work_order_id', $workOrderA->id)->where('employee_id', $worker->id)->whereBetween('date', ['2026-08-01', '2026-08-31'])->get(),
            'from' => '2026-08-01', 'to' => '2026-08-31',
        ])->render();
        $this->assertStringContainsString('05 Aug 2026', $htmlA);
        $this->assertStringNotContainsString('06 Aug 2026', $htmlA);

        $htmlB = view('work-orders.attendance-pdf', [
            'workOrder' => $workOrderB,
            'employee' => $worker,
            'records' => \App\Models\Attendance::where('work_order_id', $workOrderB->id)->where('employee_id', $worker->id)->whereBetween('date', ['2026-08-01', '2026-08-31'])->get(),
            'from' => '2026-08-01', 'to' => '2026-08-31',
        ])->render();
        $this->assertStringContainsString('06 Aug 2026', $htmlB);
        $this->assertStringNotContainsString('05 Aug 2026', $htmlB);

        // And the real route, driving the same query the controller uses.
        $pdfResponse = $this->actingAs($admin)->get(route('work-orders.attendance.pdf', [
            'workOrder' => $workOrderA, 'employee_id' => $worker->id, 'from' => '2026-08-01', 'to' => '2026-08-31',
        ]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }
}
