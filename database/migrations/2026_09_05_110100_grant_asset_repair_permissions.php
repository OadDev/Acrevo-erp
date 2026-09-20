<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Repair permissions directly to existing production
     * roles (rather than re-running the full seeder, which would wipe any
     * role customization already made in production).
     *
     * Admin gets full access. Management gets view/create/edit/download
     * -pdf (no update_status - tracking a repair to completion is a
     * site-level or Admin action). Executive Team Leader gets
     * view/create/update_status/download-pdf, both scoped in the
     * controller to their own site. Every other role gets nothing by
     * default.
     */
    public function up(): void
    {
        $all = ['repairs.view', 'repairs.create', 'repairs.edit', 'repairs.update_status', 'repairs.download_pdf'];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($all);
        }

        $management = Role::where('name', 'Management')->where('guard_name', 'web')->first();
        if ($management) {
            $management->givePermissionTo(['repairs.view', 'repairs.create', 'repairs.edit', 'repairs.download_pdf']);
        }

        $teamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($teamLeader) {
            $teamLeader->givePermissionTo(['repairs.view', 'repairs.create', 'repairs.update_status', 'repairs.download_pdf']);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo(['repairs.view', 'repairs.create', 'repairs.edit', 'repairs.update_status', 'repairs.download_pdf']);
        });
    }
};
