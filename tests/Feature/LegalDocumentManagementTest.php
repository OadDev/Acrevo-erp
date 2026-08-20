<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LegalDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function legalUser(): User
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::create([
            'name' => 'Legal Officer', 'email' => 'legal+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Legal']);

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

    public function test_a_legal_document_can_be_created_with_multiple_files_in_one_entry(): void
    {
        $user = $this->legalUser();

        $response = $this->actingAs($user)->post('/legal', [
            'title' => 'Site Lease Agreement',
            'type' => 'agreement',
            'reference_no' => 'REF-001',
            'files' => [
                UploadedFile::fake()->create('lease.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('signed-page.jpg'),
            ],
        ]);
        $response->assertRedirect();

        $document = LegalDocument::where('title', 'Site Lease Agreement')->firstOrFail();
        $this->assertSame(2, $document->media()->count());

        $this->actingAs($user)->get('/legal')->assertOk()->assertSee('lease.pdf')->assertSee('signed-page.jpg');
    }

    public function test_a_legal_document_can_be_edited_and_gains_more_files(): void
    {
        $user = $this->legalUser();
        $document = LegalDocument::create([
            'title' => 'Old Title', 'type' => 'contract', 'status' => 'active', 'created_by' => $user->id,
        ]);
        $document->addMedia(UploadedFile::fake()->create('original.pdf', 50))->toMediaCollection('files');

        $response = $this->actingAs($user)->put("/legal/{$document->id}", [
            'title' => 'Renamed Contract',
            'type' => 'contract',
            'status' => 'expired',
            'files' => [UploadedFile::fake()->create('addendum.pdf', 50)],
        ]);
        $response->assertRedirect('/legal');

        $fresh = $document->fresh();
        $this->assertSame('Renamed Contract', $fresh->title);
        $this->assertSame('expired', $fresh->status);
        $this->assertSame(2, $fresh->media()->count());
    }

    public function test_an_individual_uploaded_file_can_be_removed_without_deleting_the_document(): void
    {
        $user = $this->legalUser();
        $document = LegalDocument::create([
            'title' => 'Notice', 'type' => 'notice', 'status' => 'active', 'created_by' => $user->id,
        ]);
        $media = $document->addMedia(UploadedFile::fake()->create('notice.pdf', 50))->toMediaCollection('files');

        $this->actingAs($user)->delete("/legal/{$document->id}/media/{$media->id}")->assertRedirect();

        $this->assertNotNull($document->fresh());
        $this->assertSame(0, $document->fresh()->media()->count());
    }

    public function test_a_legal_document_and_its_files_can_be_deleted(): void
    {
        $user = $this->legalUser();
        $document = LegalDocument::create([
            'title' => 'To Delete', 'type' => 'other', 'status' => 'active', 'created_by' => $user->id,
        ]);
        $document->addMedia(UploadedFile::fake()->create('file.pdf', 50))->toMediaCollection('files');

        $this->actingAs($user)->delete("/legal/{$document->id}")->assertRedirect('/legal');

        $this->assertNull(LegalDocument::find($document->id));
        $this->assertSame(0, \Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_id', $document->id)->count());
    }

    public function test_a_view_only_legal_role_cannot_create_edit_or_delete(): void
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $viewer = $this->managementUser();
        $document = LegalDocument::create([
            'title' => 'Guarded Doc', 'type' => 'agreement', 'status' => 'active', 'created_by' => $viewer->id,
        ]);

        $this->actingAs($viewer)->get('/legal')->assertOk()->assertDontSee('New Legal Document');
        $this->actingAs($viewer)->post('/legal', ['title' => 'Blocked', 'type' => 'agreement'])->assertForbidden();
        $this->actingAs($viewer)->get("/legal/{$document->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->put("/legal/{$document->id}", ['title' => 'Blocked', 'type' => 'agreement', 'status' => 'active'])->assertForbidden();
        $this->actingAs($viewer)->delete("/legal/{$document->id}")->assertForbidden();
    }
}
