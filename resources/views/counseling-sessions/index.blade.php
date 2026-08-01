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
                <h2 class="text-2xl font-semibold text-body">Sessions</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.counseling.print', ['search' => $search])" target="_blank">
                    Print Report
                </x-secondary-button>
                <x-secondary-button :href="route('reports.counseling.pdf', ['search' => $search])">
                    Download PDF
                </x-secondary-button>
                <x-primary-button :href="route('counseling-sessions.create')">
                    Schedule session
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <x-table>
        <x-slot:header>
            <form method="GET" action="{{ route('counseling-sessions.index') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <x-input-label for="search" :value="__('Student Name')" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 w-72" value="{{ $search }}" placeholder="Search by student name" />
                </div>
                <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                @if ($search)
                    <x-secondary-button :href="route('counseling-sessions.index')">
                        {{ __('Clear') }}
                    </x-secondary-button>
                @endif
            </form>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>Student</x-table.th>
            <x-table.th>Counselor</x-table.th>
            <x-table.th>Date &amp; Time</x-table.th>
            <x-table.th>Status</x-table.th>
            <x-table.th>Confidentiality</x-table.th>
            <x-table.th align="right">Actions</x-table.th>
        </x-slot:head>

        @forelse ($sessions as $session)
            <tr>
                <x-table.td class="font-medium text-body">{{ $session->student->full_name }}</x-table.td>
                <x-table.td>{{ $session->counselor->name }}</x-table.td>
                <x-table.td>{{ $session->session_datetime->format('M d, Y g:i A') }}</x-table.td>
                <x-table.td><x-badge :color="$statusColors[$session->session_status] ?? 'slate'">{{ $session->session_status }}</x-badge></x-table.td>
                <x-table.td>{{ $session->confidentiality_level }}</x-table.td>
                <x-table.td align="right">
                    <div class="inline-flex flex-wrap justify-end gap-2">
                        <a href="{{ route('counseling-sessions.show', $session) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                        @can('update', $session)
                            <a href="{{ route('counseling-sessions.edit', $session) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                        @endcan
                        @can('delete', $session)
                            <form id="delete-session-form-{{ $session->id }}" method="POST" action="{{ route('counseling-sessions.destroy', $session) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button
                                type="button"
                                @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Delete this counseling session?', message: 'This action cannot be undone.', confirmLabel: 'Delete', formId: 'delete-session-form-{{ $session->id }}' })"
                                class="rounded-md border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50"
                            >Delete</button>
                        @endcan
                    </div>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="6">No counseling sessions found.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $sessions->links() }}
        </x-slot:footer>
    </x-table>

    <x-confirm-modal />
</x-app-layout>
