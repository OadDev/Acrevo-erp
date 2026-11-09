<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new Ticket permissions directly to existing production
     * roles (rather than re-running the full seeder, which would wipe any
     * role customization already made in production).
     *
     * tickets.view/create/manage are replaced by six independent
     * permissions: tickets.all_view/assigned_view/assigned_create/
     * assigned_manage/assigned_edit/assigned_remove.
     *
     * The old tickets.view permission gated every ticket route, including
     * Create and Manage actions - so a role holding Create/Manage but not
     * View got a 403 on those actions too, even though it held the
     * permission that was supposed to govern them. tickets.view also let
     * anyone holding it comment on any ticket (comments.store was in the
     * tickets.view route group, not tickets.manage), independently of
     * whether they could edit/delete it. The six new permissions are each
     * gated by their own route, so Create/Manage/Edit/Remove no longer
     * depend on also holding a View permission.
     *
     * tickets.view/create/manage are left in the permissions table -
     * nothing checks them any more, and deleting them would also delete
     * the role_has_permissions rows that reference them, which isn't
     * worth the churn.
     */
    private array $permissions = [
        'tickets.all_view', 'tickets.assigned_view', 'tickets.assigned_create',
        'tickets.assigned_manage', 'tickets.assigned_edit', 'tickets.assigned_remove',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Full rights - same net access these roles already had (Admin via
        // its all-permissions role, Sales via its own tickets=>'*' grant).
        foreach (['Admin', 'Sales'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($this->permissions);
            }
        }

        // Global view (same as before), create/comment/edit scoped to
        // their own assigned Work Orders - no remove, same restriction
        // this role always had.
        $executiveTeamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        if ($executiveTeamLeader) {
            $executiveTeamLeader->givePermissionTo([
                'tickets.all_view', 'tickets.assigned_create', 'tickets.assigned_manage', 'tickets.assigned_edit',
            ]);
        }

        // Global view plus comment - these roles could already comment on
        // any ticket under the old view-gated comments route, so this
        // preserves that net access.
        foreach (['QC Officer', 'Management'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(['tickets.all_view', 'tickets.assigned_manage']);
            }
        }

        $demo = Role::where('name', 'Demo')->where('guard_name', 'web')->first();
        if ($demo) {
            $demo->givePermissionTo($this->permissions);
        }
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo($this->permissions);
        });
    }
};
