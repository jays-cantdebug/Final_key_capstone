<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Sessions</h2>
            <div class="flex flex-wrap gap-2">
                <x-primary-button :href="route('counseling-sessions.create')">
                    Schedule session
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    @include('counseling-sessions._tabs', ['active' => 'counseling-sessions.students.index'])

    <div x-data="liveSearch()" x-on:input.debounce.400ms="handleInput($event)" x-on:click="handleClick($event)">
        <div x-ref="results">
            @include('counseling-sessions.students._table')
        </div>
    </div>
</x-app-layout>
