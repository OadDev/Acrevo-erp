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
        'attendance' => 'Worker Attendance',
        'summary' => 'Monthly Summary',
        'ledger' => 'Site Ledger',
        'company-ledger' => 'Company Ledger',
        'qc' => 'QC',
        'approvals' => 'Approval Requests',
        'tickets' => 'Tickets',
    ];

    /**
     * Each WO sub-module section whose tab/export visibility is gated by
     * its own dedicated permission, independently of the others.
     */
    private const PERMISSION_GATED_SECTIONS = [
        'overview' => 'work_orders.overview_view',
        'checklist' => 'daily_checklist.view',
        'progress' => 'daily_progress.view',
        'materials' => 'wo_materials.view',
        'manpower' => 'wo_manpower.view',
        'mb' => 'wo_measurement_book.view',
        'attendance' => 'wo_attendance.view',
        'summary' => 'work_orders.summary_view',
        'ledger' => 'wo_ledger.view',
        'approvals' => 'approval_requests.view',
    ];

    public static function forUser($user): array
    {
        $sections = self::SECTIONS;

        if (! $user || ! $user->hasAnyRole(['Admin', 'Finance'])) {
            unset($sections['company-ledger']);
        }

        foreach (self::PERMISSION_GATED_SECTIONS as $section => $permission) {
            if (! $user || ! $user->can($permission)) {
                unset($sections[$section]);
            }
        }

        return $sections;
    }
}
