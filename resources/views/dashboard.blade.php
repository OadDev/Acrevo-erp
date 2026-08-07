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
