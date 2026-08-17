<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Finance needs to open a work order to reach its new Company Ledger
     * tab, so it needs read access to work orders too. Granted directly
     * here (rather than by re-running the seeder) so it doesn't disturb
     * any role customization already made in production.
     */
    public function up(): void
    {
        $role = Role::where('name', 'Finance')->where('guard_name', 'web')->first();
        $permission = Permission::where('name', 'work_orders.view')->where('guard_name', 'web')->first();

        if ($role && $permission && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $role = Role::where('name', 'Finance')->where('guard_name', 'web')->first();

        if ($role) {
            $role->revokePermissionTo('work_orders.view');
        }
    }
};
