<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">User Management</p>
                <h2 class="text-2xl font-semibold text-body">Users</h2>
            </div>
            <x-primary-button :href="route('users.create')">
                Add user
            </x-primary-button>
        </div>
    </x-slot>

    @include('settings._tabs', ['active' => 'users'])

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <x-table>
        <x-slot:header>
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <x-input-label for="search" :value="__('Search')" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 w-72" value="{{ $search }}" placeholder="Search by name or email" />
                </div>
                <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                @if ($search)
                    <x-secondary-button :href="route('users.index')">
                        {{ __('Clear') }}
                    </x-secondary-button>
                @endif
            </form>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>Name</x-table.th>
            <x-table.th>Email</x-table.th>
            <x-table.th>Role</x-table.th>
            <x-table.th>Status</x-table.th>
            <x-table.th align="right">Actions</x-table.th>
        </x-slot:head>

        @forelse ($users as $user)
            <tr>
                <x-table.td class="font-medium text-body">{{ $user->name }}</x-table.td>
                <x-table.td>{{ $user->email }}</x-table.td>
                <x-table.td>{{ $user->role?->display_name }}</x-table.td>
                <x-table.td>
                    <x-badge :color="$user->is_active ? 'green' : 'slate'">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </x-badge>
                </x-table.td>
                <x-table.td align="right">
                    <div class="inline-flex flex-wrap justify-end gap-2">
                        <a href="{{ route('users.show', $user) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                        <a href="{{ route('users.edit', $user) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                        @if ($user->is_active)
                            @can('deactivate', $user)
                                <form id="deactivate-user-form-{{ $user->id }}" method="POST" action="{{ route('users.deactivate', $user) }}" class="hidden">
                                    @csrf
                                    @method('PATCH')
                                </form>
                                <button
                                    type="button"
                                    @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Deactivate this user account?', message: 'They will immediately be unable to log in until reactivated.', confirmLabel: 'Deactivate', formId: 'deactivate-user-form-{{ $user->id }}' })"
                                    class="rounded-md border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50"
                                >Deactivate</button>
                            @endcan
                        @else
                            @can('activate', $user)
                                <form id="activate-user-form-{{ $user->id }}" method="POST" action="{{ route('users.activate', $user) }}" class="hidden">
                                    @csrf
                                    @method('PATCH')
                                </form>
                                <button
                                    type="button"
                                    @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Activate this user account?', message: 'They will regain the ability to log in.', confirmLabel: 'Activate', variant: 'primary', formId: 'activate-user-form-{{ $user->id }}' })"
                                    class="rounded-md border border-emerald-200 px-3 py-1.5 font-medium text-emerald-700 transition hover:bg-emerald-50"
                                >Activate</button>
                            @endcan
                        @endif
                    </div>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="5">No users found.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $users->links() }}
        </x-slot:footer>
    </x-table>

    <x-confirm-modal />
</x-app-layout>
