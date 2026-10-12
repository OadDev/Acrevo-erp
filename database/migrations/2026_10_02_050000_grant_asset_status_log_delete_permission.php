<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants assets.delete_status_log directly to Admin in production,
     * rather than re-running the full RolePermissionSeeder - see the same
     * note in 2026_09_08_050000_grant_admin_only_history_edit_remove_
     * permissions.php. Lets an Admin delete a mistaken Status Update entry
     * from an asset's Status History and have the quantity it moved
     * restored to whatever status it was changed from - nobody else gets
     * it, matching movements.delete/repairs.delete/verifications.delete.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'assets.delete_status_log', 'guard_name' => 'web']);

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo('assets.delete_status_log');
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo('assets.delete_status_log');
        });
    }
};
