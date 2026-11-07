<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
            'work_orders.overview_view',
            'work_orders.summary_view', 'work_orders.summary_create', 'work_orders.summary_edit', 'work_orders.summary_delete',
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
            'approval_requests.manage',
        ],
        // Daily Work with Checklist, and Progress & Media (which also
        // covers the WO Media Gallery/Details Upload, previously its own
        // media.upload permission - always granted alongside
        // daily_progress.manage anyway, so folding it in loses nothing)
        // each replace a single blanket *.manage permission with four
        // independently grantable View/Create/Edit/Delete permissions.
        'daily_checklist' => [
            'daily_checklist.view', 'daily_checklist.create', 'daily_checklist.edit', 'daily_checklist.delete',
        ],
        'daily_progress' => [
            'daily_progress.view', 'daily_progress.create', 'daily_progress.edit', 'daily_progress.delete',
        ],
        // These replace the old single site_records.manage permission -
        // each WO sub-module (except Overview, Approval Requests, and
        // Tickets, which have their own dedicated permissions above/below)
        // now has independently grantable View/Create/Edit/Delete
        // permissions, so a role or user can be given e.g. data entry on
        // Measurement Books without also getting Delete on the Site
        // Ledger.
        'wo_materials' => [
            'wo_materials.view', 'wo_materials.create', 'wo_materials.edit', 'wo_materials.delete',
        ],
        'wo_manpower' => [
            'wo_manpower.view', 'wo_manpower.create', 'wo_manpower.edit', 'wo_manpower.delete',
        ],
        'wo_measurement_book' => [
            'wo_measurement_book.view', 'wo_measurement_book.create', 'wo_measurement_book.edit', 'wo_measurement_book.delete',
        ],
        'wo_ledger' => [
            'wo_ledger.view', 'wo_ledger.create', 'wo_ledger.edit', 'wo_ledger.delete',
        ],
        'wo_attendance' => [
            'wo_attendance.view', 'wo_attendance.create', 'wo_attendance.edit', 'wo_attendance.delete',
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
            'assets.delete_status_log',
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
        'work_schedules' => [
            'work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details',
            'work_schedules.view_dates', 'work_schedules.view_progress', 'work_schedules.manage',
        ],
        'companies' => [
            'companies.manage',
        ],
        'items' => [
            'items.manage',
        ],
        'proforma_invoices' => [
            'proforma_invoices.view', 'proforma_invoices.create', 'proforma_invoices.edit',
            'proforma_invoices.delete', 'proforma_invoices.convert', 'proforma_invoices.download_pdf',
        ],
        'tax_invoices' => [
            'tax_invoices.view', 'tax_invoices.create', 'tax_invoices.edit',
            'tax_invoices.delete', 'tax_invoices.download_pdf',
        ],
        'delivery_challans' => [
            'delivery_challans.view', 'delivery_challans.create', 'delivery_challans.edit',
            'delivery_challans.delete', 'delivery_challans.download_pdf',
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
            // View-only, scoped in the controller to sites of clients
            // assigned to this Sales user - no work_schedules.manage.
            'work_schedules' => ['work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details', 'work_schedules.view_dates', 'work_schedules.view_progress'],
            // Sales issues these day to day - full lifecycle including
            // converting a Proforma to a Tax Invoice, but no delete (a
            // wrong document gets corrected via edit, not removed).
            'proforma_invoices' => ['proforma_invoices.view', 'proforma_invoices.create', 'proforma_invoices.edit', 'proforma_invoices.convert', 'proforma_invoices.download_pdf'],
            'tax_invoices' => ['tax_invoices.view', 'tax_invoices.create', 'tax_invoices.edit', 'tax_invoices.download_pdf'],
            'delivery_challans' => ['delivery_challans.view', 'delivery_challans.create', 'delivery_challans.edit', 'delivery_challans.download_pdf'],
            // View-only on every WO sub-module by default - same read access
            // Sales already had before these were split out of
            // site_records.manage. Admin can grant Create/Edit/Delete on a
            // specific module per role or per user from here on.
            'wo_materials' => ['wo_materials.view'],
            'wo_manpower' => ['wo_manpower.view'],
            'wo_measurement_book' => ['wo_measurement_book.view'],
            'wo_ledger' => ['wo_ledger.view'],
            'wo_attendance' => ['wo_attendance.view'],
            'daily_checklist' => ['daily_checklist.view'],
            'daily_progress' => ['daily_progress.view'],
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
            'sales' => [
                'work_orders.view', 'work_orders.overview_view',
                'work_orders.summary_view', 'work_orders.summary_create', 'work_orders.summary_edit', 'work_orders.summary_delete',
            ],
            'reports' => ['reports.view'],
            'admin' => ['global_search.use'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            'wo_materials' => ['wo_materials.view'],
            'wo_manpower' => ['wo_manpower.view'],
            'wo_measurement_book' => ['wo_measurement_book.view'],
            'wo_ledger' => ['wo_ledger.view'],
            'wo_attendance' => ['wo_attendance.view'],
            'daily_checklist' => ['daily_checklist.view'],
            'daily_progress' => ['daily_progress.view'],
        ],
        'Executive Team Leader' => [
            'executive' => '*',
            'tickets' => ['tickets.view', 'tickets.create'],
            'sales' => [
                'work_orders.view', 'work_orders.overview_view',
                'work_orders.summary_view', 'work_orders.summary_create', 'work_orders.summary_edit', 'work_orders.summary_delete',
            ],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            // Full data-entry + correction rights on every WO sub-module -
            // same net access the old blanket site_records.manage gave this
            // role, now as five independently revocable permissions.
            'wo_materials' => '*',
            'wo_manpower' => '*',
            'wo_measurement_book' => '*',
            'wo_ledger' => '*',
            'wo_attendance' => '*',
            // Same reasoning - full Daily Work/Checklist and Progress &
            // Media rights, same net access the old daily_checklist.manage/
            // daily_progress.manage/media.upload bundle gave this role.
            'daily_checklist' => '*',
            'daily_progress' => '*',
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
            // View-only, scoped in the controller to sites this Team Leader
            // leads - no work_schedules.manage, updating progress/dates is
            // Admin's call for now.
            'work_schedules' => ['work_schedules.view_site', 'work_schedules.view_details', 'work_schedules.view_dates', 'work_schedules.view_progress'],
        ],
        'Worker' => [
            'executive' => ['assigned_work.view'],
            'sales' => ['work_orders.overview_view', 'work_orders.summary_view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            // View-only, same read access a Worker already had on these
            // tabs before they were split out of site_records.manage - no
            // Site Ledger, which carries company/client cost figures a
            // field worker doesn't need to see.
            'wo_materials' => ['wo_materials.view'],
            'wo_manpower' => ['wo_manpower.view'],
            'wo_measurement_book' => ['wo_measurement_book.view'],
            'wo_attendance' => ['wo_attendance.view'],
            // Full rights - same net access the old daily_checklist.manage/
            // daily_progress.manage/media.upload bundle gave this role.
            'daily_checklist' => '*',
            'daily_progress' => '*',
        ],
        'QC Officer' => [
            'qc' => '*',
            'sales' => ['work_orders.view', 'work_orders.overview_view', 'work_orders.summary_view'],
            'tickets' => ['tickets.view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            'wo_materials' => ['wo_materials.view'],
            'wo_manpower' => ['wo_manpower.view'],
            'wo_measurement_book' => ['wo_measurement_book.view'],
            'wo_attendance' => ['wo_attendance.view'],
            'daily_checklist' => ['daily_checklist.view'],
            'daily_progress' => ['daily_progress.view'],
        ],
        'Finance' => [
            'finance' => '*',
            'hr' => ['payroll.view'],
            'reports' => '*',
            'management' => ['client_records.view'],
            'sales' => ['work_orders.view', 'work_orders.overview_view', 'work_orders.summary_view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            'wo_materials' => ['wo_materials.view'],
            'wo_manpower' => ['wo_manpower.view'],
            'wo_measurement_book' => ['wo_measurement_book.view'],
            'wo_ledger' => ['wo_ledger.view'],
            'wo_attendance' => ['wo_attendance.view'],
            'daily_checklist' => ['daily_checklist.view'],
            'daily_progress' => ['daily_progress.view'],
            // Finance owns billing documents end to end, including delete
            // (correcting a wrongly-issued Tax Invoice) and the Item price
            // catalog - but not Company Profile setup, which stays
            // Admin-only (legal/bank details).
            'items' => '*',
            'proforma_invoices' => ['proforma_invoices.view', 'proforma_invoices.download_pdf'],
            'tax_invoices' => '*',
            'delivery_challans' => ['delivery_challans.view', 'delivery_challans.download_pdf'],
        ],
        'Management' => [
            'finance' => ['finance.view'],
            'legal' => ['legal.view'],
            'audit' => ['audit.view'],
            'management' => '*',
            'sales' => ['work_orders.view', 'ongoing_sites.view', 'completed_sites.view', 'work_orders.overview_view', 'work_orders.summary_view'],
            'tickets' => ['tickets.view'],
            'reports' => '*',
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            'wo_materials' => ['wo_materials.view'],
            'wo_manpower' => ['wo_manpower.view'],
            'wo_measurement_book' => ['wo_measurement_book.view'],
            'wo_ledger' => ['wo_ledger.view'],
            'wo_attendance' => ['wo_attendance.view'],
            'daily_checklist' => ['daily_checklist.view'],
            'daily_progress' => ['daily_progress.view'],
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
            'proforma_invoices' => ['proforma_invoices.view', 'proforma_invoices.create', 'proforma_invoices.edit', 'proforma_invoices.convert', 'proforma_invoices.download_pdf'],
            'tax_invoices' => ['tax_invoices.view', 'tax_invoices.create', 'tax_invoices.edit', 'tax_invoices.download_pdf'],
            'delivery_challans' => ['delivery_challans.view', 'delivery_challans.create', 'delivery_challans.edit', 'delivery_challans.download_pdf'],
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
            'sales' => ['work_orders.view', 'work_orders.overview_view', 'work_orders.summary_view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            'wo_materials' => ['wo_materials.view'],
            'wo_manpower' => ['wo_manpower.view'],
            'wo_measurement_book' => ['wo_measurement_book.view'],
            'wo_ledger' => ['wo_ledger.view'],
            'wo_attendance' => ['wo_attendance.view'],
            'daily_checklist' => ['daily_checklist.view'],
            'daily_progress' => ['daily_progress.view'],
        ],
        // Deliberately scoped to Progress & Media only - no daily_checklist
        // create/edit/delete, site_records.manage, approval_requests.manage,
        // or tickets.create, so a Sub Contractor cannot enter Daily Work/
        // Checklist, Materials, Manpower, M.Book, Ledger, Approval Requests,
        // or Tickets. Those stay with the Executive Team Leader (or
        // Admin/Sales/HR for Monthly Summary).
        'Sub Contractor' => [
            'executive' => ['assigned_work.view'],
            'sales' => ['work_orders.overview_view', 'work_orders.summary_view'],
            'tasks' => ['tasks.view', 'tasks.create'],
            'chat' => ['chat.access'],
            'subcontractor_portal' => '*',
            // View-only, same read access a Sub Contractor already had on
            // these tabs before they were split out of site_records.manage.
            'wo_materials' => ['wo_materials.view'],
            'wo_manpower' => ['wo_manpower.view'],
            'wo_measurement_book' => ['wo_measurement_book.view'],
            'wo_attendance' => ['wo_attendance.view'],
            // View-only on Daily Work/Checklist (can see it, can't enter
            // it) - full rights on Progress & Media, same net access the
            // old daily_progress.manage/media.upload bundle gave this role.
            'daily_checklist' => ['daily_checklist.view'],
            'daily_progress' => '*',
        ],
        'Client' => [
            'client_portal' => '*',
            // View-only, scoped in the controller to this Client's own
            // site(s) - view_overall doubles as "my site(s) list" since it's
            // scoped the same way, letting the portal nav link straight to
            // it without a client-specific route. Delay/progress detail is
            // withheld by default since it can include internal remarks -
            // Admin can grant it per-client.
            'work_schedules' => ['work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details', 'work_schedules.view_dates'],
        ],
        // For prospective-client demos. SwitchDemoDatabaseConnection moves
        // every business-model query for this role onto a wholly separate
        // "demo" database, so it's safe to grant full create/edit/delete
        // access here - none of it can ever reach production. The one
        // exception is 'admin': users.*, roles.*, and permissions.manage
        // are withheld because User/Role/Permission are deliberately pinned
        // to the main connection (auth has to keep working while the
        // switch is active) - granting those would let a demo session
        // edit real staff accounts and roles.
        'Demo' => [
            'admin' => ['admin.dashboard.view', 'company_settings.manage', 'masters.manage', 'audit_logs.view', 'activity_logs.view', 'notifications.manage', 'global_search.use', 'system_settings.manage'],
            'sales' => '*',
            'hr' => '*',
            'executive' => '*',
            'wo_materials' => '*',
            'wo_manpower' => '*',
            'wo_measurement_book' => '*',
            'wo_ledger' => '*',
            'wo_attendance' => '*',
            'daily_checklist' => '*',
            'daily_progress' => '*',
            'qc' => '*',
            'tickets' => '*',
            'finance' => '*',
            'legal' => '*',
            'audit' => '*',
            'management' => '*',
            'clients' => '*',
            'assets' => '*',
            'movements' => '*',
            'repairs' => '*',
            'verifications' => '*',
            'equipment_requests' => '*',
            'reports' => '*',
            'tasks' => '*',
            'subcontractors' => '*',
            'subcontractor_portal' => '*',
            'chat' => '*',
            'work_schedules' => '*',
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
