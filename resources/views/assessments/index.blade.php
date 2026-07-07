@php
    $severityBadgeClasses = [
        'Normal' => 'bg-[#EAF3DE] text-[#27500A]',
        'Mild' => 'bg-[#E6F1FB] text-[#0C447C]',
        'Moderate' => 'bg-[#FAEEDA] text-[#633806]',
        'Severe' => 'bg-[#FAECE7] text-[#712B13]',
        'Extremely Severe' => 'bg-[#FCEBEB] text-[#791F1F]',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Assessment History</p>
                <h2 class="text-2xl font-semibold text-slate-900">Assessments</h2>
            </div>
            @if ($studentNumber)
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('reports.student-history.print', ['student_number' => $studentNumber]) }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Print Report
                    </a>
                    <a href="{{ route('reports.student-history.pdf', ['student_number' => $studentNumber]) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                        Download PDF
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('assessments.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <x-input-label for="student_number" :value="__('Student Number')" />
                <x-text-input id="student_number" name="student_number" type="text" class="mt-1 block w-full" value="{{ $studentNumber }}" placeholder="Search by student number" />
            </div>
            <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
            @if ($studentNumber)
                <a href="{{ route('assessments.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Student #</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Course / Year / Section</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Assessment Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Overall Severity</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($assessments as $assessment)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $assessment->student->full_name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $assessment->student->student_number }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                {{ $assessment->student->course?->course_code }} &mdash;
                                {{ $assessment->student->yearLevel?->label }} &mdash;
                                {{ $assessment->student->section?->section_name }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $assessment->submitted_at->format('M d, Y g:i A') }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $severityBadgeClasses[$assessment->result?->highestSeverityLevel()] ?? 'bg-slate-200 text-slate-600' }}">
                                    {{ $assessment->result?->highestSeverityLevel() ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('assessments.show', $assessment) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                                No assessments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $assessments->links() }}
        </div>
    </div>
</x-app-layout>
