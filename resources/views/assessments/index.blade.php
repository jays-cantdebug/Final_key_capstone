<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Assessment History</p>
                <h2 class="text-2xl font-semibold text-body">Assessments</h2>
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

    <x-card class="mb-6">
        <form method="GET" action="{{ route('assessments.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <x-input-label for="search" :value="__('Student Name')" />
                <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Search by student name" />
            </div>
            <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
            @if ($search || $studentNumber)
                <x-secondary-button :href="route('assessments.index')">
                    {{ __('Clear') }}
                </x-secondary-button>
            @endif
        </form>
    </x-card>

    <x-table>
        <x-slot:head>
            <x-table.th>Student</x-table.th>
            <x-table.th>Student #</x-table.th>
            <x-table.th>Course / Year / Section</x-table.th>
            <x-table.th>Assessment Date</x-table.th>
            <x-table.th>Overall Severity</x-table.th>
            <x-table.th align="right">Actions</x-table.th>
        </x-slot:head>

        @forelse ($assessments as $assessment)
            <tr>
                <x-table.td class="font-medium text-body">{{ $assessment->student->full_name }}</x-table.td>
                <x-table.td>{{ $assessment->student->student_number }}</x-table.td>
                <x-table.td>
                    {{ $assessment->student->course?->course_code }} &mdash;
                    {{ $assessment->student->yearLevel?->label }} &mdash;
                    {{ $assessment->student->section?->section_name }}
                </x-table.td>
                <x-table.td>{{ $assessment->submitted_at->format('M d, Y g:i A') }}</x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->highestSeverityLevel()" /></x-table.td>
                <x-table.td align="right">
                    <a href="{{ route('assessments.show', $assessment) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="6">No assessments found.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $assessments->links() }}
        </x-slot:footer>
    </x-table>
</x-app-layout>
