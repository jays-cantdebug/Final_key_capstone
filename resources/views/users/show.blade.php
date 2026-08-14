<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">User Management</p>
                <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $user->name }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-primary-button :href="route('users.edit', $user)">
                    Edit user
                </x-primary-button>
                <x-secondary-button :href="route('users.index')">
                    Back to list
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    @if ($errors->any())
        <x-toast type="error">{{ $errors->first() }}</x-toast>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <x-card>
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-6">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                    <p class="mt-2 text-sm font-medium text-body dark:text-slate-100">{{ $user->role?->display_name }}</p>
                </div>
                <x-badge :color="$user->is_active ? 'green' : 'slate'">
                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                </x-badge>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                @can('forceLogout', $user)
                    <form method="POST" action="{{ route('users.force-logout', $user) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">
                            Force logout (end active sessions)
                        </button>
                    </form>
                @endcan

                @if ($user->is_active)
                    @can('deactivate', $user)
                        <form method="POST" action="{{ route('users.deactivate', $user) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center justify-center rounded-md border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                Deactivate account
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">This account cannot be deactivated (it is your own account, or the last remaining active Psychometrician account).</p>
                    @endcan
                @else
                    @can('activate', $user)
                        <form method="POST" action="{{ route('users.activate', $user) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center justify-center rounded-md border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950/40">
                                Activate account
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-body dark:text-slate-100">Reset Password</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Set a new password for this account directly.</p>

            @can('resetPassword', $user)
                <form method="POST" action="{{ route('users.reset-password', $user) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="password" :value="__('New Password')" />
                        <x-password-input id="password" name="password" class="mt-1 block w-full" required />
                        <x-input-error class="mt-2" :messages="$errors->get('password')" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" :value="__('Confirm New Password')" />
                        <x-password-input id="password_confirmation" name="password_confirmation" class="mt-1 block w-full" required />
                        <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                    </div>

                    <x-primary-button>{{ __('Reset Password') }}</x-primary-button>
                </form>
            @endcan
        </x-card>
    </div>
</x-app-layout>
