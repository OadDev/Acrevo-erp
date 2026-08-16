<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Welcome back, {{ explode(' ', auth()->user()->name)[0] }}
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Here's what's happening across your work orders today.</p>
    </x-slot>

    <div class="space-y-8">
        @forelse ($widgets as $widget)
            <div>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">{{ $widget['label'] }}</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    @foreach ($widget['cards'] as $card)
                        <x-card class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $card['value'] }}</p>
                            </div>
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                                <x-icon :name="$card['icon']" class="h-5 w-5" />
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </div>
        @empty
            <x-empty-state icon="layers" title="No dashboard widgets available" description="Your role doesn't have any modules assigned yet. Contact your administrator." />
        @endforelse

        @if ($myAttendance !== null)
            <div>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">My Attendance &amp; Payroll — {{ now()->format('F Y') }}</h3>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-card :padded="false" class="lg:col-span-2">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2">Date</th>
                                        <th class="px-4 py-2">Work Order</th>
                                        <th class="px-4 py-2">Hours</th>
                                        <th class="px-4 py-2 text-right">Salary</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($myAttendance as $record)
                                        <tr>
                                            <td class="px-4 py-2">{{ $record->date->format('d M') }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $record->workOrder?->work_order_no ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $record->hours_worked ?? '—' }}</td>
                                            <td class="px-4 py-2 text-right font-medium">{{ $record->salary ? '₹'.number_format($record->salary, 2) : '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No attendance recorded yet this month.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                    <x-card>
                        <p class="text-sm text-gray-500 dark:text-gray-400">This Month's Payroll</p>
                        @if ($myPayroll)
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">₹{{ number_format($myPayroll->net_salary, 2) }}</p>
                            <x-badge :status="$myPayroll->status" class="mt-2" />
                            @if ($myPayroll->status === 'paid')
                                <p class="mt-2 text-xs text-gray-400">Paid on {{ $myPayroll->paid_at?->format('d M Y') }}</p>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-gray-400">Not processed yet.</p>
                        @endif
                    </x-card>
                </div>
            </div>
        @endif

        @if ($recentActivity->isNotEmpty())
            <div>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">Recent Activity</h3>
                <x-card :padded="false">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($recentActivity as $activity)
                            <li class="flex items-center justify-between px-5 py-3 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $activity->causer?->name ?? 'System' }}</span>
                                    {{ $activity->description }}
                                    {{ class_basename($activity->subject_type ?? '') }}
                                </span>
                                <span class="shrink-0 text-xs text-gray-400">{{ $activity->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            </div>
        @endif
    </div>
</x-app-layout>
