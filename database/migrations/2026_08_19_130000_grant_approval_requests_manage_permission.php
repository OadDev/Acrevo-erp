<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new approval_requests.manage permission directly to
     * existing production roles (rather than re-running the full seeder,
     * which would wipe any role customization already made in production).
     * Only Admin and Executive Team Leader get it - raising/responding to
     * approval requests moves from Admin/Sales-only to the site team's
     * Team Leader, while Worker and Sub Contractor deliberately do not
     * get it (Sub Contractor stays scoped to Progress & Media only).
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'approval_requests.manage', 'guard_name' => 'web']);

        Role::whereIn('name', ['Admin', 'Executive Team Leader'])->where('guard_name', 'web')->get()->each(function (Role $role) use ($permission) {
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        });
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo('approval_requests.manage');
        });
    }
};
