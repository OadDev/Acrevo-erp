@props(['href', 'active' => false, 'icon' => null, 'badge' => null])

<a
    href="{{ $href }}"
    {{ $attributes }}
    @class([
        'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $active,
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white' => ! $active,
    ])
>
    @if ($icon)
        <x-icon :name="$icon" class="h-4.5 w-4.5 shrink-0" />
    @endif
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-semibold text-white">{{ $badge }}</span>
    @endif
</a>
