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

    /**
     * The Company Ledger tracks company-side expenses against a work order
     * and is restricted to Finance and Admin only - no other role should
     * even see it exists.
     */
    protected function authorizeFinanceOrAdmin(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['Admin', 'Finance']), 403, 'Only Finance and Admin can access the company ledger.');
    }
}
