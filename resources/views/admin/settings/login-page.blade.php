<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Login Page" subtitle="Customize the background image and promotional content shown on the login page" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.settings.login-page.update') }}" enctype="multipart/form-data" x-data="{ remove: false }">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <x-input-label value="Background Image" />
                    <p class="mt-1 text-xs text-gray-400">Shown full-width behind the login form &mdash; a construction site, building, or team photo works well. Recommended: a wide landscape photo, at least 1600px, under 8MB.</p>

                    @if ($setting->backgroundImage())
                        <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700" x-show="!remove">
                            <img src="{{ $setting->backgroundImage()->getUrl() }}" alt="Current login background" class="h-40 w-full object-cover">
                        </div>
                        <label class="mt-2 flex items-center gap-2 text-sm text-rose-600 dark:text-rose-400">
                            <input type="checkbox" name="remove_background" value="1" x-model="remove" class="rounded border-gray-300 text-rose-600">
                            Remove current image (revert to the default look)
                        </label>
                    @endif

                    <input type="file" name="background" accept="image/*" class="mt-3 block w-full text-sm text-gray-600 dark:text-gray-300" x-show="!remove">
                    <x-input-error :messages="$errors->get('background')" class="mt-2" />
                </div>

                <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_offer_active" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('is_offer_active', $setting->is_offer_active))>
                        Show promotional content on the login page
                    </label>
                    <p class="mt-1 text-xs text-gray-400">Visible to anyone on the login page, including prospective clients who signed up via an enquiry &mdash; a good spot for a current offer or announcement.</p>
                </div>

                <div>
                    <x-input-label for="offer_title" value="Offer Title" />
                    <x-text-input id="offer_title" name="offer_title" class="mt-1 block w-full" maxlength="150" placeholder="e.g. Get 10% off new projects this month" value="{{ old('offer_title', $setting->offer_title) }}" />
                    <x-input-error :messages="$errors->get('offer_title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="offer_body" value="Offer Details" />
                    <x-textarea-input id="offer_body" name="offer_body" rows="4" class="mt-1 block w-full" maxlength="2000" placeholder="A short paragraph, or one line per offer.">{{ old('offer_body', $setting->offer_body) }}</x-textarea-input>
                    <x-input-error :messages="$errors->get('offer_body')" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
