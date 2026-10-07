<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryChallan;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sir reported the company logo uploaded on the Company tab wasn't
 * appearing on the Delivery Challan PDF - the template only ever printed
 * the literal word "Logo" as a placeholder and never actually read
 * Company::logo_path at all.
 */
class DeliveryChallanPdfTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DepartmentSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $admin->syncRoles(['Admin']);

        return $admin;
    }

    private function deliveryChallan(Company $company, User $admin): DeliveryChallan
    {
        $client = Client::create(['name' => 'C', 'email' => 'c@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        $deliveryChallan = DeliveryChallan::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'challan_no' => 'DC-1',
            'challan_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $admin->id,
        ]);
        $deliveryChallan->items()->create(['name' => 'Steel Sheets', 'unit' => 'Nos', 'quantity' => 10, 'sort_order' => 0]);

        return $deliveryChallan;
    }

    public function test_the_delivery_challan_pdf_embeds_the_companys_uploaded_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $logo = UploadedFile::fake()->image('logo.png', 120, 60);
        $logoPath = $logo->store('company-logos', 'public');

        $company = Company::create([
            'name' => 'Acme Builders', 'code' => 'ACME', 'logo_path' => $logoPath,
            'tax_regime' => 'gst', 'currency' => 'INR', 'is_active' => true,
        ]);
        $deliveryChallan = $this->deliveryChallan($company, $admin);

        // Same pattern as other PDF exports in this codebase - asserted
        // through the HTML mPDF renders from, since the compiled PDF bytes
        // can't be grepped directly.
        $html = view('delivery-challans.pdf', compact('deliveryChallan'))->render();
        $this->assertStringContainsString('<img src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('>Logo<', $html);

        // And the real route, driving the same controller action/view the
        // app actually serves.
        $response = $this->actingAs($admin)->get(route('delivery-challans.pdf', $deliveryChallan));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_the_delivery_challan_pdf_falls_back_to_a_placeholder_when_the_company_has_no_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $company = Company::create([
            'name' => 'No Logo Co', 'code' => 'NOLOGO',
            'tax_regime' => 'gst', 'currency' => 'INR', 'is_active' => true,
        ]);
        $deliveryChallan = $this->deliveryChallan($company, $admin);

        $html = view('delivery-challans.pdf', compact('deliveryChallan'))->render();
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('>Logo<', $html);

        $response = $this->actingAs($admin)->get(route('delivery-challans.pdf', $deliveryChallan));
        $response->assertOk();
    }
}
