<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * A view-only role for prospective-client demos, sharing this live
     * production database. It sees the operational modules a demo needs to
     * show off (sales pipeline, sites, work schedule, equipment, QC,
     * tickets, reports) and nothing from HR/payroll, finance, legal, audit,
     * company records, or user/role administration, which can carry real
     * staff or company-confidential data.
     *
     * This alone is a belt: the BlockDemoWrites middleware is the
     * suspenders, rejecting every non-GET request for this role regardless
     * of which permissions it holds, so a future permission grant here
     * can't accidentally let a demo account write.
     */
    private array $demoPermissions = [
        'admin.dashboard.view', 'global_search.use',
        'enquiries.view', 'site_visits.view', 'quotations.view',
        'work_orders.view', 'ongoing_sites.view', 'completed_sites.view',
        'work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details',
        'work_schedules.view_dates', 'work_schedules.view_progress',
        'qc.view', 'qc.reports.view',
        'tickets.view',
        'assets.view', 'assets.view_history', 'assets.download_pdf', 'assets.view_missing',
        'movements.view', 'movements.download_pdf',
        'repairs.view', 'repairs.download_pdf',
        'verifications.view', 'verifications.download_pdf',
        'equipment_requests.view', 'equipment_requests.download_pdf',
        'reports.view', 'reports.export',
    ];

    public function up(): void
    {
        foreach ($this->demoPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $demoRole = Role::firstOrCreate(['name' => 'Demo', 'guard_name' => 'web']);
        $demoRole->syncPermissions($this->demoPermissions);

        $demoUser = User::firstOrCreate(
            ['email' => 'demo@geethanworks.in'],
            [
                'name' => 'Demo Account',
                'designation' => 'Demo',
                'password' => Hash::make('GeethanDemo@2026'),
                'email_verified_at' => now(),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        $demoUser->syncRoles(['Demo']);
    }

    public function down(): void
    {
        User::where('email', 'demo@geethanworks.in')->first()?->forceDelete();
        Role::where('name', 'Demo')->where('guard_name', 'web')->first()?->delete();
    }
};
