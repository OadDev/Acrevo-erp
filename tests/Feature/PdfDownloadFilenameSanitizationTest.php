<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "S/o", "D/o", and "W/o" are standard in Indian formal names (e.g. "Ravi
 * Kumar S/o Krishnan"), so an employee's name can legitimately contain a
 * "/". Every PDF download whose filename is built from that name (payroll,
 * self-service payroll, HR attendance) fed the raw name straight into the
 * Content-Disposition header - Symfony's HeaderUtils::makeDisposition()
 * throws an uncaught InvalidArgumentException the moment a "/" or "\"
 * appears in that filename, 500ing the whole download. Fixed centrally in
 * PdfDocument::download() (sanitizes every filename before use), so this
 * covers it at two of its real call sites rather than every one individually.
 */
class PdfDownloadFilenameSanitizationTest extends TestCase
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

    private function staffUser(User $admin, string $role, string $name): User
    {
        $this->actingAs($admin)->post('/admin/users', [
            'name' => $name, 'email' => strtolower(str_replace([' ', '/'], '', $role)).'+'.uniqid().'@example.com', 'role' => $role,
        ])->assertRedirect();

        return User::where('name', $name)->firstOrFail();
    }

    public function test_payroll_pdf_downloads_for_an_employee_whose_name_contains_a_slash(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR', 'Ravi Kumar S/o Krishnan');

        $this->actingAs($admin)->post('/payroll', [
            'employee_id' => $hr->employee->id, 'month' => now()->month, 'year' => now()->year,
            'basic_salary' => '20000',
        ])->assertRedirect();

        $payroll = Payroll::where('employee_id', $hr->employee->id)->firstOrFail();

        $response = $this->actingAs($admin)->get("/payroll/{$payroll->id}/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('/', $response->headers->get('Content-Disposition'));
    }

    public function test_hr_attendance_pdf_downloads_for_an_employee_whose_name_contains_a_slash(): void
    {
        $admin = $this->admin();
        $hr = $this->staffUser($admin, 'HR', 'Priya D/o Suresh');

        Attendance::create([
            'employee_id' => $hr->employee->id, 'date' => now(), 'status' => 'present',
            'salary' => 800, 'advance' => 0, 'marked_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/attendance/pdf?employee_id='.$hr->employee->id);
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
