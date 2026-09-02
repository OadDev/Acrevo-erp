<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants two new Admin-only permissions directly to existing production
     * roles (rather than re-running the full seeder, which would wipe any
     * role customization already made in production):
     *
     * - audit.delete splits "remove an audit record" out of audit.manage,
     *   so Auditor (which keeps audit.manage for create/edit) no longer
     *   gets delete for free through it - only Admin does.
     * - clients.manage splits Client edit/remove/portal-access/portal
     *   -permissions out of the single enquiries.view gate the whole
     *   clients resource previously shared with Sales and Marketing - both
     *   of whom need enquiries.view for the Enquiry module and were
     *   incidentally getting full Client management through it.
     */
    public function up(): void
    {
        $names = ['audit.delete', 'clients.manage'];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($names);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo(['audit.delete', 'clients.manage']);
        });
    }
};
