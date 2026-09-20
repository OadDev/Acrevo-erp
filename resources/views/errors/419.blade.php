<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Session Expired &middot; {{ config('app.name', 'Geethan Works ERP') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-gray-50 px-4 font-sans antialiased dark:bg-gray-950">
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                <x-icon name="clock" class="h-6 w-6" />
            </div>
            <h1 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Your session has expired</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                For your security, we signed you out after a period of inactivity. Please log in again to continue.
            </p>
            <a href="{{ route('login') }}"
                class="mt-6 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Go to Login
            </a>
            <p class="mt-3 text-xs text-gray-400" id="redirect-note">Redirecting you automatically in <span id="redirect-seconds">4</span>s&hellip;</p>
        </div>

        <script>
            (function () {
                var seconds = 4;
                var el = document.getElementById('redirect-seconds');
                var timer = setInterval(function () {
                    seconds -= 1;
                    if (el) el.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(timer);
                        window.location.replace(@json(route('login')));
                    }
                }, 1000);
            })();
        </script>
    </body>
</html>
