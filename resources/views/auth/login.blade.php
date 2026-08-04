<x-login-layout>
    <div class="grid items-center gap-10 lg:grid-cols-[1.4fr_1fr]">
        <div class="text-center lg:text-left">
            <x-application-logo class="mb-6 inline-block h-40 w-40" />
            <h1 class="text-3xl font-bold uppercase leading-tight tracking-wide text-white sm:text-4xl lg:text-3xl xl:text-5xl">
                <span class="block">Student</span>
                <span class="block">Depression, Anxiety</span>
                <span class="block">and Stress Assessment</span>
            </h1>
            <p class="mt-6 text-lg font-semibold text-white/90">{{ app(\App\Services\SystemSettingService::class)->schoolName() }}</p>
            <p class="mt-1 text-sm uppercase tracking-[0.3em] text-white/60">Well-being System</p>
        </div>

        <div class="mx-auto w-full max-w-md rounded-lg bg-white p-8 shadow-2xl">
            <h2 class="text-center text-2xl font-bold uppercase tracking-wide text-body">Login</h2>

            <x-auth-session-status class="mt-4" :status="session('status')" />

            @php
                $emailError = $errors->first('email');
                $isLockout = $emailError && str_starts_with($emailError, 'Too many login attempts');
            @endphp

            @if ($isLockout)
                <x-alert type="error" class="mt-4">{{ $emailError }}</x-alert>
            @endif

            <form
                method="POST"
                action="{{ route('login') }}"
                class="mt-8 space-y-6"
                novalidate
                x-init="$nextTick(() => { const first = $el.querySelector('[data-field-invalid]'); if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); } })"
            >
                @csrf

                <div class="relative" x-data="{ show: {{ ! $isLockout && $errors->has('email') ? 'true' : 'false' }} }">
                    <x-input-label for="email" :value="__('Email Address')" />
                    <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" :invalid="! $isLockout && $errors->has('email')" autofocus autocomplete="username" @input="show = false" />
                    <x-field-error-tooltip :message="$isLockout ? null : $emailError" />
                </div>

                <div class="relative" x-data="{ show: {{ $errors->has('password') ? 'true' : 'false' }} }">
                    <x-input-label for="password" :value="__('Password')" />
                    <x-password-input id="password" class="mt-1 block w-full" name="password" :invalid="$errors->has('password')" autocomplete="current-password" @input="show = false" />
                    <x-field-error-tooltip :message="$errors->first('password')" />
                </div>

                <div class="flex items-center justify-between gap-4">
                    <label for="remember_me" class="inline-flex items-center gap-2">
                        <x-checkbox id="remember_me" name="remember" />
                        <span class="text-sm text-slate-600">{{ __('Remember me') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm font-medium text-primary hover:text-primary-dark" href="{{ route('password.request') }}">
                            {{ __('Forgot Password') }}
                        </a>
                    @endif
                </div>

                <button type="submit" class="w-full rounded-md bg-primary py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg shadow-primary/30 transition hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    {{ __('Login') }}
                </button>
            </form>
        </div>
    </div>
</x-login-layout>
