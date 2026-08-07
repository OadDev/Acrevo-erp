@props(['href', 'variant' => 'primary'])

@php
    $variants = [
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-500 shadow-sm',
        'secondary' => 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700',
    ][$variant];
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold transition $variants"]) }}>
    {{ $slot }}
</a>
