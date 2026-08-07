@props(['label'])

<div>
    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $label }}</p>
    <div class="mt-2 space-y-0.5">
        {{ $slot }}
    </div>
</div>
