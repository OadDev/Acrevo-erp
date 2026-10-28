<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SiteDocumentUploadTest extends TestCase
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

    private function site(User $admin): Site
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        return Site::create(['client_id' => $client->id, 'address' => 'Addr', 'created_by' => $admin->id]);
    }

    public function test_a_legacy_xls_document_can_be_uploaded_to_a_site(): void
    {
        // Regression test: Site Documents' upload only allowed
        // jpg/jpeg/png/pdf/doc/docx - an XLS/XLSX file was rejected with a
        // validation error instead of uploading.
        $admin = $this->admin();
        $site = $this->site($admin);

        $file = UploadedFile::fake()->create('boq.xls', 50, 'application/vnd.ms-excel');

        $response = $this->actingAs($admin)->post("/sites/{$site->id}/documents", [
            'category' => 'other',
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $site->fresh()->media()->count());
        $this->assertSame('boq.xls', $site->fresh()->getFirstMedia('other')->file_name);
    }

    public function test_a_modern_xlsx_document_can_be_uploaded_to_a_site(): void
    {
        $admin = $this->admin();
        $site = $this->site($admin);

        $file = UploadedFile::fake()->create('rate-list.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->actingAs($admin)->post("/sites/{$site->id}/documents", [
            'category' => 'other',
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $site->fresh()->media()->count());
    }
}
