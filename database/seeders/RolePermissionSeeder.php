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
            'audit.view', 'audit.manage', 'audit.delete',
        ],
        'management' => [
            'company_records.view', 'company_records.manage',
            'client_records.view',
        ],
        'clients' => [
            'clients.manage',
        ],
        // Verification/Equipment-Request permissions are added as those
        // features are built, not pre-declared here - a permission
        // checkbox with nothing behind it yet would just confuse whoever
        // configures Roles & Permissions.
        'assets' => [
            'assets.view', 'assets.create', 'assets.edit', 'assets.approve',
            'assets.delete', 'assets.restore', 'assets.update_status',
            'assets.view_history', 'assets.download_pdf', 'assets.view_missing',
        ],
        'movements' => [
            'movements.view', 'movements.create', 'movements.approve',
            'movements.edit', 'movements.delete', 'movements.download_pdf',
        ],
        'repairs' => [
            'repairs.view', 'repairs.create', 'repairs.edit', 'repairs.delete',
            'repairs.update_status', 'repairs.download_pdf',
        ],
        'verifications' => [
            'verifications.view', 'verifications.create', 'verifications.edit',
            'verifications.delete', 'verifications.download_pdf',
        ],
        'equipment_requests' => [
            'equipment_requests.view', 'equipment_requests.create', 'equipment_requests.edit',
            'equipment_requests.delete', 'equipment_requests.approve',
            'equipment_requests.receive', 'equipment_requests.download_pdf',
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
            'conversations.clear',
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
            // View + update status only, scoped in the controller to assets
            // currently at a work order this Team Leader leads - no create,
            // edit, approve, delete, or restore.
            'assets' => ['assets.view', 'assets.update_status', 'assets.view_history', 'assets.download_pdf', 'assets.view_missing'],
            // Create/confirm movements, both scoped in the controller to
            // this Team Leader's own site - dispatching what's at their
            // site, and confirming receipt of what arrives there.
            'movements' => ['movements.view', 'movements.create', 'movements.approve', 'movements.download_pdf'],
            // Report a repair and track it through to completion - both
            // scoped in the controller to assets at this Team Leader's site.
            'repairs' => ['repairs.view', 'repairs.create', 'repairs.update_status', 'repairs.download_pdf'],
            // Physically verify assets at this Team Leader's own site -
            // scoped in the controller the same way as repairs/movements.
            'verifications' => ['verifications.view', 'verifications.create', 'verifications.download_pdf'],
            // Request equipment for their own site and confirm/complete
            // receipt of it - no approve, deciding a request (and any
            // purchase it needs) is Admin's call.
            'equipment_requests' => ['equipment_requests.view', 'equipment_requests.create', 'equipment_requests.receive', 'equipment_requests.download_pdf'],
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
            // Create + edit, but no assets.approve - edits go through the
            // AssetChangeRequest queue instead of applying immediately.
            // No delete/restore/update_status either.
            'assets' => ['assets.view', 'assets.create', 'assets.edit', 'assets.view_history', 'assets.download_pdf', 'assets.view_missing'],
            // No movements.approve - Management can dispatch a movement but
            // confirming receipt is a site-level action (an Executive Team
            // Leader) or Admin's to make. No movements.edit/delete either -
            // editing or removing a movement entry is Admin-only.
            'movements' => ['movements.view', 'movements.create', 'movements.download_pdf'],
            // No repairs.update_status - Management can log a repair entry
            // but tracking it through to completion is a site-level action
            // (an Executive Team Leader) or Admin's to make. No
            // repairs.edit/delete either - editing or removing a repair
            // entry is Admin-only.
            'repairs' => ['repairs.view', 'repairs.create', 'repairs.download_pdf'],
            // Management can also record a verification anywhere (not
            // site-scoped, unlike a Team Leader) as a spot-check.
            'verifications' => ['verifications.view', 'verifications.create', 'verifications.download_pdf'],
            // Management can request equipment for any site and confirm
            // receipt/completion, but not decide (approve/reject) a
            // request - same reasoning as withholding movements.approve
            // and repairs.update_status above.
            'equipment_requests' => ['equipment_requests.view', 'equipment_requests.create', 'equipment_requests.receive', 'equipment_requests.download_pdf'],
        ],
        'Legal' => [
            'legal' => '*',
            'management' => ['company_records.view', 'company_records.manage'],
            'reports' => ['reports.view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
        ],
        'Auditor' => [
            // Explicit list, not '*' - Auditor gets to create and edit
            // audits, but audit.delete (the whole group's '*' would include
            // it) is deliberately withheld, so old audit records can't be
            // removed by anyone but Admin.
            'audit' => ['audit.view', 'audit.manage'],
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
