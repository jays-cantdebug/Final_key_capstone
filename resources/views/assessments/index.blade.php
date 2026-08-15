<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Assessment History</p>
                <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Assessments</h2>
            </div>
            @if ($studentNumber)
                <div class="flex flex-wrap gap-2">
                    <x-secondary-button :href="route('reports.student-history.print', ['student_number' => $studentNumber])" target="_blank">
                        Print Report
                    </x-secondary-button>
                    <x-primary-button :href="route('reports.student-history.pdf', ['student_number' => $studentNumber])">
                        Download PDF
                    </x-primary-button>
                </div>
            @endif
        </div>
    </x-slot>

    <div x-data="liveSearch()" x-on:input.debounce.400ms="handleInput($event)" x-on:click="handleClick($event)">
        <div x-ref="results">
            @include('assessments._table', ['assessments' => $assessments, 'search' => $search, 'studentNumber' => $studentNumber])
        </div>
    </div>
</x-app-layout>
