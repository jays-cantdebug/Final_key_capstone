<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">User Management</p>
                <h2 class="text-2xl font-semibold text-slate-900">Users</h2>
            </div>
            <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                Add user
            </a>
        </div>
    </x-slot>

    @include('settings._tabs', ['active' => 'users'])

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <x-input-label for="search" :value="__('Search')" />
                <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Search by name or email" />
            </div>
            <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
            @if ($search)
                <a href="{{ route('users.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                    {{ __('Clear') }}
                </a>
            @endif
        </form>
    </div>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($users as $user)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $user->name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $user->email }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $user->role?->display_name }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <div class="inline-flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('users.show', $user) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                                    <a href="{{ route('users.edit', $user) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                                    @if ($user->is_active)
                                        @can('deactivate', $user)
                                            <form method="POST" action="{{ route('users.deactivate', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-full border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50">Deactivate</button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('activate', $user)
                                            <form method="POST" action="{{ route('users.activate', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1.5 font-medium text-emerald-700 transition hover:bg-emerald-50">Activate</button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
