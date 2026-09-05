<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Equipment Request permissions directly to existing
     * production roles (rather than re-running the full seeder, which
     * would wipe any role customization already made in production).
     *
     * Admin gets full access, including deciding (approve/reject) a
     * request. Management and Executive Team Leader can both request
     * equipment and confirm/complete receipt of it, but not decide a
     * request - that's Admin's call, matching how movements.approve and
     * repairs.update_status are also withheld from Management elsewhere
     * in this module. Every other role gets nothing by default.
     */
    public function up(): void
    {
        $all = ['equipment_requests.view', 'equipment_requests.create', 'equipment_requests.approve', 'equipment_requests.receive', 'equipment_requests.download_pdf'];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($all);
        }

        $notApprove = ['equipment_requests.view', 'equipment_requests.create', 'equipment_requests.receive', 'equipment_requests.download_pdf'];

        $management = Role::where('name', 'Management')->where('guard_name', 'web')->first();
        if ($management) {
            $management->givePermissionTo($notApprove);
        }

        $teamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($teamLeader) {
            $teamLeader->givePermissionTo($notApprove);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo(['equipment_requests.view', 'equipment_requests.create', 'equipment_requests.approve', 'equipment_requests.receive', 'equipment_requests.download_pdf']);
        });
    }
};
