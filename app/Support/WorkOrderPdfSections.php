<?php

namespace App\Support;

class WorkOrderPdfSections
{
    /**
     * Single source of truth for the WO tabs that can be exported to PDF,
     * shared by the tab bar's dropdown, the section-PDF route validation,
     * and the "full work order" / "whole site" exports that render every
     * section in this order.
     */
    public const SECTIONS = [
        'site' => 'Site',
        'overview' => 'Overview',
        'team' => 'Team',
        'checklist' => 'Daily Work with Checklist',
        'progress' => 'Progress & Media',
        'materials' => 'Material Inward and Daily Material Used Entry',
        'manpower' => 'Used Man Power Budget',
        'mb' => 'Measurement Book',
        'summary' => 'Monthly Summary',
        'ledger' => 'Site Ledger',
        'company-ledger' => 'Company Ledger',
        'qc' => 'QC',
        'approvals' => 'Approval Requests',
        'tickets' => 'Tickets',
    ];

    public static function forUser($user): array
    {
        $sections = self::SECTIONS;

        if (! $user || ! $user->hasAnyRole(['Admin', 'Finance'])) {
            unset($sections['company-ledger']);
        }

        return $sections;
    }
}
