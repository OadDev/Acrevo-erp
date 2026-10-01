<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Pinned to database.main_connection (the real default, captured before any
 * switch) so role/permission checks stay correct even during a Demo-role
 * request, where SwitchDemoDatabaseConnection has moved the app's default
 * connection to the isolated demo database.
 */
class Permission extends SpatiePermission
{
    public function getConnectionName(): ?string
    {
        return config('database.main_connection');
    }
}
