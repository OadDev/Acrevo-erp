<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Every screen, button, and report in the ERP is gated behind one of
     * these permission strings, grouped by module for readability.
     */
    private array $permissionGroups = [
        'admin' => [
            'admin.dashboard.view',
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'permissions.manage',
            'company_settings.manage',
            'masters.manage',
            'audit_logs.view',
            'activity_logs.view',
            'notifications.manage',
            'global_search.use',
            'system_settings.manage',
        ],
        'sales' => [
            'enquiries.view', 'enquiries.create', 'enquiries.edit', 'enquiries.delete',
            'site_visits.view', 'site_visits.create', 'site_visits.edit',
            'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.send', 'quotations.approve',
            'work_orders.view', 'work_orders.create', 'work_orders.edit', 'work_orders.cancel',
            'ongoing_sites.view', 'completed_sites.view',
        ],
        'hr' => [
            'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
            'attendance.view', 'attendance.manage',
            'payroll.view', 'payroll.manage',
            'benefits.manage',
            'executive_teams.view', 'executive_teams.manage',
            'worker_assignment.manage',
        ],
        'executive' => [
            'assigned_work.view',
            'daily_checklist.manage',
            'daily_progress.manage',
            'site_records.manage',
            'media.upload',
            'approval_requests.manage',
        ],
        'qc' => [
            'qc.view', 'qc.perform', 'qc.reports.view',
        ],
        'tickets' => [
            'tickets.view', 'tickets.create', 'tickets.manage',
        ],
        'finance' => [
            'finance.view', 'finance.manage',
        ],
        'legal' => [
            'legal.view', 'legal.manage',
        ],
        'audit' => [
            'audit.view', 'audit.manage',
        ],
        'management' => [
            'company_records.view', 'company_records.manage',
            'client_records.view',
        ],
        'reports' => [
            'reports.view', 'reports.export',
        ],
        'client_portal' => [
            'client_portal.access',
        ],
        'tasks' => [
            'tasks.view', 'tasks.create', 'tasks.manage',
        ],
        'subcontractors' => [
            'subcontractors.view', 'subcontractors.manage',
        ],
        'subcontractor_portal' => [
            'subcontractor_sites.view', 'subcontractor_finance.view',
        ],
        'chat' => [
            'chat.access',
        ],
    ];

    /**
     * @var array<string, string[]|'*'>
     */
    private array $rolePermissions = [
        'Admin' => '*',
        'Sales' => [
            'sales' => '*',
            'tickets' => '*',
            'reports' => '*',
            'admin' => ['global_search.use'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'Marketing' => [
            'sales' => ['enquiries.view', 'enquiries.create', 'enquiries.edit', 'site_visits.view'],
            'reports' => ['reports.view'],
            'admin' => ['global_search.use'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'HR' => [
            'hr' => '*',
            'sales' => ['work_orders.view'],
            'reports' => ['reports.view'],
            'admin' => ['global_search.use'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'Executive Team Leader' => [
            'executive' => '*',
            'tickets' => ['tickets.view', 'tickets.create'],
            'sales' => ['work_orders.view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'Worker' => [
            'executive' => ['assigned_work.view', 'daily_checklist.manage', 'daily_progress.manage', 'media.upload'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'QC Officer' => [
            'qc' => '*',
            'sales' => ['work_orders.view'],
            'tickets' => ['tickets.view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'Finance' => [
            'finance' => '*',
            'hr' => ['payroll.view'],
            'reports' => '*',
            'management' => ['client_records.view'],
            'sales' => ['work_orders.view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'Management' => [
            'finance' => ['finance.view'],
            'legal' => ['legal.view'],
            'audit' => ['audit.view'],
            'management' => '*',
            'sales' => ['work_orders.view', 'ongoing_sites.view', 'completed_sites.view'],
            'tickets' => ['tickets.view'],
            'reports' => '*',
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'Legal' => [
            'legal' => '*',
            'management' => ['company_records.view', 'company_records.manage'],
            'reports' => ['reports.view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'Auditor' => [
            'audit' => '*',
            'finance' => ['finance.view'],
            'reports' => ['reports.view'],
            'sales' => ['work_orders.view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        // Deliberately scoped to Progress & Media only - no daily_checklist.manage,
        // site_records.manage, approval_requests.manage, or tickets.create, so a
        // Sub Contractor cannot enter Daily Checklist, Materials, Manpower, M.Book,
        // Ledger, Approval Requests, or Tickets. Those stay with the Executive
        // Team Leader (or Admin/Sales/HR for Monthly Summary).
        'Sub Contractor' => [
            'executive' => ['assigned_work.view', 'daily_progress.manage', 'media.upload'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            'subcontractor_portal' => '*',
        ],
        'Client' => [
            'client_portal' => '*',
        ],
    ];

    public function run(): void
    {
        DB::table('permissions')->delete();
        DB::table('roles')->delete();

        $allPermissions = collect($this->permissionGroups)->flatten();

        $permissions = $allPermissions->mapWithKeys(fn ($name) => [
            $name => Permission::create(['name' => $name, 'guard_name' => 'web']),
        ]);

        foreach ($this->rolePermissions as $roleName => $groups) {
            $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);

            if ($groups === '*') {
                $role->syncPermissions($allPermissions);

                continue;
            }

            $names = collect();

            foreach ($groups as $group => $selection) {
                $groupPermissions = collect($this->permissionGroups[$group]);
                $names = $names->merge($selection === '*' ? $groupPermissions : collect($selection));
            }

            $role->syncPermissions($names->unique()->values());
        }
    }
}
