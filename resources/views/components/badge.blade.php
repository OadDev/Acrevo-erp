@props(['status' => null, 'color' => null])

@php
    $palette = match (true) {
        $color !== null => $color,
        in_array($status, ['completed', 'passed', 'qc_passed', 'paid', 'approved', 'resolved', 'closed', 'active', 'present', 'verified', 'verified_ok', 'available', 'repaired', 'returned', 'confirmed']) => 'emerald',
        in_array($status, ['pending_hr_assignment', 'draft', 'new', 'pending', 'open', 'scheduled', 'sent', 'retired', 'requested']) => 'gray',
        in_array($status, ['team_assigned', 'in_progress', 'contacted', 'site_visit_scheduled', 'quoted', 'partial', 'half_day', 'in_use', 'ready_for_return', 'expiring_soon', 'dispatched', 'received']) => 'blue',
        in_array($status, ['qc_pending', 'client_review', 'final_qc', 'in_progress', 'submitted', 'under_repair', 'purchase_required']) => 'amber',
        in_array($status, ['qc_failed', 'ticket_raised', 'rework_in_progress', 'failed', 'rejected', 'cancelled', 'overdue', 'lost', 'absent', 'critical', 'high', 'urgent', 'damaged', 'missing', 'not_found', 'expired']) => 'rose',
        default => 'gray',
    };

    $classes = [
        'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'gray' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        'blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
        'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'indigo' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',
    ][$palette];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium $classes"]) }}>
    {{ $slot->isEmpty() ? \Illuminate\Support\Str::of($status)->replace('_', ' ')->title() : $slot }}
</span>
