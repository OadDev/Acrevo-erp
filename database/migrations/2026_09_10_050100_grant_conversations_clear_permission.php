<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Lets Admin clear a Discussion thread (e.g. before/after a client is
     * granted access to it). Granted directly here rather than via the
     * full RolePermissionSeeder re-run - see the equivalent history
     * edit/remove migration for why.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'conversations.clear', 'guard_name' => 'web']);

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo('conversations.clear');
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo('conversations.clear');
        });
    }
};
