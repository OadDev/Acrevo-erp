<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Verification permissions, plus the new
     * assets.view_missing permission, directly to existing production
     * roles (rather than re-running the full seeder, which would wipe any
     * role customization already made in production).
     *
     * Admin gets full access. Management and Executive Team Leader both
     * get the full verifications.* set - a Team Leader's create is scoped
     * in the controller to assets at a site they lead, Management's is
     * not. Every other role gets nothing by default.
     */
    public function up(): void
    {
        $verificationPermissions = ['verifications.view', 'verifications.create', 'verifications.download_pdf'];

        foreach ($verificationPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Permission::firstOrCreate(['name' => 'assets.view_missing', 'guard_name' => 'web']);

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo([...$verificationPermissions, 'assets.view_missing']);
        }

        $management = Role::where('name', 'Management')->where('guard_name', 'web')->first();
        if ($management) {
            $management->givePermissionTo([...$verificationPermissions, 'assets.view_missing']);
        }

        $teamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($teamLeader) {
            $teamLeader->givePermissionTo([...$verificationPermissions, 'assets.view_missing']);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo(['verifications.view', 'verifications.create', 'verifications.download_pdf', 'assets.view_missing']);
        });
    }
};
