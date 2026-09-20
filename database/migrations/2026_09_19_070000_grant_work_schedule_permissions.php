<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Site Work Schedule Planner permissions directly to
     * existing production roles (rather than re-running the full seeder,
     * which would wipe any role customization already made in production).
     *
     * Admin gets full access including work_schedules.manage. Executive
     * Team Leader and Sales get view-only, scoped in the controller to
     * sites they lead / sites of clients assigned to them respectively.
     * Client gets view-only (minus view_progress - delay/progress detail
     * can include internal remarks, withheld by default), scoped to their
     * own site via ClientLogin. Every other role gets nothing by default -
     * an Admin can grant work_schedules.* to any role from Roles &
     * Permissions without a code change.
     */
    public function up(): void
    {
        $all = [
            'work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details',
            'work_schedules.view_dates', 'work_schedules.view_progress', 'work_schedules.manage',
        ];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($all);
        }

        $teamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($teamLeader) {
            $teamLeader->givePermissionTo(['work_schedules.view_site', 'work_schedules.view_details', 'work_schedules.view_dates', 'work_schedules.view_progress']);
        }

        $sales = Role::where('name', 'Sales')->where('guard_name', 'web')->first();
        if ($sales) {
            $sales->givePermissionTo(['work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details', 'work_schedules.view_dates', 'work_schedules.view_progress']);
        }

        $client = Role::where('name', 'Client')->where('guard_name', 'web')->first();
        if ($client) {
            $client->givePermissionTo(['work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details', 'work_schedules.view_dates']);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo([
                'work_schedules.view_overall', 'work_schedules.view_site', 'work_schedules.view_details',
                'work_schedules.view_dates', 'work_schedules.view_progress', 'work_schedules.manage',
            ]);
        });
    }
};
