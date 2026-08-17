<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Editing/removing already-recorded records is restricted to Admins,
     * so a mistaken entry elsewhere can't quietly rewrite site history.
     */
    protected function authorizeAdminOnly(): void
    {
        abort_unless(Auth::user()?->hasRole('Admin'), 403, 'Only Admins can edit or remove this record.');
    }
}
