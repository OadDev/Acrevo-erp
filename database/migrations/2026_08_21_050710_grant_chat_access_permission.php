<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Grants the new chat.access permission directly to every existing
     * production role except Client (rather than re-running the full
     * seeder, which would wipe every user's role assignment in production).
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'chat.access', 'guard_name' => 'web']);

        Role::where('guard_name', 'web')->where('name', '!=', 'Client')->get()->each(function (Role $role) {
            if (! $role->hasPermissionTo('chat.access')) {
                $role->givePermissionTo('chat.access');
            }
        });
    }

    public function down(): void
    {
        Role::where('guard_name', 'web')->get()->each(function (Role $role) {
            $role->revokePermissionTo('chat.access');
        });
    }
};
