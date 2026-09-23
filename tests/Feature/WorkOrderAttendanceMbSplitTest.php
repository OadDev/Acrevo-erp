<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\MeasurementBook;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir asked for Attendance and M.Book, previously combined under one "M.Book"
 * tab/PDF/column, to be manageable, downloadable, and permission-controlled
 * separately.
 */
class WorkOrderAttendanceMbSplitTest extends TestCase
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

    private function workOrderWithMbAndAttendance(User $admin): WorkOrder
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
        $workOrder = WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);

        $mb = MeasurementBook::create([
            'work_order_id' => $workOrder->id, 'type' => 'actual', 'description' => 'Work done',
            'date' => now(), 'recorded_by' => $admin->id, 'status' => 'draft',
        ]);
        $mb->items()->create(['item_description' => 'Foundation excavation work', 'unit' => 'Sqft', 'quantity' => 100, 'rate' => 10, 'amount' => 1000]);

        $employee = Employee::create([
            'name' => 'Ravi Worker', 'employee_code' => 'EMP-'.uniqid(), 'department_id' => Department::first()->id,
            'designation' => 'Mason', 'employment_type' => 'daily_wage', 'status' => 'active', 'created_by' => $admin->id,
        ]);
        Attendance::create([
            'employee_id' => $employee->id, 'work_order_id' => $workOrder->id, 'date' => now(),
            'status' => 'present', 'check_in' => '09:00', 'check_out' => '17:00', 'hours_worked' => 8,
            'salary' => 800, 'marked_by' => $admin->id,
        ]);

        return $workOrder;
    }

    public function test_the_work_order_page_has_separate_measurement_book_and_attendance_tabs(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrderWithMbAndAttendance($admin);

        // Both tabs are independently manageable on the same page - the tab
        // bar lists them as two entries, and each has its own add form
        // (Alpine shows/hides between them client-side, so both are present
        // in the response; what matters is they're no longer one fused tab).
        $response = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Measurement Book')
            ->assertSee('Worker Attendance')
            ->assertSee('Foundation excavation work')
            ->assertSee('Ravi Worker')
            ->assertSee('Add Measurement Book')
            ->assertSee('Record Attendance');
    }

    public function test_measurement_book_and_attendance_can_be_downloaded_as_separate_pdfs(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrderWithMbAndAttendance($admin);

        $mbPdf = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/mb");
        $mbPdf->assertOk()->assertHeader('content-type', 'application/pdf');

        $attendancePdf = $this->actingAs($admin)->get("/work-orders/{$workOrder->id}/pdf/attendance");
        $attendancePdf->assertOk()->assertHeader('content-type', 'application/pdf');

        // Two distinct downloads, not one shared file.
        $this->assertNotSame($mbPdf->getContent(), $attendancePdf->getContent());
    }

    public function test_the_download_pdf_menu_lists_measurement_book_and_attendance_as_separate_entries(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrderWithMbAndAttendance($admin);

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}")
            ->assertOk()
            ->assertSee("/work-orders/{$workOrder->id}/pdf/mb", false)
            ->assertSee("/work-orders/{$workOrder->id}/pdf/attendance", false);
    }

    public function test_a_client_can_be_granted_attendance_access_without_measurement_book(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrderWithMbAndAttendance($admin);
        $client = $workOrder->client;

        $clientUser = User::create([
            'name' => $client->name, 'email' => $client->email,
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $clientUser->syncRoles(['Client']);
        ClientLogin::create(['client_id' => $client->id, 'user_id' => $clientUser->id]);

        $this->actingAs($admin)->put("/clients/{$client->id}/portal-permissions", [
            'visible_sections' => ['attendance'],
        ])->assertRedirect();

        $response = $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Worker Attendance')
            ->assertSee('Ravi Worker')
            ->assertDontSee('Measurement Book')
            ->assertDontSee('Foundation excavation work');
    }

    public function test_a_client_can_be_granted_measurement_book_access_without_attendance(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrderWithMbAndAttendance($admin);
        $client = $workOrder->client;

        $clientUser = User::create([
            'name' => $client->name, 'email' => $client->email,
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $clientUser->syncRoles(['Client']);
        ClientLogin::create(['client_id' => $client->id, 'user_id' => $clientUser->id]);

        $this->actingAs($admin)->put("/clients/{$client->id}/portal-permissions", [
            'visible_sections' => ['mb'],
        ])->assertRedirect();

        $response = $this->actingAs($clientUser)->get("/portal/work-orders/{$workOrder->id}");
        $response->assertOk()
            ->assertSee('Measurement Book')
            ->assertSee('Foundation excavation work')
            ->assertDontSee('Worker Attendance')
            ->assertDontSee('Ravi Worker');
    }

    public function test_the_client_permissions_form_lists_attendance_as_its_own_checkbox(): void
    {
        $admin = $this->admin();
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $clientUser = User::create([
            'name' => $client->name, 'email' => $client->email,
            'password' => bcrypt('password'), 'is_active' => true, 'must_change_password' => false,
        ]);
        $clientUser->syncRoles(['Client']);
        ClientLogin::create(['client_id' => $client->id, 'user_id' => $clientUser->id]);

        $this->actingAs($admin)->get("/clients/{$client->id}")
            ->assertOk()
            ->assertSee('name="visible_sections[]" value="attendance"', false)
            ->assertSee('name="visible_sections[]" value="mb"', false);
    }
}
