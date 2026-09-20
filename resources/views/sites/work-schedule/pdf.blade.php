<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #374151; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
    <table style="border: none; margin-bottom: 8px;">
        <tr style="border: none;">
            <td style="border: none; width: 60%;">
                <h1>{{ config('app.name') }}</h1>
                <p class="muted">Site Work Schedule — {{ $site->client?->name ?? 'Removed client' }}</p>
            </td>
            <td style="border: none; width: 40%; text-align: right;">
                <span class="badge">{{ $site->site_no }}</span>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 16px;">
        <tr><td>Project Start (Day 1)</td><td>{{ $projectStart?->format('d M Y') ?? '—' }}</td></tr>
        <tr><td>Projected Completion</td><td>{{ $projectedCompletion?->format('d M Y') ?? '—' }}</td></tr>
        <tr><td>Total Duration</td><td>{{ $projectStart && $projectedCompletion ? $projectStart->diffInDays($projectedCompletion) + 1 : 0 }} days</td></tr>
    </table>

    @if ($schedules->isEmpty())
        <p class="muted">No work schedules recorded for this site.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>S.No</th><th>Work</th><th>Day</th><th>Date</th>
                    <th class="text-right">Original Days</th><th class="text-right">Revised Days</th>
                    <th>Original End</th><th>Revised End</th>
                    <th>Actual Start</th><th>Actual End</th><th class="text-right">Actual Duration</th>
                    <th class="text-right">Previous Work Delay</th><th class="text-right">Own Delay</th>
                    <th>Progress</th><th>Status</th><th>Delay / Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schedules as $schedule)
                    @php
                        [$dayStart, $dayEnd] = $schedule->dayRange();
                        $previousDelay = $schedule->previousWorkDelayDays();
                        $ownDelay = $schedule->ownDelayDays();
                        $actualDuration = $schedule->actualDurationDays();
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $schedule->work_name }}{{ $schedule->schedule_mode === 'depends_on' && $schedule->dependsOn ? ' (after '.$schedule->dependsOn->work_name.')' : '' }}</td>
                        <td>Day {{ $dayStart }}{{ $dayEnd !== $dayStart ? '–'.$dayEnd : '' }}</td>
                        <td>{{ $schedule->revised_start_date->format('d/m') }}–{{ $schedule->revised_end_date->format('d/m/y') }}</td>
                        <td class="text-right">{{ $schedule->original_duration_days }}d</td>
                        <td class="text-right">{{ $schedule->revised_duration_days }}d</td>
                        <td>{{ $schedule->original_end_date->format('d M Y') }}</td>
                        <td>{{ $schedule->revised_end_date->format('d M Y') }}</td>
                        <td>{{ $schedule->actual_start_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $schedule->actual_end_date?->format('d M Y') ?? '—' }}</td>
                        <td class="text-right">{{ $actualDuration !== null ? $actualDuration.'d' : '—' }}</td>
                        <td class="text-right">{{ $previousDelay > 0 ? '+'.$previousDelay : $previousDelay }}d</td>
                        <td class="text-right">{{ $ownDelay > 0 ? '+'.$ownDelay : $ownDelay }}d</td>
                        <td>{{ $schedule->actual_progress_percent !== null ? $schedule->actual_progress_percent.'%' : '—' }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $schedule->status)) }}{{ $schedule->isDelayed() ? ' (Delayed)' : '' }}</td>
                        <td>{{ $schedule->delay_reason ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
