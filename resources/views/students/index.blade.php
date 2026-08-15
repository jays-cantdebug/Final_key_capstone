<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Student Management</p>
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Students</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    <div x-data="liveSearch()" x-on:input.debounce.400ms="handleInput($event)" x-on:click="handleClick($event)">
        <div x-ref="results">
            @include('students._table', ['students' => $students, 'search' => $search])
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
