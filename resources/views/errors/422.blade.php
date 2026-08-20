<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Geethan Works ERP') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-gray-50 px-4 font-sans antialiased dark:bg-gray-950">
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Can't do that right now</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $exception->getMessage() ?: 'This action could not be completed as submitted.' }}</p>
            <button onclick="history.back()" class="mt-5 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                Go Back
            </button>
        </div>
    </body>
</html>
