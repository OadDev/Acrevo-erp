<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
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

    public function test_admin_can_add_a_department_and_it_becomes_selectable_for_users(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/departments', [
            'name' => 'Operations', 'code' => 'OPS', 'description' => 'Site operations team',
        ])->assertRedirect();

        $department = Department::where('code', 'OPS')->firstOrFail();
        $this->assertSame('Operations', $department->name);

        $this->actingAs($admin)->get('/admin/departments')->assertOk()->assertSee('Operations');
        $this->actingAs($admin)->get('/admin/users/create')->assertOk()->assertSee('Operations');
    }

    public function test_admin_can_edit_a_department(): void
    {
        $admin = $this->admin();
        $department = Department::where('code', 'OPS')->first() ?? Department::create(['name' => 'Old Name', 'code' => 'OLD', 'is_active' => true]);

        $this->actingAs($admin)->put("/admin/departments/{$department->id}", [
            'name' => 'New Name', 'code' => $department->code, 'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('New Name', $department->fresh()->name);
    }

    public function test_a_department_with_users_or_workers_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $department = $admin->department;

        $response = $this->actingAs($admin)->delete("/admin/departments/{$department->id}");
        $response->assertStatus(422);
        $this->assertNotNull(Department::find($department->id));
    }

    public function test_an_unused_department_can_be_deleted(): void
    {
        $admin = $this->admin();
        $department = Department::create(['name' => 'Temp Dept', 'code' => 'TEMP', 'is_active' => true]);

        $this->actingAs($admin)->delete("/admin/departments/{$department->id}")->assertRedirect();
        $this->assertNull(Department::find($department->id));
    }

    public function test_a_non_admin_cannot_manage_departments(): void
    {
        $admin = $this->admin();
        $hr = User::create([
            'name' => 'HR User', 'email' => 'hr+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $hr->syncRoles(['HR']);

        $this->actingAs($hr)->get('/admin/departments')->assertForbidden();
        $this->actingAs($hr)->post('/admin/departments', ['name' => 'X', 'code' => 'X'])->assertForbidden();
    }
}
