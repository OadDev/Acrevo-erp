<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf._fonts')
    <style>
        body { font-family: 'DejaVu Sans', 'Noto Sans Tamil', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 4px; color: #374151; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 9px; color: #6b7280; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 9px; font-weight: bold; }
        .badge-verified { background: #d1fae5; color: #047857; }
        .badge-submitted { background: #dbeafe; color: #1d4ed8; }
        .badge-pending { background: #f3f4f6; color: #4b5563; }
        .badge-retasked { background: #fee2e2; color: #991b1b; }
        .badge-overdue { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Task Performance Report</p>
    <p class="muted">
        Filters:
        @if ($filterUser) User: {{ $filterUser->name }} &middot; @endif
        @if ($filterFrom) From: {{ \Carbon\Carbon::parse($filterFrom)->format('d M Y') }} &middot; @endif
        @if ($filterTo) To: {{ \Carbon\Carbon::parse($filterTo)->format('d M Y') }} &middot; @endif
        @if ($status) Status: {{ Str::title($status) }} &middot; @endif
        Generated {{ now()->timezone('Asia/Kolkata')->format('d-M-Y, h:i A') }} IST
    </p>

    <h2>Performance Summary by User</h2>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th class="text-right">Total</th>
                <th class="text-right">Verified</th>
                <th class="text-right">Submitted</th>
                <th class="text-right">Pending</th>
                <th class="text-right">Overdue</th>
                <th class="text-right">Retasked</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($performance as $row)
                <tr>
                    <td>{{ $row['user']?->name ?? '—' }}</td>
                    <td class="text-right">{{ $row['total'] }}</td>
                    <td class="text-right">{{ $row['verified'] }}</td>
                    <td class="text-right">{{ $row['submitted'] }}</td>
                    <td class="text-right">{{ $row['pending'] }}</td>
                    <td class="text-right">{{ $row['overdue'] }}</td>
                    <td class="text-right">{{ $row['retasked'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No tasks match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Task Details</h2>
    <table>
        <thead>
            <tr>
                <th>Assigned To</th>
                <th>Title</th>
                <th>Assigned By</th>
                <th>Due Date</th>
                <th>Completed</th>
                <th>Verified</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tasks as $task)
                <tr>
                    <td>{{ $task->assignedTo?->name ?? '—' }}</td>
                    <td>{{ $task->title }}</td>
                    <td>{{ $task->assignedBy?->name ?? 'System (Calendar)' }}</td>
                    <td>{{ $task->due_date->format('d M Y') }}</td>
                    <td>{{ $task->completed_at?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $task->verified_at?->format('d M Y') ?? '—' }}</td>
                    <td><span class="badge badge-{{ $task->isOverdue() ? 'overdue' : $task->status }}">{{ $task->isOverdue() ? 'Overdue' : Str::title($task->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7">No tasks match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
