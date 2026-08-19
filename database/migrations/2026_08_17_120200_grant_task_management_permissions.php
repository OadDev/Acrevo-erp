<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new tasks.* permissions directly to existing production
     * roles (rather than re-running the full seeder, which would wipe any
     * role customization already made in production). Every internal role
     * gets tasks.view + tasks.create; only Admin gets tasks.manage; Client
     * gets nothing - the Task module is explicitly internal-only.
     */
    public function up(): void
    {
        foreach (['tasks.view', 'tasks.create', 'tasks.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $view = Permission::where('name', 'tasks.view')->where('guard_name', 'web')->first();
        $create = Permission::where('name', 'tasks.create')->where('guard_name', 'web')->first();
        $manage = Permission::where('name', 'tasks.manage')->where('guard_name', 'web')->first();

        Role::where('guard_name', 'web')->where('name', '!=', 'Client')->get()->each(function (Role $role) use ($view, $create) {
            if (! $role->hasPermissionTo($view)) {
                $role->givePermissionTo($view);
            }
            if (! $role->hasPermissionTo($create)) {
                $role->givePermissionTo($create);
            }
        });

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin && ! $admin->hasPermissionTo($manage)) {
            $admin->givePermissionTo($manage);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo('tasks.view');
            $role->revokePermissionTo('tasks.create');
            $role->revokePermissionTo('tasks.manage');
        });
    }
};
