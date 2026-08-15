<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Sessions</h2>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.counseling.print', ['search' => $search])" target="_blank">
                    Print Report
                </x-secondary-button>
                <x-secondary-button :href="route('reports.counseling.pdf', ['search' => $search])">
                    Download PDF
                </x-secondary-button>
                <x-primary-button :href="route('counseling-sessions.create')">
                    Schedule session
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    <div x-data="liveSearch()" x-on:input.debounce.400ms="handleInput($event)" x-on:click="handleClick($event)">
        <div x-ref="results">
            @include('counseling-sessions._table', ['sessions' => $sessions, 'search' => $search])
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
