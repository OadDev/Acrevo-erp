<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new WO permissions directly to existing production roles
     * (rather than re-running the full seeder, which would wipe any role
     * customization already made in production).
     *
     * Also backfills work_orders.summary_manage: PR #43 added it to
     * RolePermissionSeeder but shipped without a migration, so it never
     * existed in production and the Monthly Summary tab's edit/add
     * controls have been invisible to everyone - Admin included - since
     * that deploy.
     *
     * work_orders.overview_view controls the WO Overview tab/section's
     * visibility independently of edit rights (work_orders.edit).
     *
     * wo_materials/wo_manpower/wo_measurement_book/wo_ledger/wo_attendance
     * replace the old blanket site_records.manage with four independently
     * grantable permissions each (view/create/edit/delete), so a role or
     * user can be given, say, Measurement Book data entry without also
     * getting Delete on the Site Ledger. site_records.manage itself is
     * left in the permissions table - nothing checks it any more, and
     * deleting it would also delete the role_has_permissions rows that
     * reference it, which isn't worth the churn.
     */
    private array $fullCrud = [
        'wo_materials.view', 'wo_materials.create', 'wo_materials.edit', 'wo_materials.delete',
        'wo_manpower.view', 'wo_manpower.create', 'wo_manpower.edit', 'wo_manpower.delete',
        'wo_measurement_book.view', 'wo_measurement_book.create', 'wo_measurement_book.edit', 'wo_measurement_book.delete',
        'wo_ledger.view', 'wo_ledger.create', 'wo_ledger.edit', 'wo_ledger.delete',
        'wo_attendance.view', 'wo_attendance.create', 'wo_attendance.edit', 'wo_attendance.delete',
    ];

    private array $viewOnly = [
        'wo_materials.view', 'wo_manpower.view', 'wo_measurement_book.view',
        'wo_ledger.view', 'wo_attendance.view',
    ];

    public function up(): void
    {
        foreach (array_merge($this->fullCrud, ['work_orders.overview_view', 'work_orders.summary_manage']) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo(array_merge($this->fullCrud, ['work_orders.overview_view', 'work_orders.summary_manage']));
        }

        $executiveTeamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($executiveTeamLeader) {
            $executiveTeamLeader->givePermissionTo(array_merge($this->fullCrud, ['work_orders.overview_view', 'work_orders.summary_manage']));
        }

        $hr = Role::where('name', 'HR')->where('guard_name', 'web')->first();
        if ($hr) {
            $hr->givePermissionTo(array_merge($this->viewOnly, ['work_orders.overview_view', 'work_orders.summary_manage']));
        }

        foreach (['Sales', 'Finance', 'Management', 'Auditor'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(array_merge($this->viewOnly, ['work_orders.overview_view']));
            }
        }

        // No Site Ledger - matches the existing company-ledger/ledger
        // carve-out already applied to QC Officer in
        // WorkOrderPdfSections::forUser().
        $qc = Role::where('name', 'QC Officer')->where('guard_name', 'web')->first();
        if ($qc) {
            $qc->givePermissionTo(array_merge(array_diff($this->viewOnly, ['wo_ledger.view']), ['work_orders.overview_view']));
        }

        // Worker/Sub Contractor: same read-only access to these tabs they
        // already had before this split, minus the Site Ledger - company
        // and client cost figures a field role doesn't need to see.
        foreach (['Worker', 'Sub Contractor'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(array_merge(array_diff($this->viewOnly, ['wo_ledger.view']), ['work_orders.overview_view']));
            }
        }

        $demo = Role::where('name', 'Demo')->where('guard_name', 'web')->first();
        if ($demo) {
            $demo->givePermissionTo(array_merge($this->fullCrud, ['work_orders.overview_view', 'work_orders.summary_manage']));
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo(array_merge($this->fullCrud, ['work_orders.overview_view']));
        });
    }
};
