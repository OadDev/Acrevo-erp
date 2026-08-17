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

    /**
     * The Monthly Summary log is entered by the office, not the site team -
     * only Sales, HR, and Admin can add or correct rows in it.
     */
    protected function authorizeSummaryEditors(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['Admin', 'Sales', 'HR']), 403, 'Only Sales, HR, and Admin can edit the work order summary.');
    }
}
