<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Users</h2>
            <x-primary-button :href="route('users.create')">
                Add user
            </x-primary-button>
        </div>
    </x-slot>

    @include('settings._tabs', ['active' => 'users'])

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    @if ($errors->any())
        <x-toast type="error">{{ $errors->first() }}</x-toast>
    @endif

    <div x-data="liveSearch()" x-on:input.debounce.400ms="handleInput($event)" x-on:click="handleClick($event)">
        <div x-ref="results">
            @include('users._table', ['users' => $users, 'search' => $search])
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
