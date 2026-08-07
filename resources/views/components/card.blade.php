@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 ' . ($padded ? 'p-5' : '')]) }}>
    {{ $slot }}
</div>
