<x-install-layout>
    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-indigo-600">Step 1 of 4</p>
    <h1 class="mb-4 text-lg font-semibold text-gray-900">Welcome</h1>
    <p class="mb-6 text-sm text-gray-600">
        This wizard will configure your database, run migrations, seed the
        default roles and permissions, and create your admin account.
    </p>

    <ul class="mb-6 divide-y divide-gray-100 rounded-lg border border-gray-200">
        @foreach ($requirements as $label => $passed)
            <li class="flex items-center justify-between px-4 py-2.5 text-sm">
                <span class="text-gray-700">{{ $label }}</span>
                @if ($passed)
                    <span class="inline-flex items-center gap-1 text-emerald-600">
                        <x-icon name="check-circle" class="h-4 w-4" /> OK
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-rose-600">
                        <x-icon name="alert-triangle" class="h-4 w-4" /> Missing
                    </span>
                @endif
            </li>
        @endforeach
    </ul>

    @php($allPassed = ! in_array(false, $requirements, true))

    @if (! $allPassed)
        <p class="mb-4 text-sm text-rose-600">
            Fix the failing requirements above (install the missing PHP
            extension, or run <code class="rounded bg-gray-100 px-1">chmod -R 775 storage bootstrap/cache</code>
            over SSH), then reload this page.
        </p>
    @endif

    <a
        href="{{ $allPassed ? route('install.database', ['token' => $token]) : '#' }}"
        @class([
            'inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition',
            'bg-indigo-600 hover:bg-indigo-500' => $allPassed,
            'cursor-not-allowed bg-gray-300' => ! $allPassed,
        ])
    >
        Continue
    </a>
</x-install-layout>
