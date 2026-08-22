<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Mail Settings" subtitle="Configure the SMTP server used to send password resets and other emails" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.settings.mail.update') }}">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <x-input-label for="mailer" value="Mail Driver" />
                    <x-select-input id="mailer" name="mailer" class="mt-1 block w-full" required onchange="document.getElementById('smtp-fields').classList.toggle('hidden', this.value !== 'smtp')">
                        <option value="log" @selected(old('mailer', $settings['mailer']) === 'log')>Log only (no real emails sent — for testing)</option>
                        <option value="smtp" @selected(old('mailer', $settings['mailer']) === 'smtp')>SMTP (send real emails)</option>
                    </x-select-input>
                    <p class="mt-1 text-xs text-gray-400">Password reset and other emails silently go nowhere until this is set to SMTP with valid details below.</p>
                </div>

                <div id="smtp-fields" class="space-y-4 {{ old('mailer', $settings['mailer']) === 'smtp' ? '' : 'hidden' }}">
                    <div>
                        <x-input-label for="host" value="SMTP Host" />
                        <x-text-input id="host" name="host" class="mt-1 block w-full" placeholder="e.g. smtp.hostinger.com" value="{{ old('host', $settings['host']) }}" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="port" value="Port" />
                            <x-text-input id="port" type="number" name="port" class="mt-1 block w-full" placeholder="587" value="{{ old('port', $settings['port']) }}" />
                        </div>
                        <div>
                            <x-input-label for="encryption" value="Encryption" />
                            <x-select-input id="encryption" name="encryption" class="mt-1 block w-full">
                                <option value="tls" @selected(old('encryption', $settings['encryption']) === 'tls')>TLS</option>
                                <option value="ssl" @selected(old('encryption', $settings['encryption']) === 'ssl')>SSL</option>
                                <option value="" @selected(! old('encryption', $settings['encryption']))>None</option>
                            </x-select-input>
                        </div>
                    </div>
                    <div>
                        <x-input-label for="username" value="SMTP Username" />
                        <x-text-input id="username" name="username" class="mt-1 block w-full" placeholder="usually your full email address" value="{{ old('username', $settings['username']) }}" />
                    </div>
                    <div>
                        <x-input-label for="password" value="SMTP Password" />
                        <x-text-input id="password" type="password" name="password" class="mt-1 block w-full" :placeholder="$settings['has_password'] ? 'Leave blank to keep the saved password' : 'Enter password'" />
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
                    <x-input-label for="from_address" value="From Email Address" />
                    <x-text-input id="from_address" type="email" name="from_address" class="mt-1 block w-full" value="{{ old('from_address', $settings['from_address']) }}" required />
                </div>
                <div>
                    <x-input-label for="from_name" value="From Name" />
                    <x-text-input id="from_name" name="from_name" class="mt-1 block w-full" value="{{ old('from_name', $settings['from_name']) }}" required />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
            @csrf
            <p class="mb-2 text-xs text-gray-400">Sends a test email to your own address ({{ auth()->user()->email }}) using the settings currently saved above.</p>
            <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Send Test Email</button>
        </form>
    </x-card>
</x-app-layout>
