@php
    $statusBadgeClasses = [
        'Scheduled' => 'bg-blue-100 text-blue-700',
        'Completed' => 'bg-emerald-100 text-emerald-700',
        'Cancelled' => 'bg-slate-200 text-slate-600',
        'No-Show' => 'bg-amber-100 text-amber-700',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Counseling Sessions</p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $session->student->full_name }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('update', $session)
                    <a href="{{ route('counseling-sessions.edit', $session) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                        Edit session
                    </a>
                @endcan
                <a href="{{ route('counseling-sessions.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Back to list
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-6">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">{{ $session->student->student_number }}</p>
                        <p class="mt-2 text-sm text-slate-500">
                            {{ $session->student->course?->course_code }} &mdash;
                            {{ $session->student->yearLevel?->label }} &mdash;
                            {{ $session->student->section?->section_name }}
                        </p>
                    </div>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClasses[$session->session_status] ?? 'bg-slate-200 text-slate-600' }}">
                        {{ $session->session_status }}
                    </span>
                </div>

                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Counselor</dt>
                        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $session->counselor->name }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Session Date &amp; Time</dt>
                        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $session->session_datetime->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Related Assessment</dt>
                        <dd class="mt-2 text-sm font-medium text-slate-900">
                            @if ($session->assessment)
                                <a href="{{ route('assessments.show', $session->assessment) }}" class="text-[#1F6B3A] underline">
                                    {{ $session->assessment->submitted_at->format('M d, Y') }} &mdash; {{ $session->assessment->result?->highestSeverityLevel() ?? 'N/A' }}
                                </a>
                            @else
                                None
                            @endif
                        </dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Follow-Up</dt>
                        <dd class="mt-2 text-sm font-medium text-slate-900">
                            @if ($session->follow_up_required)
                                Required by {{ $session->follow_up_date?->format('M d, Y') ?? 'N/A' }}
                            @else
                                Not required
                            @endif
                        </dd>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Confidentiality Level</dt>
                        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $session->confidentiality_level }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Session Notes</h3>
                @if ($session->isRestrictedFor(auth()->user()))
                    <p class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm italic text-slate-500">
                        These session notes are marked Restricted and are only visible to the counselor who created this session.
                    </p>
                @else
                    <p class="mt-4 whitespace-pre-line rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">{{ $session->session_notes }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
