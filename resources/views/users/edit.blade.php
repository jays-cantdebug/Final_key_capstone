<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">User Management</p>
                <h2 class="text-2xl font-semibold text-body">Edit User</h2>
            </div>
            <x-secondary-button :href="route('users.show', $user)">
                View user
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')

            @include('users._form')

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('Update User') }}</x-primary-button>
                <x-secondary-button :href="route('users.show', $user)">
                    {{ __('Cancel') }}
                </x-secondary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
