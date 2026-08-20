<?php

namespace Tests\Feature;

use App\Models\CompanyRecord;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CompanyRecordManagementTest extends TestCase
{
    use RefreshDatabase;

    private function managementUser(): User
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::create([
            'name' => 'Management User', 'email' => 'mgmt+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Management']);

        return $user;
    }

    public function test_a_company_record_can_be_created_with_multiple_files_in_one_entry(): void
    {
        $user = $this->managementUser();

        $this->actingAs($user)->post('/company-records', [
            'type' => 'gst', 'name' => 'GST Certificate',
            'files' => [
                UploadedFile::fake()->create('gst.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('gst-scan.jpg'),
            ],
        ])->assertRedirect('/company-records');

        $record = CompanyRecord::where('name', 'GST Certificate')->firstOrFail();
        $this->assertSame(2, $record->media()->count());

        $this->actingAs($user)->get('/company-records')->assertOk()->assertSee('2 file(s) attached');
    }

    public function test_a_company_record_can_be_edited_gains_more_files_and_an_individual_file_can_be_removed(): void
    {
        $user = $this->managementUser();
        $record = CompanyRecord::create(['type' => 'insurance', 'name' => 'Old Policy']);
        $media = $record->addMedia(UploadedFile::fake()->create('policy.pdf', 50))->toMediaCollection('files');

        $this->actingAs($user)->put("/company-records/{$record->id}", [
            'type' => 'insurance', 'name' => 'Renewed Policy',
            'files' => [UploadedFile::fake()->create('renewal.pdf', 50)],
        ])->assertRedirect('/company-records');

        $fresh = $record->fresh();
        $this->assertSame('Renewed Policy', $fresh->name);
        $this->assertSame(2, $fresh->media()->count());

        $this->actingAs($user)->delete("/company-records/{$record->id}/media/{$media->id}")->assertRedirect();
        $this->assertSame(1, $fresh->fresh()->media()->count());
    }

    public function test_a_company_record_and_its_files_can_be_deleted(): void
    {
        $user = $this->managementUser();
        $record = CompanyRecord::create(['type' => 'license', 'name' => 'Trade License']);
        $record->addMedia(UploadedFile::fake()->create('license.pdf', 50))->toMediaCollection('files');

        $this->actingAs($user)->delete("/company-records/{$record->id}")->assertRedirect();

        $this->assertNull(CompanyRecord::find($record->id));
        $this->assertSame(0, \Spatie\MediaLibrary\MediaCollections\Models\Media::where('model_id', $record->id)->where('model_type', CompanyRecord::class)->count());
    }
}
