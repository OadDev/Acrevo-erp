<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Geethan Works ERP') }}</title>

        <script>
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased dark:text-gray-100" x-data x-cloak>
        @php
            $loginSetting = \App\Models\LoginPageSetting::current();
            $backgroundUrl = $loginSetting->backgroundImage()?->getUrl();
        @endphp

        @if ($backgroundUrl)
            <div class="relative min-h-screen overflow-hidden">
                <div class="absolute inset-0">
                    <img src="{{ $backgroundUrl }}" alt="" class="h-full w-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-slate-950/60 to-slate-950/25"></div>
                </div>

                <div class="relative z-10 flex min-h-screen flex-col lg:flex-row">
                    <!-- Brand panel -->
                    <div class="hidden w-1/2 flex-col justify-between p-12 text-white lg:flex">
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                            <x-brand-mark size="md" class="text-white [&_span]:text-white [&_span.text-gray-400]:text-indigo-200" />
                        </a>

                        <div class="max-w-md space-y-6">
                            <div>
                                <h1 class="text-3xl font-semibold leading-tight tracking-tight">Built for the people running the work.</h1>
                                <p class="mt-4 text-base leading-relaxed text-white/85">
                                    One place to manage clients, work orders, teams, and equipment &mdash; from site to office.
                                </p>
                            </div>
                            <x-login-offer :setting="$loginSetting" />
                        </div>

                        <p class="text-sm text-white/70">&copy; {{ now()->year }} {{ config('app.name', 'Geethan Works') }}. All rights reserved.</p>
                    </div>

                    <!-- Form panel -->
                    <div class="flex w-full flex-1 flex-col items-center justify-center px-6 py-12 lg:w-1/2">
                        <div class="w-full max-w-sm">
                            <a href="{{ route('dashboard') }}" class="mb-6 inline-flex items-center gap-3 lg:hidden">
                                <x-brand-mark size="sm" class="text-white [&_span]:text-white [&_span.text-gray-400]:text-white/70" />
                            </a>

                            <x-login-offer :setting="$loginSetting" class="mb-6 lg:hidden" />

                            <div class="rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 sm:p-8">
                                {{ $slot }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="flex min-h-screen bg-gray-50 dark:bg-gray-950">
                <!-- Brand panel -->
                <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-700 via-indigo-800 to-slate-900 p-12 text-white lg:flex">
                    <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 28px 28px;"></div>

                    <a href="{{ route('dashboard') }}" class="relative z-10 inline-flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                        <x-brand-mark size="md" class="text-white [&_span]:text-white [&_span.text-gray-400]:text-indigo-200" />
                    </a>

                    <div class="relative z-10 max-w-md space-y-6">
                        <div>
                            <h1 class="text-3xl font-semibold leading-tight tracking-tight">Built for the people running the work.</h1>
                            <p class="mt-4 text-base leading-relaxed text-indigo-100/90">
                                One place to manage clients, work orders, teams, and equipment &mdash; from site to office.
                            </p>
                        </div>
                        <x-login-offer :setting="$loginSetting" />
                    </div>

                    <p class="relative z-10 text-sm text-indigo-200/80">&copy; {{ now()->year }} {{ config('app.name', 'Geethan Works') }}. All rights reserved.</p>
                </div>

                <!-- Form panel -->
                <div class="flex w-full flex-1 flex-col items-center justify-center px-6 py-12 lg:w-1/2">
                    <div class="w-full max-w-sm">
                        <a href="{{ route('dashboard') }}" class="mb-8 inline-flex items-center gap-3 lg:hidden">
                            <x-brand-mark size="sm" />
                        </a>

                        <x-login-offer :setting="$loginSetting" variant="plain" class="mb-6 lg:hidden" />

                        {{ $slot }}
                    </div>
                </div>
            </div>
        @endif
    </body>
</html>
