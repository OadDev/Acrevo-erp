<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Movement permissions directly to existing production
     * roles (rather than re-running the full seeder, which would wipe any
     * role customization already made in production).
     *
     * Admin gets full access. Management gets view/create/edit/download
     * -pdf (no approve - confirming receipt is a site-level or Admin
     * action). Executive Team Leader gets view/create/approve/download
     * -pdf, both scoped in the controller to their own site. Every other
     * role gets nothing by default.
     */
    public function up(): void
    {
        $all = ['movements.view', 'movements.create', 'movements.approve', 'movements.edit', 'movements.download_pdf'];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($all);
        }

        $management = Role::where('name', 'Management')->where('guard_name', 'web')->first();
        if ($management) {
            $management->givePermissionTo(['movements.view', 'movements.create', 'movements.edit', 'movements.download_pdf']);
        }

        $teamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($teamLeader) {
            $teamLeader->givePermissionTo(['movements.view', 'movements.create', 'movements.approve', 'movements.download_pdf']);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo(['movements.view', 'movements.create', 'movements.approve', 'movements.edit', 'movements.download_pdf']);
        });
    }
};
