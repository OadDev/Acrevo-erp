<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Site Work Schedule Planner — Overall Schedule — as of {{ now()->format('d M Y') }}</p>

    @if ($sites->isEmpty())
        <p class="muted">No scheduled sites match the applied filters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>S.No</th><th>Site</th><th>Client</th><th class="text-right">Works</th>
                    <th>Start (Day 1)</th><th>Original Completion</th><th>Revised Completion</th>
                    <th class="text-right">Total Duration</th><th class="text-right">Delayed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sites as $site)
                    @php
                        $start = $site->workSchedules->min('original_start_date');
                        $originalEnd = $site->workSchedules->max('original_end_date');
                        $revisedEnd = $site->workSchedules->max('revised_end_date');
                        $delayedCount = $site->workSchedules->filter->isDelayed()->count();
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $site->site_no }}</td>
                        <td>{{ $site->client?->name ?? 'Removed client' }}</td>
                        <td class="text-right">{{ $site->workSchedules->count() }}</td>
                        <td>{{ $start?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $originalEnd?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $revisedEnd?->format('d M Y') ?? '—' }}</td>
                        <td class="text-right">{{ $start && $revisedEnd ? $start->diffInDays($revisedEnd) + 1 : 0 }}d</td>
                        <td class="text-right">{{ $delayedCount }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
