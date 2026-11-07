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
     * Daily Work with Checklist and Progress & Media each replace a single
     * blanket *.manage permission with four independently grantable View/
     * Create/Edit/Delete permissions, same pattern as the earlier
     * site_records.manage split. Progress & Media also absorbs the old
     * media.upload permission (the Media Gallery/Details Upload section of
     * that same tab) - every role that held one always held the other, so
     * folding them into one four-permission set loses nothing.
     *
     * Monthly Summary gets the same split in place of work_orders.
     * summary_manage.
     *
     * daily_checklist.manage, daily_progress.manage, media.upload, and
     * work_orders.summary_manage are left in the permissions table -
     * nothing checks them any more, and deleting them would also delete
     * the role_has_permissions rows that reference them, which isn't
     * worth the churn.
     */
    private array $dailyChecklist = [
        'daily_checklist.view', 'daily_checklist.create', 'daily_checklist.edit', 'daily_checklist.delete',
    ];

    private array $dailyProgress = [
        'daily_progress.view', 'daily_progress.create', 'daily_progress.edit', 'daily_progress.delete',
    ];

    private array $summary = [
        'work_orders.summary_view', 'work_orders.summary_create', 'work_orders.summary_edit', 'work_orders.summary_delete',
    ];

    public function up(): void
    {
        $all = array_merge($this->dailyChecklist, $this->dailyProgress, $this->summary);

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($all);
        }

        // Full rights on all three - same net access the old
        // daily_checklist.manage/daily_progress.manage/media.upload/
        // work_orders.summary_manage bundle gave this role.
        $executiveTeamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($executiveTeamLeader) {
            $executiveTeamLeader->givePermissionTo($all);
        }

        // Full rights on Daily Checklist and Progress & Media, plus
        // view-only on Monthly Summary (it was always visible to anyone
        // viewing the WO before this split, just not editable by Worker).
        $worker = Role::where('name', 'Worker')->where('guard_name', 'web')->first();
        if ($worker) {
            $worker->givePermissionTo(array_merge($this->dailyChecklist, $this->dailyProgress, ['work_orders.summary_view']));
        }

        // View-only on Daily Checklist (can see it, can't enter it), full
        // rights on Progress & Media, plus view-only on Monthly Summary -
        // same net access this role had before this split.
        $subContractor = Role::where('name', 'Sub Contractor')->where('guard_name', 'web')->first();
        if ($subContractor) {
            $subContractor->givePermissionTo(array_merge(['daily_checklist.view'], $this->dailyProgress, ['work_orders.summary_view']));
        }

        // Full Monthly Summary rights (replacing work_orders.summary_manage)
        // plus view-only on Daily Checklist/Progress & Media, same read
        // access these roles already had on those tabs.
        foreach (['Sales', 'HR'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(array_merge($this->summary, ['daily_checklist.view', 'daily_progress.view']));
            }
        }

        // View-only on all three - same read access these roles already
        // had on these tabs before this split.
        foreach (['QC Officer', 'Finance', 'Management', 'Auditor'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(['daily_checklist.view', 'daily_progress.view', 'work_orders.summary_view']);
            }
        }

        $demo = Role::where('name', 'Demo')->where('guard_name', 'web')->first();
        if ($demo) {
            $demo->givePermissionTo($all);
        }
    }

    public function down(): void
    {
        $all = array_merge($this->dailyChecklist, $this->dailyProgress, $this->summary);

        Role::where('guard_name', 'web')->get()->each(function (Role $role) use ($all) {
            $role->revokePermissionTo($all);
        });
    }
};
