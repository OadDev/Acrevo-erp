<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Equipment & Asset Management permissions directly to
     * existing production roles (rather than re-running the full seeder,
     * which would wipe any role customization already made in production).
     *
     * Admin gets full access. Management gets view/create/edit/view_history
     * /download_pdf (edits go through the AssetChangeRequest approval
     * queue, not assets.approve). Executive Team Leader gets
     * view/update_status/view_history/download_pdf, scoped in the
     * controller to assets currently at a work order they lead. Every
     * other role gets nothing by default - an Admin can grant assets.* to
     * any role from Roles & Permissions without a code change.
     */
    public function up(): void
    {
        $all = [
            'assets.view', 'assets.create', 'assets.edit', 'assets.approve',
            'assets.delete', 'assets.restore', 'assets.update_status',
            'assets.view_history', 'assets.download_pdf',
        ];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($all);
        }

        $management = Role::where('name', 'Management')->where('guard_name', 'web')->first();
        if ($management) {
            $management->givePermissionTo(['assets.view', 'assets.create', 'assets.edit', 'assets.view_history', 'assets.download_pdf']);
        }

        $teamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($teamLeader) {
            $teamLeader->givePermissionTo(['assets.view', 'assets.update_status', 'assets.view_history', 'assets.download_pdf']);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo([
                'assets.view', 'assets.create', 'assets.edit', 'assets.approve',
                'assets.delete', 'assets.restore', 'assets.update_status',
                'assets.view_history', 'assets.download_pdf',
            ]);
        });
    }
};
