<x-guest-layout>
    <div class="mb-8">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Sign in to your account</h2>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Enter your details to access the dashboard.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Email address" />
            <div class="relative mt-1.5">
                <x-icon name="mail" class="pointer-events-none absolute left-3 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-gray-400" />
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="block w-full rounded-lg border-gray-300 py-2.5 pl-10 text-sm shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    placeholder="you@company.com">
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div x-data="{ show: false }">
            <x-input-label for="password" value="Password" />
            <div class="relative mt-1.5">
                <x-icon name="lock" class="pointer-events-none absolute left-3 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-gray-400" />
                <input :type="show ? 'text' : 'password'" id="password" name="password" required autocomplete="current-password"
                    class="block w-full rounded-lg border-gray-300 py-2.5 pl-10 pr-10 text-sm shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" tabindex="-1">
                    <x-icon x-show="!show" name="eye" class="h-4.5 w-4.5" />
                    <x-icon x-show="show" name="eye-off" class="h-4.5 w-4.5" x-cloak />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me + Forgot -->
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <input id="remember_me" type="checkbox" name="remember"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                Remember me
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                    Forgot password?
                </a>
            @endif
        </div>

        <x-primary-button class="w-full justify-center py-2.5 text-sm">
            Sign in
        </x-primary-button>
    </form>
</x-guest-layout>
