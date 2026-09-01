<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Quotations previously had no way to attach supporting documents (BOQ,
 * drawings, specs, T&C). This adds multi-file upload/remove on the internal
 * side and view/download access for the client reviewing the quotation in
 * the portal - via Spatie MediaLibrary, mirroring the Ticket/Approval
 * Request attachment pattern already used elsewhere in the app.
 */
class QuotationAttachmentTest extends TestCase
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

    private function sales(): User
    {
        $sales = User::create([
            'name' => 'Sales Guy', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);

        return $sales;
    }

    private function quotation(User $admin, string $status = 'draft'): Quotation
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return Quotation::create(['enquiry_id' => $enquiry->id, 'client_id' => $client->id, 'status' => $status, 'total_amount' => 100, 'created_by' => $admin->id]);
    }

    private function clientLogin(User $admin, Quotation $quotation): User
    {
        $this->actingAs($admin)->post("/clients/{$quotation->client_id}/portal-access")->assertRedirect();

        return ClientLogin::where('client_id', $quotation->client_id)->firstOrFail()->user;
    }

    public function test_sales_can_upload_multiple_attachments_to_a_quotation(): void
    {
        $admin = $this->admin();
        $sales = $this->sales();
        $quotation = $this->quotation($admin);

        $response = $this->actingAs($sales)->post("/quotations/{$quotation->id}/media", [
            'files' => [
                UploadedFile::fake()->create('boq.xlsx', 200, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                UploadedFile::fake()->create('drawing.pdf', 300, 'application/pdf'),
                UploadedFile::fake()->image('elevation.jpg'),
            ],
        ]);
        $response->assertRedirect();

        $quotation->refresh();
        $this->assertCount(3, $quotation->media);
        $this->assertTrue($quotation->media->contains('file_name', 'boq.xlsx'));

        $this->actingAs($admin)->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('boq.xlsx')
            ->assertSee('drawing.pdf')
            ->assertSee('elevation.jpg');
    }

    public function test_admin_can_remove_a_quotation_attachment(): void
    {
        $admin = $this->admin();
        $quotation = $this->quotation($admin);
        $media = $quotation->addMedia(UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf'))->toMediaCollection('attachments');

        $this->actingAs($admin)->delete(route('quotations.media.destroy', [$quotation, $media]))->assertRedirect();

        $this->assertCount(0, $quotation->fresh()->media);
    }

    public function test_non_admin_cannot_remove_a_quotation_attachment(): void
    {
        $admin = $this->admin();
        $sales = $this->sales();
        $quotation = $this->quotation($admin);
        $media = $quotation->addMedia(UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf'))->toMediaCollection('attachments');

        $this->actingAs($sales)->delete(route('quotations.media.destroy', [$quotation, $media]))->assertForbidden();

        $this->assertCount(1, $quotation->fresh()->media);
    }

    public function test_client_can_view_and_download_quotation_attachments_once_sent(): void
    {
        $admin = $this->admin();
        $quotation = $this->quotation($admin, 'sent');
        $quotation->addMedia(UploadedFile::fake()->create('boq.pdf', 100, 'application/pdf'))->toMediaCollection('attachments');
        $clientUser = $this->clientLogin($admin, $quotation);

        $this->actingAs($clientUser)->get(route('portal.quotations.show', $quotation))
            ->assertOk()
            ->assertSee('boq.pdf');

        $this->actingAs($clientUser)->get(route('portal.quotations.pdf', $quotation))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_client_cannot_download_another_clients_quotation_pdf(): void
    {
        $admin = $this->admin();
        $quotationA = $this->quotation($admin, 'sent');
        $quotationB = $this->quotation($admin, 'sent');
        $clientAUser = $this->clientLogin($admin, $quotationA);

        $this->actingAs($clientAUser)->get(route('portal.quotations.pdf', $quotationB))->assertForbidden();
    }

    public function test_quotation_pdf_lists_attachments(): void
    {
        $admin = $this->admin();
        $quotation = $this->quotation($admin);
        $quotation->addMedia(UploadedFile::fake()->create('terms.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->toMediaCollection('attachments');

        $html = view('quotations.pdf', ['quotation' => $quotation->fresh(['items', 'client', 'enquiry', 'media'])])->render();

        $this->assertStringContainsString('terms.docx', $html);
    }
}
