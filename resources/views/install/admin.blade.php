<x-install-layout>
    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-indigo-600">Step 4 of 4</p>
    <h1 class="mb-4 text-lg font-semibold text-gray-900">Create Your Admin Account</h1>

    @if ($error)
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $error }}
        </div>
    @endif

    <form method="POST" action="{{ route('install.admin.save', ['token' => $token]) }}" class="space-y-4">
        <div>
            <x-input-label value="Full Name" />
            <x-text-input name="name" class="mt-1 block w-full" required autofocus />
        </div>

        <div>
            <x-input-label value="Email" />
            <x-text-input type="email" name="email" class="mt-1 block w-full" required />
        </div>

        <div>
            <x-input-label value="Password" />
            <x-text-input type="password" name="password" class="mt-1 block w-full" required minlength="8" />
        </div>

        <div>
            <x-input-label value="Confirm Password" />
            <x-text-input type="password" name="password_confirmation" class="mt-1 block w-full" required minlength="8" />
        </div>

        <x-primary-button class="w-full justify-center">Create Account &amp; Finish</x-primary-button>
    </form>
</x-install-layout>
