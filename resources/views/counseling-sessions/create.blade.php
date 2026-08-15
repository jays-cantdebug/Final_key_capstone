<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Counseling Sessions</p>
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Schedule Session</h2>
        </div>
    </x-slot>

    @if ($errors->any() && ! $foundStudent)
        <x-alert type="error" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div x-data="liveSearch()" x-on:input.debounce.400ms="handleInput($event)" x-on:click="handleClick($event)">
        <x-card>
            <p class="text-sm text-slate-600 dark:text-slate-400">Search for the student this counseling session is for by name. Counseling sessions can only be scheduled for existing student records.</p>

            <form method="GET" action="{{ route('counseling-sessions.create') }}" class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-[220px] flex-1">
                    <x-input-label for="search" :value="__('Student Name')" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Search by student name" autofocus />
                </div>
            </form>
        </x-card>

        <div x-ref="results">
            @include('counseling-sessions._search-results', [
                'searched' => $searched,
                'foundStudent' => $foundStudent,
                'matches' => $matches,
            ])
        </div>
    </div>
</x-app-layout>
