<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuditManagementTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::create([
            'name' => 'Auditor', 'email' => 'auditor+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Auditor']);

        return $user;
    }

    private function managementUser(): User
    {
        $user = User::create([
            'name' => 'Management Viewer', 'email' => 'mgmt+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Management']);

        return $user;
    }

    private function workOrder(User $user): WorkOrder
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $user->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $user->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $user->id,
        ]);
    }

    public function test_an_audit_can_be_created_with_multiple_files_in_one_entry(): void
    {
        $user = $this->auditor();

        $this->actingAs($user)->post('/audits', [
            'type' => 'financial', 'title' => 'Q1 Financial Audit', 'audit_date' => now()->toDateString(),
            'files' => [
                UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('evidence.jpg'),
            ],
        ])->assertRedirect('/audits');

        $audit = Audit::where('title', 'Q1 Financial Audit')->firstOrFail();
        $this->assertSame(2, $audit->media()->count());

        $this->actingAs($user)->get('/audits')->assertOk()->assertSee('2 file(s)');
    }

    public function test_an_audit_can_be_edited_gains_more_files_and_an_individual_file_can_be_removed(): void
    {
        $user = $this->auditor();
        $audit = Audit::create([
            'type' => 'internal', 'title' => 'Old Title', 'auditor_id' => $user->id,
            'audit_date' => now(), 'status' => 'scheduled',
        ]);
        $media = $audit->addMedia(UploadedFile::fake()->create('draft.pdf', 50))->toMediaCollection('files');

        $this->actingAs($user)->put("/audits/{$audit->id}", [
            'type' => 'internal', 'title' => 'Finalized Title', 'audit_date' => now()->toDateString(), 'status' => 'completed',
            'files' => [UploadedFile::fake()->create('final.pdf', 50)],
        ])->assertRedirect('/audits');

        $fresh = $audit->fresh();
        $this->assertSame('Finalized Title', $fresh->title);
        $this->assertSame('completed', $fresh->status);
        $this->assertSame(2, $fresh->media()->count());

        $this->actingAs($user)->delete("/audits/{$audit->id}/media/{$media->id}")->assertRedirect();
        $this->assertSame(1, $fresh->fresh()->media()->count());
    }

    public function test_an_audit_and_its_files_can_be_deleted(): void
    {
        $user = $this->auditor();
        $audit = Audit::create([
            'type' => 'project', 'title' => 'To Delete', 'auditor_id' => $user->id,
            'audit_date' => now(), 'status' => 'completed',
        ]);
        $audit->addMedia(UploadedFile::fake()->create('file.pdf', 50))->toMediaCollection('files');

        $this->actingAs($user)->delete("/audits/{$audit->id}")->assertRedirect('/audits');

        $this->assertNull(Audit::find($audit->id));
        $this->assertSame(0, \Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_id', $audit->id)->where('model_type', Audit::class)->count());
    }

    public function test_the_audit_index_can_be_filtered_by_type_status_and_work_order(): void
    {
        $user = $this->auditor();
        $workOrder = $this->workOrder($user);

        Audit::create(['type' => 'financial', 'title' => 'Financial One', 'auditor_id' => $user->id, 'audit_date' => now(), 'status' => 'completed', 'work_order_id' => $workOrder->id]);
        Audit::create(['type' => 'internal', 'title' => 'Internal One', 'auditor_id' => $user->id, 'audit_date' => now(), 'status' => 'scheduled']);

        $response = $this->actingAs($user)->get('/audits?type=financial');
        $response->assertOk()->assertSee('Financial One')->assertDontSee('Internal One');

        $response = $this->actingAs($user)->get('/audits?status=scheduled');
        $response->assertOk()->assertSee('Internal One')->assertDontSee('Financial One');

        $response = $this->actingAs($user)->get("/audits?work_order_id={$workOrder->id}");
        $response->assertOk()->assertSee('Financial One')->assertDontSee('Internal One');
    }

    public function test_a_view_only_role_cannot_create_edit_or_delete_audits(): void
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $viewer = $this->managementUser();
        $audit = Audit::create([
            'type' => 'internal', 'title' => 'Guarded', 'auditor_id' => $viewer->id,
            'audit_date' => now(), 'status' => 'completed',
        ]);

        $this->actingAs($viewer)->get('/audits')->assertOk()->assertDontSee('New Audit');
        $this->actingAs($viewer)->get('/audits/create')->assertForbidden();
        $this->actingAs($viewer)->post('/audits', ['type' => 'internal', 'title' => 'Blocked', 'audit_date' => now()->toDateString()])->assertForbidden();
        $this->actingAs($viewer)->get("/audits/{$audit->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->delete("/audits/{$audit->id}")->assertForbidden();
    }
}
