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
     * Gates correcting (editing/removing) an already-recorded Work Order
     * entry behind the same permission that already governs creating that
     * kind of entry - whoever a role lets create a Daily Checklist/Material
     * entry/etc. can also fix their own mistake in it, instead of every
     * correction being hardcoded to the Admin role regardless of what
     * permissions a role (built-in or custom) actually holds. Admin is
     * covered automatically, since the Admin role holds every permission.
     */
    protected function authorizeAnyPermission(string ...$permissions): void
    {
        $user = Auth::user();

        abort_unless($user && collect($permissions)->contains(fn ($permission) => $user->can($permission)), 403, 'You do not have permission to edit or remove this record.');
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
     * gated behind its own permission (granted to Sales, HR, and Executive
     * Team Leader by default) rather than a hardcoded role list, so Admin
     * can extend it to another role without assigning Executive Team
     * Leader itself.
     */
    protected function authorizeSummaryEditors(): void
    {
        abort_unless(Auth::user()?->can('work_orders.summary_manage'), 403, 'You do not have permission to edit the work order summary.');
    }
}
