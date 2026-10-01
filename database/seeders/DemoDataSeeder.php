<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetStock;
use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Employee;
use App\Models\ExecutiveTeam;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\WorkOrder;
use App\Support\DemoDatabase;
use Illuminate\Database\Seeder;

/**
 * Sample/fake records for the isolated demo database - never run against
 * the real production database. Only runs once: if the demo database
 * already has data (a real demo session has been using it), this is a
 * no-op so a routine deploy never wipes what a client was looking at.
 * Use `php artisan demo:reset` to intentionally wipe and reseed fresh.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Client::query()->exists()) {
            return;
        }

        $this->call(DepartmentSeeder::class);

        // Satisfies created_by/team_leader_id foreign keys on the sample
        // records below using the same mirrored row ProvisionDemoDatabase
        // keeps in sync - never touches the real users table, which
        // App\Models\User is pinned away from here on purpose.
        $creatorId = DemoDatabase::mirrorDemoUser();

        if (! $creatorId) {
            return;
        }

        $adminDept = Department::where('code', 'ADMIN')->first();

        $employee = Employee::create([
            'name' => 'Ravi Kumar', 'phone' => '9876500001', 'department_id' => $adminDept?->id,
            'designation' => 'Executive Team Leader', 'employment_type' => 'permanent',
            'status' => 'active', 'created_by' => $creatorId,
        ]);

        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-001', 'name' => 'Alpha Team', 'team_leader_id' => $creatorId,
            'is_active' => true, 'created_by' => $creatorId,
        ]);

        $clients = [
            ['name' => 'Sunrise Apartments', 'email' => 'contact@sunriseapartments.example', 'phone' => '9876543210', 'address' => 'Anna Nagar, Chennai'],
            ['name' => 'Greenfield Builders', 'email' => 'info@greenfieldbuilders.example', 'phone' => '9876543211', 'address' => 'Whitefield, Bengaluru'],
            ['name' => 'Coastal Retail Park', 'email' => 'projects@coastalretail.example', 'phone' => '9876543212', 'address' => 'ECR, Chennai'],
        ];

        $services = ['Villa Construction', 'Interior Renovation', 'Commercial Fit-out'];

        foreach ($clients as $i => $clientData) {
            $client = Client::create($clientData + ['is_active' => true, 'created_by' => $creatorId]);

            $enquiry = Enquiry::create([
                'client_id' => $client->id, 'service_type' => $services[$i], 'contact_name' => $clientData['name'],
                'contact_phone' => $clientData['phone'], 'contact_email' => $clientData['email'],
                'status' => 'converted', 'source' => 'website', 'created_by' => $creatorId,
            ]);

            $quotation = Quotation::create([
                'enquiry_id' => $enquiry->id, 'client_id' => $client->id,
                'subtotal' => 500000, 'tax_percent' => 18, 'tax_amount' => 90000, 'total_amount' => 590000,
                'status' => 'approved', 'approved_at' => now()->subDays(10), 'created_by' => $creatorId,
            ]);

            QuotationItem::create([
                'quotation_id' => $quotation->id, 'name' => $services[$i], 'description' => $services[$i],
                'unit' => 'lump sum', 'quantity' => 1, 'unit_price' => 500000, 'total' => 500000,
            ]);

            $site = Site::create([
                'client_id' => $client->id, 'quotation_id' => $quotation->id,
                'address' => $clientData['address'], 'status' => $i === 0 ? 'completed' : 'ongoing',
                'created_by' => $creatorId,
            ]);

            $workOrder = WorkOrder::create([
                'client_id' => $client->id, 'enquiry_id' => $enquiry->id, 'title' => $services[$i],
                'priority' => 'medium', 'type' => 'new', 'status' => $i === 0 ? 'completed' : 'in_progress',
                'created_by' => $creatorId,
            ]);

            Ticket::create([
                'work_order_id' => $workOrder->id, 'type' => 'client_change', 'priority' => 'medium',
                'title' => "Site visit follow-up - {$client->name}",
                'description' => 'Sample demo ticket for walkthrough purposes.',
                'raised_by_type' => 'client', 'raised_by_client_id' => $client->id,
                'status' => $i === 2 ? 'open' : 'resolved',
            ]);

            unset($site);
        }

        $assets = [
            ['name' => 'Tower Crane', 'category' => 'Heavy Machinery', 'available' => 2, 'damaged' => 0, 'missing' => 0],
            ['name' => 'Concrete Mixer', 'category' => 'Machinery', 'available' => 4, 'damaged' => 1, 'missing' => 0],
            ['name' => 'Scaffolding Set', 'category' => 'Structural', 'available' => 20, 'damaged' => 2, 'missing' => 1],
            ['name' => 'Safety Helmets', 'category' => 'Safety Gear', 'available' => 50, 'damaged' => 0, 'missing' => 3],
        ];

        foreach ($assets as $assetData) {
            $total = $assetData['available'] + $assetData['damaged'] + $assetData['missing'];

            $asset = Asset::create([
                'name' => $assetData['name'], 'category' => $assetData['category'], 'status' => 'available',
                'current_location' => 'company_store', 'quantity' => $total, 'created_by' => $creatorId,
            ]);

            if ($assetData['available'] > 0) {
                AssetStock::adjust($asset, 'company_store', null, 'available', $assetData['available']);
            }
            if ($assetData['damaged'] > 0) {
                AssetStock::adjust($asset, 'company_store', null, 'damaged', $assetData['damaged']);
            }
            if ($assetData['missing'] > 0) {
                AssetStock::adjust($asset, 'company_store', null, 'missing', $assetData['missing']);
            }
        }

        unset($employee, $team);
    }
}
