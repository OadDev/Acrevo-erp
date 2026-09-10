@props(['setting', 'variant' => 'overlay'])

@php
    // 'overlay' sits on a photo or a dark gradient panel, so it always
    // uses light text regardless of the site's own light/dark toggle.
    // 'plain' sits on the page's own background (the no-photo fallback on
    // mobile), so it follows the site's theme like every other card.
    $toneClasses = $variant === 'plain'
        ? 'border-indigo-100 bg-indigo-50 dark:border-indigo-500/20 dark:bg-indigo-500/10'
        : 'border-white/25 bg-white/10 backdrop-blur-sm';
    $titleClasses = $variant === 'plain' ? 'text-indigo-600 dark:text-indigo-400' : 'text-amber-300';
    $bodyClasses = $variant === 'plain' ? 'text-gray-600 dark:text-gray-300' : 'text-white/90';
@endphp

@if ($setting->hasActiveOffer())
    <div {{ $attributes->merge(['class' => "rounded-xl border p-4 $toneClasses"]) }}>
        @if ($setting->offer_title)
            <p class="text-xs font-semibold uppercase tracking-wide {{ $titleClasses }}">{{ $setting->offer_title }}</p>
        @endif
        @if ($setting->offer_body)
            <p class="{{ $setting->offer_title ? 'mt-1' : '' }} whitespace-pre-line text-sm leading-relaxed {{ $bodyClasses }}">{{ $setting->offer_body }}</p>
        @endif
    </div>
@endif
