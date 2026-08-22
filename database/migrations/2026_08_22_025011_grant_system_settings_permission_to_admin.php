<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * system_settings.manage already existed in the seeder's permission list,
     * but the seeder only runs once at initial install - if this row was
     * added after that, production's Admin role would never actually have
     * it. Grant it directly to be certain, same as chat.access.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'system_settings.manage', 'guard_name' => 'web']);

        $admin = Role::where('guard_name', 'web')->where('name', 'Admin')->first();

        if ($admin && ! $admin->hasPermissionTo('system_settings.manage')) {
            $admin->givePermissionTo('system_settings.manage');
        }
    }

    public function down(): void
    {
        $admin = Role::where('guard_name', 'web')->where('name', 'Admin')->first();
        $admin?->revokePermissionTo('system_settings.manage');
    }
};
