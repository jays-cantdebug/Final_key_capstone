@php
    $severityBadgeClasses = [
        'Normal' => 'bg-[#EAF3DE] text-[#27500A]',
        'Mild' => 'bg-[#E6F1FB] text-[#0C447C]',
        'Moderate' => 'bg-[#FAEEDA] text-[#633806]',
        'Severe' => 'bg-[#FAECE7] text-[#712B13]',
        'Extremely Severe' => 'bg-[#FCEBEB] text-[#791F1F]',
    ];

    $statusBadgeClasses = [
        'Open' => 'bg-rose-100 text-rose-700',
        'Resolved' => 'bg-emerald-100 text-emerald-700',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Flagged Students</p>
                <h2 class="text-2xl font-semibold text-slate-900">Flagged Cases</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.flagged-students.print', $filters) }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Print Report
                </a>
                <a href="{{ route('reports.flagged-students.pdf', $filters) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Download PDF
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('flagged-cases.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <x-input-label for="student_number" :value="__('Student Number')" />
                <x-text-input id="student_number" name="student_number" type="text" class="mt-1 block w-full" value="{{ $filters['student_number'] ?? '' }}" placeholder="Search by student number" />
            </div>

            <div>
                <x-input-label for="course_id" :value="__('Course')" />
                <select id="course_id" name="course_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) ($filters['course_id'] ?? '') === (string) $course->id)>{{ $course->course_code }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="year_level_id" :value="__('Year Level')" />
                <select id="year_level_id" name="year_level_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All year levels</option>
                    @foreach ($yearLevels as $yearLevel)
                        <option value="{{ $yearLevel->id }}" @selected((string) ($filters['year_level_id'] ?? '') === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="section_id" :value="__('Section')" />
                <select id="section_id" name="section_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All sections</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected((string) ($filters['section_id'] ?? '') === (string) $section->id)>{{ $section->section_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="date_from" :value="__('Assessment Date From')" />
                <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full" value="{{ $filters['date_from'] ?? '' }}" />
            </div>

            <div>
                <x-input-label for="date_to" :value="__('Assessment Date To')" />
                <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full" value="{{ $filters['date_to'] ?? '' }}" />
            </div>

            <div class="flex items-end gap-3 lg:col-span-3">
                <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                <a href="{{ route('flagged-cases.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                    {{ __('Clear') }}
                </a>
            </div>
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Highest Severity</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($flaggedCases as $flaggedCase)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $flaggedCase->assessment->student->full_name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $flaggedCase->assessment->student->student_number }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                {{ $flaggedCase->assessment->student->course?->course_code }} &mdash;
                                {{ $flaggedCase->assessment->student->yearLevel?->label }} &mdash;
                                {{ $flaggedCase->assessment->student->section?->section_name }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $flaggedCase->assessment->submitted_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $severityBadgeClasses[$flaggedCase->highest_severity] ?? 'bg-slate-200 text-slate-600' }}">
                                    {{ $flaggedCase->highest_severity }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClasses[$flaggedCase->status] ?? 'bg-slate-200 text-slate-600' }}">
                                    {{ $flaggedCase->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('assessments.show', $flaggedCase->assessment_id) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View Assessment</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">
                                No flagged cases found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $flaggedCases->links() }}
        </div>
    </div>
</x-app-layout>
