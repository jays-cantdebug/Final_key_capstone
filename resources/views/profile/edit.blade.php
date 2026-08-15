<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    @if ($errors->updatePassword->any())
        <x-alert type="error" class="mb-6">{{ $errors->updatePassword->first() }}</x-alert>
    @endif

    <div class="space-y-6">
        <x-card>
            <h3 class="text-lg font-semibold text-body dark:text-slate-100">Appearance</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Choose how the portal looks on this device.</p>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-lg bg-slate-50 p-4 dark:bg-slate-900/50">
                <div>
                    <p class="text-sm font-medium text-body dark:text-slate-100">Dark Mode</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Switches between light and dark themes. Saved to this browser only.</p>
                </div>

                <button
                    type="button"
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    x-on:click="
                        dark = !dark;
                        document.documentElement.classList.toggle('dark', dark);
                        localStorage.setItem('theme', dark ? 'dark' : 'light');
                    "
                    :aria-pressed="dark"
                    aria-label="Toggle dark mode"
                    :class="dark ? 'bg-primary dark:bg-primary-soft' : 'bg-slate-300 dark:bg-slate-600'"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:focus:ring-primary-soft dark:focus:ring-offset-slate-900"
                >
                    <span
                        :class="dark ? 'translate-x-6' : 'translate-x-1'"
                        class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                    ></span>
                </button>
            </div>
        </x-card>

        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>
    </div>
</x-app-layout>
