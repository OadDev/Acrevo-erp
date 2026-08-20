<x-install-layout>
    <div class="text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
            <x-icon name="check-circle" class="h-7 w-7" />
        </div>
        <h1 class="mb-2 text-lg font-semibold text-gray-900">{{ config('app.name') ?: 'Geethan Works ERP' }} is ready</h1>
        <p class="mb-6 text-sm text-gray-600">
            Setup is complete. This installer is now locked and will refuse
            to run again unless <code class="rounded bg-gray-100 px-1">storage/installed</code>
            is removed on the server.
        </p>
        <a
            href="{{ route('login') }}"
            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
        >
            Go to Login
        </a>
    </div>
</x-install-layout>
