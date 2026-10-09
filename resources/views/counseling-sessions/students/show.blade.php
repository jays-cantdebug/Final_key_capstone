@php
    $statusColors = [
        'Scheduled' => 'blue',
        'Completed' => 'green',
        'Cancelled' => 'slate',
        'No-Show' => 'amber',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Counseling History</p>
                <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $student->full_name }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                @unless ($student->trashed())
                    <x-primary-button :href="route('counseling-sessions.create', ['student_id' => $student->id])">
                        Schedule session
                    </x-primary-button>
                @endunless
                <x-secondary-button :href="route('reports.counseling.print', ['student_id' => $student->id])" target="_blank">
                    Print Report
                </x-secondary-button>
                <x-secondary-button :href="route('reports.counseling.pdf', ['student_id' => $student->id])">
                    Download PDF
                </x-secondary-button>
                <x-secondary-button :href="route('counseling-sessions.students.index')">
                    Back to list
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-700">
                <div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $student->student_number }}</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ $student->course?->course_code }} &mdash;
                        {{ $student->yearLevel?->label }} &mdash;
                        {{ $student->section?->section_name }}
                    </p>
                </div>
                @if ($student->trashed())
                    <x-badge color="slate">Archived student</x-badge>
                @endif
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Sessions</dt>
                    <dd class="mt-2 text-sm font-medium text-body dark:text-slate-100">{{ $summary?->counseling_sessions_count ?? 0 }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Last Session</dt>
                    <dd class="mt-2 text-sm font-medium text-body dark:text-slate-100">{{ $summary?->last_session_at?->format('M d, Y g:i A') ?? 'None completed' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Next Scheduled</dt>
                    <dd class="mt-2 text-sm font-medium text-body dark:text-slate-100">{{ $summary?->next_session_at?->format('M d, Y g:i A') ?? 'None' }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Follow-Up</dt>
                    <dd class="mt-2"><x-follow-up-badge :status="$summary?->follow_up_status ?? 'none'" :due-date="$summary?->last_follow_up_date" /></dd>
                </div>
            </dl>
        </x-card>

        <x-table>
            <x-slot:head>
                <x-table.th>Date &amp; Time</x-table.th>
                <x-table.th>Counselor</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th>Related Assessment</x-table.th>
                <x-table.th>Follow-Up</x-table.th>
                <x-table.th>Confidentiality</x-table.th>
                <x-table.th sticky align="right">Actions</x-table.th>
            </x-slot:head>

            @forelse ($sessions as $session)
                <tr>
                    <x-table.td class="font-medium text-body dark:text-slate-100">{{ $session->session_datetime->format('M d, Y g:i A') }}</x-table.td>
                    <x-table.td wrap>{{ $session->counselor->name }}</x-table.td>
                    <x-table.td><x-badge :color="$statusColors[$session->session_status] ?? 'slate'">{{ $session->session_status }}</x-badge></x-table.td>
                    <x-table.td>
                        @if ($session->assessment)
                            <a href="{{ route('assessments.show', $session->assessment) }}" class="text-primary underline dark:text-primary-soft">
                                {{ $session->assessment->submitted_at->format('M d, Y') }} &mdash; {{ $session->assessment->effectiveHighestSeverityLevel() ?? 'N/A' }}
                            </a>
                        @else
                            &mdash;
                        @endif
                    </x-table.td>
                    <x-table.td>
                        @if ($session->follow_up_required)
                            Required by {{ $session->follow_up_date?->format('M d, Y') ?? 'N/A' }}
                        @else
                            Not required
                        @endif
                    </x-table.td>
                    <x-table.td>{{ $session->confidentiality_level }}</x-table.td>
                    <x-table.td sticky align="right">
                        <div class="inline-flex flex-wrap justify-end gap-2">
                            <a href="{{ route('counseling-sessions.show', $session) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">View</a>
                            @can('update', $session)
                                <a href="{{ route('counseling-sessions.edit', $session) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">Edit</a>
                            @endcan
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="7">No counseling sessions recorded for this student yet.</x-table.empty>
            @endforelse
        </x-table>
    </div>
</x-app-layout>
