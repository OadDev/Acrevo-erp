<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new subcontractor permissions directly to existing
     * production roles (rather than re-running the full seeder, which
     * would wipe every user's role assignment in production). Admin gets
     * the Subcontractor admin menu; Sub Contractor gets their own scoped
     * Sites/Finance visibility.
     */
    public function up(): void
    {
        $adminOnly = ['subcontractors.view', 'subcontractors.manage'];
        $subContractorOnly = ['subcontractor_sites.view', 'subcontractor_finance.view'];

        foreach ([...$adminOnly, ...$subContractorOnly] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo([...$adminOnly, ...$subContractorOnly]);
        }

        $subContractor = Role::where('name', 'Sub Contractor')->where('guard_name', 'web')->first();
        if ($subContractor) {
            $subContractor->givePermissionTo($subContractorOnly);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo([
                'subcontractors.view', 'subcontractors.manage',
                'subcontractor_sites.view', 'subcontractor_finance.view',
            ]);
        });
    }
};
