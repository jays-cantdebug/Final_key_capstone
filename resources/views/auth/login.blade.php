<x-login-layout>
    <div class="grid items-center gap-10 lg:grid-cols-2">
        <div class="text-center lg:text-left">
            <h1 class="text-3xl font-bold uppercase leading-tight tracking-wide text-white sm:text-4xl lg:text-5xl">
                Web-Based Student Depression, Anxiety and Stress Predictor
            </h1>
            <p class="mt-6 text-lg font-semibold text-white/90">Northern Mindanao Colleges, Inc.</p>
            <p class="mt-1 text-sm uppercase tracking-[0.3em] text-white/60">Well-being System</p>
        </div>

        <div class="mx-auto w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl">
            <h2 class="text-center text-2xl font-bold uppercase tracking-wide text-[#2C2C2A]">Login</h2>

            <x-auth-session-status class="mt-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-6">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Email Address')" />
                    <x-text-input id="email" class="mt-1 block w-full focus:border-[#1F6B3A] focus:ring-[#1F6B3A]" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" class="mt-1 block w-full focus:border-[#1F6B3A] focus:ring-[#1F6B3A]" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between gap-4">
                    <label for="remember_me" class="inline-flex items-center gap-2">
                        <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-[#1F6B3A] shadow-sm focus:ring-[#1F6B3A]" name="remember">
                        <span class="text-sm text-slate-600">{{ __('Remember me') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm font-medium text-[#1F6B3A] hover:text-[#185828]" href="{{ route('password.request') }}">
                            {{ __('Forgot Password') }}
                        </a>
                    @endif
                </div>

                <button type="submit" class="w-full rounded-xl bg-[#1F6B3A] py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg shadow-[#1F6B3A]/30 transition hover:bg-[#185828] focus:outline-none focus:ring-2 focus:ring-[#1F6B3A] focus:ring-offset-2">
                    {{ __('Login') }}
                </button>
            </form>
        </div>
    </div>
</x-login-layout>
