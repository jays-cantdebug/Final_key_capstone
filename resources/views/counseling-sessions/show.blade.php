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
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Counseling Sessions</p>
                <h2 class="text-2xl font-semibold text-body">{{ $session->student->full_name }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('update', $session)
                    <x-primary-button :href="route('counseling-sessions.edit', $session)">
                        Edit session
                    </x-primary-button>
                @endcan
                <x-secondary-button :href="route('counseling-sessions.index')">
                    Back to list
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="mx-auto max-w-3xl space-y-6">
        <x-card>
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-6">
                <div>
                    <p class="text-sm font-semibold text-slate-500">{{ $session->student->student_number }}</p>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ $session->student->course?->course_code }} &mdash;
                        {{ $session->student->yearLevel?->label }} &mdash;
                        {{ $session->student->section?->section_name }}
                    </p>
                </div>
                <x-badge :color="$statusColors[$session->session_status] ?? 'slate'">{{ $session->session_status }}</x-badge>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Counselor</dt>
                    <dd class="mt-2 text-sm font-medium text-body">{{ $session->counselor->name }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Session Date &amp; Time</dt>
                    <dd class="mt-2 text-sm font-medium text-body">{{ $session->session_datetime->format('M d, Y g:i A') }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Related Assessment</dt>
                    <dd class="mt-2 text-sm font-medium text-body">
                        @if ($session->assessment)
                            <a href="{{ route('assessments.show', $session->assessment) }}" class="text-primary underline">
                                {{ $session->assessment->submitted_at->format('M d, Y') }} &mdash; {{ $session->assessment->result?->highestSeverityLevel() ?? 'N/A' }}
                            </a>
                        @else
                            None
                        @endif
                    </dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Follow-Up</dt>
                    <dd class="mt-2 text-sm font-medium text-body">
                        @if ($session->follow_up_required)
                            Required by {{ $session->follow_up_date?->format('M d, Y') ?? 'N/A' }}
                        @else
                            Not required
                        @endif
                    </dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Confidentiality Level</dt>
                    <dd class="mt-2 text-sm font-medium text-body">{{ $session->confidentiality_level }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-body">Session Notes</h3>
            @if ($session->isRestrictedFor(auth()->user()))
                <p class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm italic text-slate-500">
                    These session notes are marked Restricted and are only visible to the counselor who created this session.
                </p>
            @else
                <p class="mt-4 whitespace-pre-line rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">{{ $session->session_notes }}</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
