<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * The Demo role was created narrow (view-only) back when it shared the
     * real production database. Now that Demo requests are switched onto a
     * wholly separate database (see SwitchDemoDatabaseConnection), it's
     * safe to grant it full create/edit/delete access to every business
     * module - none of it can reach production. users.*, roles.*, and
     * permissions.manage stay withheld: User/Role/Permission are pinned to
     * the main connection so auth keeps working, so those specific
     * permissions would let a demo session touch real accounts.
     */
    private array $fullAccessGroups = [
        'enquiries.view', 'enquiries.create', 'enquiries.edit', 'enquiries.delete',
        'site_visits.view', 'site_visits.create', 'site_visits.edit',
        'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.send', 'quotations.approve',
        'work_orders.view', 'work_orders.create', 'work_orders.edit', 'work_orders.cancel',
        'ongoing_sites.view', 'completed_sites.view',
        'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
        'attendance.view', 'attendance.manage',
        'payroll.view', 'payroll.manage',
        'benefits.manage',
        'executive_teams.view', 'executive_teams.manage',
        'worker_assignment.manage',
        'assigned_work.view', 'daily_checklist.manage', 'daily_progress.manage',
        'site_records.manage', 'media.upload', 'approval_requests.manage',
        'qc.view', 'qc.perform', 'qc.reports.view',
        'tickets.view', 'tickets.create', 'tickets.manage',
        'finance.view', 'finance.manage',
        'legal.view', 'legal.manage',
        'audit.view', 'audit.manage', 'audit.delete',
        'company_records.view', 'company_records.manage', 'client_records.view',
        'clients.manage',
        'assets.view', 'assets.create', 'assets.edit', 'assets.approve',
        'assets.delete', 'assets.restore', 'assets.update_status',
        'assets.view_history', 'assets.download_pdf', 'assets.view_missing',
        'movements.view', 'movements.create', 'movements.approve',
        'movements.edit', 'movements.delete', 'movements.download_pdf',
        'repairs.view', 'repairs.create', 'repairs.edit', 'repairs.delete',
        'repairs.update_status', 'repairs.download_pdf',
        'verifications.view', 'verifications.create', 'verifications.edit',
        'verifications.delete', 'verifications.download_pdf',
        'equipment_requests.view', 'equipment_requests.create', 'equipment_requests.edit',
        'equipment_requests.delete', 'equipment_requests.approve',
        'equipment_requests.receive', 'equipment_requests.download_pdf',
        'reports.view', 'reports.export',
        'tasks.view', 'tasks.create', 'tasks.manage',
        'subcontractors.view', 'subcontractors.manage',
        'subcontractor_sites.view', 'subcontractor_finance.view',
        'chat.access', 'conversations.clear',
        'work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details',
        'work_schedules.view_dates', 'work_schedules.view_progress', 'work_schedules.manage',
    ];

    private array $adminExtras = [
        'company_settings.manage', 'masters.manage', 'audit_logs.view',
        'activity_logs.view', 'notifications.manage', 'system_settings.manage',
    ];

    public function up(): void
    {
        $all = array_merge($this->fullAccessGroups, $this->adminExtras);

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $demo = Role::where('name', 'Demo')->where('guard_name', 'web')->first();

        if ($demo) {
            $demo->syncPermissions($all);
        }
    }

    public function down(): void
    {
        $demo = Role::where('name', 'Demo')->where('guard_name', 'web')->first();

        $demo?->syncPermissions([
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
        ]);
    }
};
