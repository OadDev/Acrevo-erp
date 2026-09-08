<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants Edit/Remove on Movement, Repair, Verification History, and
     * Equipment Requests directly to Admin in production, rather than
     * re-running the full RolePermissionSeeder - that seeder deletes and
     * recreates every permission and role from its hardcoded list, which
     * would wipe any custom role or permission tweak made since via the
     * Roles & Permissions screen (Admin\RoleController lets Admin create
     * roles and reassign permissions freely, independent of the seeder).
     *
     * movements.edit/repairs.edit already existed and were already granted
     * to both Admin and Management; per the "Admin only" requirement they
     * are revoked from Management here. The rest (movements.delete,
     * repairs.delete, verifications.edit/delete, equipment_requests.edit/
     * delete) are brand new and go to Admin only.
     */
    public function up(): void
    {
        $newPermissions = [
            'movements.delete',
            'repairs.delete',
            'verifications.edit',
            'verifications.delete',
            'equipment_requests.edit',
            'equipment_requests.delete',
        ];

        foreach ($newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($newPermissions);
        }

        $management = Role::where('name', 'Management')->where('guard_name', 'web')->first();
        if ($management) {
            $management->revokePermissionTo(['movements.edit', 'repairs.edit']);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo([
                'movements.delete', 'repairs.delete',
                'verifications.edit', 'verifications.delete',
                'equipment_requests.edit', 'equipment_requests.delete',
            ]);
        });

        $management = Role::where('name', 'Management')->where('guard_name', 'web')->first();
        if ($management) {
            $management->givePermissionTo(['movements.edit', 'repairs.edit']);
        }
    }
};
