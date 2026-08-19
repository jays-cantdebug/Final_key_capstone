<x-guest-layout background="bg-[radial-gradient(circle_at_top_left,_rgba(31,107,58,0.18),_transparent_45%),linear-gradient(180deg,_#E8EDE7_0%,_#DCE5DD_100%)] dark:bg-none dark:bg-[#0B0F0D]">
    <div class="mx-auto w-full max-w-md">
        <x-card class="!border-0 !bg-white shadow-2xl">
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <div class="h-8 w-8 flex-shrink-0" aria-hidden="true"></div>

                    <h2 class="flex-1 text-center text-2xl font-bold uppercase tracking-wide text-body">Forgot your password?</h2>

                    <a
                        href="{{ route('login') }}"
                        aria-label="{{ __('Close and return to login') }}"
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                    </a>
                </div>
            </x-slot:header>

            <div class="mb-4 text-sm text-slate-600">
                {{ __('No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
            </div>

            @if (session('status'))
                <x-toast type="success">{{ session('status') }}</x-toast>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Email')" class="!text-slate-700" />
                    <x-text-input id="email" class="block mt-1 w-full !border-gray-300 !bg-white !text-slate-900 focus:!border-primary focus:!ring-primary" type="email" name="email" :value="old('email')" required autofocus />
                    <x-input-error :messages="$errors->get('email')" class="mt-2 !text-red-600" />
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
