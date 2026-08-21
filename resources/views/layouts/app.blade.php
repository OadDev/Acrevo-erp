<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Geethan Works ERP') }}</title>

        <script>
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900 dark:text-gray-100" x-data x-cloak>
        <div class="flex min-h-screen bg-gray-50 dark:bg-gray-950">
            @include('layouts.partials.sidebar')

            <div class="flex min-w-0 flex-1 flex-col">
                @include('layouts.partials.topbar')

                @isset($header)
                    <div class="border-b border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-gray-900 sm:px-6">
                        {{ $header }}
                    </div>
                @endisset

                <main class="flex-1 px-4 py-6 sm:px-6">
                    @if (session('success'))
                        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @can('chat.access')
            <script>
                (function () {
                    function refreshChatBadge() {
                        fetch('{{ route('chat.unread-count') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function (r) { return r.ok ? r.json() : null; })
                            .then(function (data) {
                                if (!data) return;
                                var link = document.getElementById('nav-chats-link');
                                if (!link) return;
                                var badge = link.querySelector('span:last-child');
                                if (data.count > 0) {
                                    if (badge && badge !== link.querySelector('span:first-of-type')) {
                                        badge.textContent = data.count;
                                    } else {
                                        badge = document.createElement('span');
                                        badge.className = 'ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-semibold text-white';
                                        badge.textContent = data.count;
                                        link.appendChild(badge);
                                    }
                                } else if (badge && badge.classList.contains('bg-indigo-600')) {
                                    badge.remove();
                                }
                            })
                            .catch(function () {});
                    }
                    setInterval(refreshChatBadge, 20000);
                })();
            </script>
        @endcan

        @stack('scripts')
    </body>
</html>
