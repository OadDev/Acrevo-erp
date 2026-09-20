<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Activity Log Report — as of {{ now()->format('d M Y') }}</p>

    @if ($activities->isEmpty())
        <p class="muted">No activity matches the applied filters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Date / Time</th><th>User</th><th>Role</th><th>Action</th><th>Subject</th>
                    <th>Previous &rarr; New Value</th><th>WO / Site</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($activities as $activity)
                    @php
                        $old = collect($activity->properties->get('old', []));
                        $attributes = collect($activity->properties->get('attributes', []));
                        $role = $activity->properties->get('role');
                        $workOrderId = $activity->properties->get('work_order_id');
                        $workOrder = $workOrderId ? \App\Models\WorkOrder::find($workOrderId) : null;
                    @endphp
                    <tr>
                        <td>{{ $activity->created_at->format('d M Y, h:i A') }}</td>
                        <td>{{ $activity->causer?->name ?? 'System' }}</td>
                        <td>{{ $role ?: '—' }}</td>
                        <td>{{ $activity->description }}</td>
                        <td>{{ class_basename($activity->subject_type ?? '') }} #{{ $activity->subject_id }}</td>
                        <td>
                            @forelse ($attributes as $field => $value)
                                {{ $field }}: {{ is_array($old->get($field)) ? json_encode($old->get($field)) : ($old->get($field) ?? '—') }} &rarr; {{ is_array($value) ? json_encode($value) : $value }}<br>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>{{ $workOrder?->work_order_no ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
