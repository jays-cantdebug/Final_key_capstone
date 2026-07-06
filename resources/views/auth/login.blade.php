<x-guest-layout>
    <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-3xl bg-[linear-gradient(160deg,#1F6B3A_0%,#185828_100%)] p-8 text-white shadow-2xl shadow-emerald-950/20">
            <div class="flex items-center gap-4">
                <x-application-logo class="h-14 w-14 text-white" />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/75">NORMI Guidance System</p>
                    <h1 class="mt-1 text-3xl font-bold leading-tight">Student DASS Assessment Platform</h1>
                </div>
            </div>

            <p class="mt-8 max-w-xl text-sm leading-6 text-white/85">
                Secure access for psychometricians and guidance counselors. Public registration is disabled; accounts are created by the institution.
            </p>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                    <p class="text-sm font-semibold text-white">Protected Access</p>
                    <p class="mt-2 text-sm text-white/80">Role-aware dashboards and authenticated workflows only.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                    <p class="text-sm font-semibold text-white">Privacy First</p>
                    <p class="mt-2 text-sm text-white/80">Built for sensitive assessment records and auditability.</p>
                </div>
            </div>
        </section>

        <section class="rounded-3xl bg-white p-8 shadow-[0_20px_60px_rgba(31,107,58,0.12)] ring-1 ring-black/5">
            <div class="mb-8">
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[#1F6B3A]">Sign In</p>
                <h2 class="mt-2 text-2xl font-bold text-[#2C2C2A]">Welcome back</h2>
                <p class="mt-2 text-sm text-slate-600">Use your institutional account to continue.</p>
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="mt-1 block w-full rounded-xl border-slate-300 focus:border-[#1F6B3A] focus:ring-[#1F6B3A]" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" class="mt-1 block w-full rounded-xl border-slate-300 focus:border-[#1F6B3A] focus:ring-[#1F6B3A]" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between gap-4">
                    <label for="remember_me" class="inline-flex items-center gap-2">
                        <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-[#1F6B3A] shadow-sm focus:ring-[#1F6B3A]" name="remember">
                        <span class="text-sm text-slate-600">{{ __('Remember me') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm font-medium text-[#1F6B3A] hover:text-[#185828]" href="{{ route('password.request') }}">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif
                </div>

                <x-primary-button class="w-full justify-center rounded-xl bg-[#1F6B3A] py-3 text-sm font-semibold shadow-lg shadow-[#1F6B3A]/20 hover:bg-[#185828] focus:ring-[#1F6B3A]">
                    {{ __('Log in') }}
                </x-primary-button>
            </form>
        </section>
    </div>
</x-guest-layout>
