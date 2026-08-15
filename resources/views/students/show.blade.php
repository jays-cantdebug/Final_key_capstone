<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Student Profile</h2>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('assessments.create.retake', $student)">
                    Take Again
                </x-secondary-button>
                <x-primary-button :href="route('students.edit', $student)">
                    Edit student
                </x-primary-button>
                <x-secondary-button :href="route('students.index')">
                    Back to list
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <x-card>
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-6">
                <div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $student->student_number }}</p>
                    <h3 class="mt-2 text-3xl font-semibold text-body dark:text-slate-100">{{ $student->full_name }}</h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $student->course?->course_name }}</p>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Gender</dt>
                    <dd class="mt-2 text-sm font-medium text-body dark:text-slate-100">{{ $student->gender }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Year Level</dt>
                    <dd class="mt-2 text-sm font-medium text-body dark:text-slate-100">{{ $student->yearLevel?->label }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Section</dt>
                    <dd class="mt-2 text-sm font-medium text-body dark:text-slate-100">{{ $student->section?->section_name }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Course</dt>
                    <dd class="mt-2 text-sm font-medium text-body dark:text-slate-100">{{ $student->course?->course_code }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-body dark:text-slate-100">Record Summary</h3>
            <div class="mt-4 space-y-4 text-sm text-slate-600 dark:text-slate-400">
                <p><span class="font-semibold text-body dark:text-slate-100">Student number:</span> {{ $student->student_number }}</p>
                <p><span class="font-semibold text-body dark:text-slate-100">Full name:</span> {{ $student->full_name }}</p>
                <p><span class="font-semibold text-body dark:text-slate-100">Program:</span> {{ $student->course?->course_name }}</p>
                <p><span class="font-semibold text-body dark:text-slate-100">Section:</span> {{ $student->section?->section_name }}</p>
                <p><span class="font-semibold text-body dark:text-slate-100">Year level:</span> {{ $student->yearLevel?->label }}</p>
            </div>
        </x-card>
    </div>

    <x-table class="mt-6">
        <x-slot:header>
            <div class="flex w-full flex-wrap items-center justify-between gap-4">
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Assessment History</h3>
                @if ($assessments->total() > 0)
                    <div class="flex flex-wrap gap-2">
                        <x-secondary-button :href="route('reports.student-history.print', ['student_number' => $student->student_number])" target="_blank">
                            Print Report
                        </x-secondary-button>
                        <x-primary-button :href="route('reports.student-history.pdf', ['student_number' => $student->student_number])">
                            Download PDF
                        </x-primary-button>
                    </div>
                @endif
            </div>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>Date</x-table.th>
            <x-table.th>Stress</x-table.th>
            <x-table.th>Anxiety</x-table.th>
            <x-table.th>Depression</x-table.th>
            <x-table.th>Flag</x-table.th>
            <x-table.th align="right">Action</x-table.th>
        </x-slot:head>

        @forelse ($assessments as $assessment)
            @php
                $priorityFlag = $assessment->priorityFlag();
                $secondaryCount = $assessment->secondaryFlagCount();
            @endphp
            <tr>
                <x-table.td>{{ $assessment->submitted_at->format('M d, Y') }}</x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->stress_level" /></x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->anxiety_level" /></x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->depression_level" /></x-table.td>
                <x-table.td>
                    @if ($priorityFlag)
                        <x-flag-badge :type="$priorityFlag->flag_type" :secondary-count="$secondaryCount" />
                    @else
                        <span class="text-slate-400 dark:text-slate-500">&mdash;</span>
                    @endif
                </x-table.td>
                <x-table.td align="right">
                    <a href="{{ route('assessments.show', $assessment) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">View</a>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="6">No assessments yet for this student.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $assessments->links() }}
        </x-slot:footer>
    </x-table>
</x-app-layout>
