<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Quotation;
use App\Models\QcInspection;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('Client')) {
            abort_unless($user->client(), 403, 'This login has the Client role but is not linked to a Client record. Ask an Admin to fix this via the Client\'s page ("Generate Portal Access").');

            return redirect()->route('portal.work-orders.index');
        }

        if ($user->hasRole('Sub Contractor')) {
            return redirect()->route('my-work-orders.index');
        }

        $widgets = [];

        if ($user->can('enquiries.view')) {
            $widgets['sales'] = [
                'label' => 'Sales & Marketing',
                'cards' => [
                    ['label' => 'New Enquiries', 'value' => Enquiry::where('status', 'new')->count(), 'icon' => 'inbox'],
                    ['label' => 'Quotations Pending', 'value' => Quotation::where('status', 'sent')->count(), 'icon' => 'file-text'],
                    ['label' => 'Ongoing Projects', 'value' => WorkOrder::whereNotIn('status', ['completed', 'cancelled'])->count(), 'icon' => 'clipboard'],
                    ['label' => 'QC Pending', 'value' => WorkOrder::where('status', 'qc_pending')->count(), 'icon' => 'shield-check'],
                    ['label' => 'Revenue (This Month)', 'value' => '₹'.number_format(Payment::whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->sum('amount'), 0), 'icon' => 'banknote'],
                ],
            ];
        }

        if ($user->can('employees.view')) {
            $widgets['hr'] = [
                'label' => 'Human Resources',
                'cards' => [
                    ['label' => 'Active Workers', 'value' => Employee::where('status', 'active')->count(), 'icon' => 'users'],
                    ['label' => 'Present Today', 'value' => Employee::whereHas('attendances', fn ($q) => $q->whereDate('date', today())->where('status', 'present'))->count(), 'icon' => 'calendar-check'],
                    ['label' => 'Payroll Pending', 'value' => Payroll::where('status', 'pending')->count(), 'icon' => 'wallet'],
                    ['label' => 'Executive Teams', 'value' => \App\Models\ExecutiveTeam::where('is_active', true)->count(), 'icon' => 'users-round'],
                ],
            ];
        }

        if ($user->can('assigned_work.view')) {
            $employeeId = $user->employee?->id;

            $myWorkOrderIds = WorkOrder::whereHas('executiveTeams', function ($q) use ($employeeId, $user) {
                $q->whereNull('unassigned_at')->whereHas('executiveTeam', function ($q2) use ($employeeId, $user) {
                    $q2->where('team_leader_id', $user->id)
                        ->orWhereHas('members', fn ($q3) => $q3->where('employee_id', $employeeId));
                });
            })->pluck('id');

            $widgets['executive'] = [
                'label' => 'Executive Team',
                'cards' => [
                    ['label' => "Today's Checklists", 'value' => \App\Models\DailyChecklist::whereIn('work_order_id', $myWorkOrderIds)->whereDate('date', today())->count(), 'icon' => 'clipboard'],
                    ['label' => 'Pending Work Orders', 'value' => WorkOrder::whereIn('id', $myWorkOrderIds)->whereNotIn('status', ['completed', 'cancelled'])->count(), 'icon' => 'hard-hat'],
                    ['label' => 'Completed Work Orders', 'value' => WorkOrder::whereIn('id', $myWorkOrderIds)->where('status', 'completed')->count(), 'icon' => 'check-circle'],
                ],
            ];
        }

        if ($user->can('qc.view')) {
            $widgets['qc'] = [
                'label' => 'Quality Control',
                'cards' => [
                    ['label' => 'Pending QC', 'value' => QcInspection::where('status', 'pending')->count(), 'icon' => 'shield-check'],
                    ['label' => 'Passed QC', 'value' => QcInspection::where('status', 'passed')->count(), 'icon' => 'check-circle'],
                    ['label' => 'Failed / Rework', 'value' => QcInspection::whereIn('status', ['failed', 'rework_required'])->count(), 'icon' => 'alert-triangle'],
                ],
            ];
        }

        if ($user->can('finance.view') || $user->can('company_records.view')) {
            $revenue = Payment::whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->sum('amount');
            $expenses = \App\Models\Expense::whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount');

            $widgets['management'] = [
                'label' => 'Management',
                'cards' => [
                    ['label' => 'Revenue (This Month)', 'value' => '₹'.number_format($revenue, 0), 'icon' => 'banknote'],
                    ['label' => 'Expenses (This Month)', 'value' => '₹'.number_format($expenses, 0), 'icon' => 'wallet'],
                    ['label' => 'Profit (This Month)', 'value' => '₹'.number_format($revenue - $expenses, 0), 'icon' => 'bar-chart'],
                    ['label' => 'Delayed Projects', 'value' => WorkOrder::where('deadline', '<', today())->whereNotIn('status', ['completed', 'cancelled'])->count(), 'icon' => 'alert-triangle'],
                    ['label' => 'Pending Tickets', 'value' => Ticket::whereIn('status', ['open', 'in_progress'])->count(), 'icon' => 'ticket'],
                ],
            ];
        }

        if ($user->can('tickets.view') && ! isset($widgets['management'])) {
            $widgets['tickets'] = [
                'label' => 'Support',
                'cards' => [
                    ['label' => 'Open Tickets', 'value' => Ticket::where('status', 'open')->count(), 'icon' => 'ticket'],
                    ['label' => 'In Progress', 'value' => Ticket::where('status', 'in_progress')->count(), 'icon' => 'clock'],
                    ['label' => 'Resolved', 'value' => Ticket::where('status', 'resolved')->count(), 'icon' => 'check-circle'],
                ],
            ];
        }

        if ($user->hasRole('Admin')) {
            $widgets['admin'] = [
                'label' => 'System Overview',
                'cards' => [
                    ['label' => 'Total Users', 'value' => \App\Models\User::count(), 'icon' => 'user-cog'],
                    ['label' => 'Total Clients', 'value' => \App\Models\Client::count(), 'icon' => 'briefcase'],
                    ['label' => 'Total Work Orders', 'value' => WorkOrder::count(), 'icon' => 'clipboard'],
                    ['label' => 'Total Employees', 'value' => Employee::count(), 'icon' => 'users'],
                ],
            ];
        }

        $recentActivity = $user->can('activity_logs.view')
            ? \Spatie\Activitylog\Models\Activity::latest()->take(8)->get()
            : collect();

        $myAttendance = null;
        $myPayroll = null;
        // Any login linked to an HR Employee record sees their own
        // attendance/payroll here - Workers (via WO attendance) and the
        // employee-linked staff roles (Sales, HR, Finance, Executive Team
        // Leader, QC Officer) alike. An unregistered worker has no login at
        // all, so this naturally stays empty for them.
        if ($employee = $user->employee) {
            $myAttendance = Attendance::where('employee_id', $employee->id)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->with('workOrder')
                ->orderBy('date')
                ->get();

            $myPayroll = Payroll::where('employee_id', $employee->id)
                ->where('month', now()->month)
                ->where('year', now()->year)
                ->first();
        }

        return view('dashboard', compact('widgets', 'recentActivity', 'myAttendance', 'myPayroll'));
    }
}
