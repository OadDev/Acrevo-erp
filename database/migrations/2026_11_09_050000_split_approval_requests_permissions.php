<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Approval Request permissions directly to existing
     * production roles (rather than re-running the full seeder, which
     * would wipe any role customization already made in production).
     *
     * Approval Requests previously had no permission of its own gating the
     * read-only list (the tab/list was visible to anyone who could open
     * the work order at all), with create/respond/edit/delete/pdf/zip
     * gated by 'work_orders.edit|approval_requests.manage' - in practice
     * only Admin (everything), Sales (via work_orders.edit), and
     * Executive Team Leader (via the 'executive' permission group's old
     * bundled approval_requests.manage) could do any of that. This splits
     * it into four independently grantable permissions, same pattern as
     * the Daily Checklist/Progress & Media/Monthly Summary split.
     *
     * approval_requests.manage is left in the permissions table - nothing
     * checks it any more, and deleting it would also delete the
     * role_has_permissions rows that reference it, which isn't worth the
     * churn.
     */
    private array $permissions = [
        'approval_requests.view', 'approval_requests.create', 'approval_requests.edit', 'approval_requests.delete',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Full rights - same net access these roles already had (Admin via
        // its all-permissions role, Sales via work_orders.edit's old
        // OR-fallback, Executive Team Leader via the 'executive' group's
        // old bundled approval_requests.manage).
        foreach (['Admin', 'Sales', 'Executive Team Leader'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($this->permissions);
            }
        }

        // View-only - same read access these roles already had on the
        // Approval Requests tab (unconditionally visible before this
        // split), with no entry rights (none of them held work_orders.edit
        // or the 'executive' group's old approval_requests.manage).
        foreach (['HR', 'Worker', 'QC Officer', 'Finance', 'Management', 'Auditor', 'Sub Contractor'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(['approval_requests.view']);
            }
        }

        $demo = Role::where('name', 'Demo')->where('guard_name', 'web')->first();
        if ($demo) {
            $demo->givePermissionTo($this->permissions);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo($this->permissions);
        });
    }
};
