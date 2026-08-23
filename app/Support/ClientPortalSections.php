<?php

namespace App\Support;

class ClientPortalSections
{
    /**
     * Single source of truth for the sections an Admin can individually
     * show/hide per client on the client portal's work order page.
     */
    public const SECTIONS = [
        'media' => 'Progress Photos & Videos',
        'checklist' => 'Daily Work & Checklist',
        'progress' => 'Progress Updates',
        'summary' => 'Monthly Summary',
        'ledger' => 'Site Ledger',
        'mb' => 'Measurement Book',
        'materials' => 'Material Inward',
        'material_usage' => 'Used Material',
        'manpower' => 'Used Manpower',
        'company_ledger' => 'Company Ledger',
        'qc' => 'QC',
        'approvals' => 'Approval Requests',
        'tickets' => 'Tickets',
    ];
}
