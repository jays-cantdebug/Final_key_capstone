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
                <h2 class="text-2xl font-semibold text-slate-900">Sessions</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.counseling.print', ['student_number' => $studentNumber]) }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Print Report
                </a>
                <a href="{{ route('reports.counseling.pdf', ['student_number' => $studentNumber]) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Download PDF
                </a>
                <a href="{{ route('counseling-sessions.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Schedule session
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('counseling-sessions.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <x-input-label for="student_number" :value="__('Student Number')" />
                <x-text-input id="student_number" name="student_number" type="text" class="mt-1 block w-full" value="{{ $studentNumber }}" placeholder="Search by student number" />
            </div>
            <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
            @if ($studentNumber)
                <a href="{{ route('counseling-sessions.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
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
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Counselor</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date &amp; Time</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Confidentiality</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($sessions as $session)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $session->student->full_name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $session->counselor->name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $session->session_datetime->format('M d, Y g:i A') }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClasses[$session->session_status] ?? 'bg-slate-200 text-slate-600' }}">
                                    {{ $session->session_status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $session->confidentiality_level }}</td>
                            <td class="px-6 py-4 text-right text-sm">
                                <div class="inline-flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('counseling-sessions.show', $session) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                                    @can('update', $session)
                                        <a href="{{ route('counseling-sessions.edit', $session) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                                    @endcan
                                    @can('delete', $session)
                                        <form method="POST" action="{{ route('counseling-sessions.destroy', $session) }}" onsubmit="return confirm('Delete this counseling session?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                                No counseling sessions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $sessions->links() }}
        </div>
    </div>
</x-app-layout>
