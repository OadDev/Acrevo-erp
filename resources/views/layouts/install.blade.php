<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Setup · {{ config('app.name') ?: 'Acrevo ERP' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
        <div class="mx-auto flex min-h-screen max-w-xl flex-col justify-center px-4 py-12">
            <div class="mb-8 flex items-center justify-center gap-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">AC</div>
                <span class="text-xl font-semibold tracking-tight text-gray-900">Acrevo ERP Setup</span>
            </div>

            <x-card>
                {{ $slot }}
            </x-card>
        </div>
    </body>
</html>
