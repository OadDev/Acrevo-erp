@props(['size' => 'md'])

@php
    $logoPath = public_path('images/logo.png');
    $hasLogo = file_exists($logoPath);

    $allSizes = [
        'sm' => ['mark' => 'h-8 w-8', 'text' => 'text-base', 'sub' => 'text-[10px]'],
        'md' => ['mark' => 'h-10 w-10', 'text' => 'text-lg', 'sub' => 'text-xs'],
        'lg' => ['mark' => 'h-16 w-16', 'text' => 'text-2xl', 'sub' => 'text-sm'],
    ];
    $sizes = $allSizes[$size] ?? $allSizes['md'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    @if ($hasLogo)
        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name', 'Geethan Works') }}" class="{{ $sizes['mark'] }} w-auto object-contain">
    @else
        <span class="flex {{ $sizes['mark'] }} shrink-0 items-center justify-center rounded-xl bg-indigo-600 font-bold text-white {{ $sizes['text'] }}">GW</span>
    @endif
    <span class="flex flex-col leading-tight">
        <span class="font-semibold tracking-tight text-gray-900 dark:text-white {{ $sizes['text'] }}">{{ config('app.name', 'Geethan Works ERP') }}</span>
        @if (! $slot->isEmpty())
            <span class="font-medium text-gray-400 {{ $sizes['sub'] }}">{{ $slot }}</span>
        @endif
    </span>
</span>
