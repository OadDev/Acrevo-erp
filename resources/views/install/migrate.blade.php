<x-install-layout>
    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-indigo-600">Step 3 of 4</p>
    <h1 class="mb-4 text-lg font-semibold text-gray-900">Database Setup</h1>

    @if ($output === null)
        <p class="mb-6 text-sm text-gray-600">
            Ready to create tables and seed the default departments, roles,
            and permissions. This is safe to run once.
        </p>
        <form method="POST" action="{{ route('install.migrate.run', ['token' => $token]) }}">
            <x-primary-button class="w-full justify-center">Run Migrations</x-primary-button>
        </form>
    @else
        <p class="mb-3 text-sm text-emerald-600">Database is set up.</p>
        <pre class="mb-6 max-h-64 overflow-y-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-200">{{ trim($output) }}</pre>
        <a
            href="{{ route('install.admin', ['token' => $token]) }}"
            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
        >
            Continue
        </a>
    @endif
</x-install-layout>
