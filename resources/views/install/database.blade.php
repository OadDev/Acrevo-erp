<x-install-layout>
    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-indigo-600">Step 2 of 4</p>
    <h1 class="mb-4 text-lg font-semibold text-gray-900">Site &amp; Database</h1>

    @if ($error)
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $error }}
        </div>
    @endif

    <form method="POST" action="{{ route('install.database.save', ['token' => $token]) }}" class="space-y-4">
        <div>
            <x-input-label value="Site Name" />
            <x-text-input name="app_name" class="mt-1 block w-full" value="{{ $values['app_name'] }}" required />
        </div>

        <div>
            <x-input-label value="Site URL" />
            <x-text-input name="app_url" class="mt-1 block w-full" value="{{ $values['app_url'] }}" required />
        </div>

        <div class="border-t border-gray-100 pt-4">
            <x-input-label value="Database Driver" />
            <x-select-input name="db_connection" class="mt-1 block w-full">
                @foreach (['mysql' => 'MySQL / MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'] as $value => $label)
                    <option value="{{ $value }}" @selected($values['db_connection'] === $value)>{{ $label }}</option>
                @endforeach
            </x-select-input>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <x-input-label value="Database Host" />
                <x-text-input name="db_host" class="mt-1 block w-full" value="{{ $values['db_host'] }}" placeholder="127.0.0.1" />
            </div>
            <div>
                <x-input-label value="Port" />
                <x-text-input name="db_port" class="mt-1 block w-full" value="{{ $values['db_port'] }}" placeholder="3306" />
            </div>
        </div>

        <div>
            <x-input-label value="Database Name" />
            <x-text-input name="db_database" class="mt-1 block w-full" value="{{ $values['db_database'] }}" required placeholder="u761085554_acrevo (or database/database.sqlite for SQLite)" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label value="Database Username" />
                <x-text-input name="db_username" class="mt-1 block w-full" value="{{ $values['db_username'] }}" />
            </div>
            <div>
                <x-input-label value="Database Password" />
                <x-text-input type="password" name="db_password" class="mt-1 block w-full" />
            </div>
        </div>

        <x-primary-button class="w-full justify-center">Test Connection &amp; Continue</x-primary-button>
    </form>
</x-install-layout>
