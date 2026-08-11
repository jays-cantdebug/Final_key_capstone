<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">User Management</p>
                <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Add User</h2>
            </div>
            <x-secondary-button :href="route('users.index')">
                Back to users
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            @include('users._form')

            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-password-input id="password" name="password" class="mt-1 block w-full" required />
                    <x-input-error class="mt-2" :messages="$errors->get('password')" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                    <x-password-input id="password_confirmation" name="password_confirmation" class="mt-1 block w-full" required />
                    <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('Create User') }}</x-primary-button>
                <x-secondary-button :href="route('users.index')">
                    {{ __('Cancel') }}
                </x-secondary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
