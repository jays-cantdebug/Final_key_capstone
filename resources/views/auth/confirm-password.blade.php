<x-guest-layout>
    <div class="mx-auto w-full max-w-md">
        <x-card>
            <x-slot:header>
                <h2 class="text-xl font-semibold text-body">Confirm Password</h2>
            </x-slot:header>

            <div class="mb-4 text-sm text-slate-600">
                {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
            </div>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex justify-end mt-4">
                    <x-primary-button>
                        {{ __('Confirm') }}
                    </x-primary-button>
                </div>
            </form>
        </x-card>
    </div>
</x-guest-layout>
