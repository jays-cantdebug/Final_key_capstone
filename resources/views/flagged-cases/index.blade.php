@php
    $tabs = [
        'all' => 'All',
        'endorsement' => 'Endorsement',
        'notification' => 'Notification',
        'normal' => 'Normal',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Flagged Students</p>
                <h2 class="text-2xl font-semibold text-body">Student Flagged</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.flagged-students.print', $filters)" target="_blank">
                    Print Report
                </x-secondary-button>
                <x-primary-button :href="route('reports.flagged-students.pdf', $filters)">
                    Download PDF
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <div class="mb-6 flex gap-2 border-b border-slate-200">
        @foreach ($tabs as $key => $label)
            <a
                href="{{ route('flagged-cases.index', array_merge(array_diff_key($filters, ['tab' => null]), ['tab' => $key])) }}"
                class="border-b-2 px-4 py-3 text-sm font-semibold transition {{ $activeTab === $key ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('flagged-cases.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="{{ $activeTab }}" />

            <div class="w-36">
                <x-input-label for="course_id" :value="__('Course')" class="!text-xs" />
                <x-select id="course_id" name="course_id" class="mt-1 block w-full !py-1.5 text-sm">
                    <option value="">All courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) ($filters['course_id'] ?? '') === (string) $course->id)>{{ $course->course_code }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="w-36">
                <x-input-label for="year_level_id" :value="__('Year Level')" class="!text-xs" />
                <x-select id="year_level_id" name="year_level_id" class="mt-1 block w-full !py-1.5 text-sm">
                    <option value="">All year levels</option>
                    @foreach ($yearLevels as $yearLevel)
                        <option value="{{ $yearLevel->id }}" @selected((string) ($filters['year_level_id'] ?? '') === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="w-36">
                <x-input-label for="date_from" :value="__('Date From')" class="!text-xs" />
                <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full !py-1.5 text-sm" value="{{ $filters['date_from'] ?? '' }}" />
            </div>

            <div class="w-36">
                <x-input-label for="date_to" :value="__('Date To')" class="!text-xs" />
                <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full !py-1.5 text-sm" value="{{ $filters['date_to'] ?? '' }}" />
            </div>

            <div class="flex items-center gap-2">
                <x-secondary-button type="submit" class="!py-1.5 text-sm">{{ __('Filter') }}</x-secondary-button>
                <x-secondary-button :href="route('flagged-cases.index', ['tab' => $activeTab])" class="!py-1.5 text-sm">
                    {{ __('Clear') }}
                </x-secondary-button>
            </div>
        </form>
    </x-card>

    <x-table>
        <x-slot:head>
            <x-table.th>Student</x-table.th>
            <x-table.th>Course</x-table.th>
            <x-table.th>Stress</x-table.th>
            <x-table.th>Anxiety</x-table.th>
            <x-table.th>Depression</x-table.th>
            <x-table.th>Flag</x-table.th>
            <x-table.th>Date</x-table.th>
            <x-table.th align="right">Action</x-table.th>
        </x-slot:head>

        @forelse ($assessments as $assessment)
            @php
                $priorityFlag = $assessment->priorityFlag();
                $secondaryCount = $assessment->secondaryFlagCount();
            @endphp
            <tr>
                <x-table.td class="font-medium text-body">
                    {{ $assessment->student->full_name }}
                    <div class="text-xs font-normal text-slate-500">{{ $assessment->student->student_number }} &mdash; {{ $assessment->student->yearLevel?->label }} / {{ $assessment->student->section?->section_name }}</div>
                </x-table.td>
                <x-table.td>{{ $assessment->student->course?->course_code }}</x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->stress_level" /></x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->anxiety_level" /></x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->depression_level" /></x-table.td>
                <x-table.td>
                    @if ($priorityFlag)
                        <x-flag-badge :type="$priorityFlag->flag_type" :secondary-count="$secondaryCount" />
                    @else
                        <x-badge color="slate">Normal</x-badge>
                    @endif
                </x-table.td>
                <x-table.td>{{ $assessment->submitted_at->format('M d, Y') }}</x-table.td>
                <x-table.td align="right">
                    <a href="{{ route('assessments.show', $assessment) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="8">No flagged students match these filters.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $assessments->links() }}
        </x-slot:footer>
    </x-table>
</x-app-layout>
