<x-guest-layout>
    <div class="mx-auto w-full max-w-md">
        <x-card>
            <x-slot:header>
                <h2 class="text-xl font-semibold text-body dark:text-slate-100">Forgot your password?</h2>
            </x-slot:header>

            <div class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                {{ __('No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-primary-button>
                        {{ __('Email Password Reset Link') }}
                    </x-primary-button>
                </div>
            </form>
        </x-card>
    </div>
</x-guest-layout>
