@php
    $statusBadgeStyles = [
        'Scheduled' => 'background:#E6F1FB;color:#0C447C;',
        'Completed' => 'background:#EAF3DE;color:#27500A;',
        'Cancelled' => 'background:#F1F5F9;color:#475569;',
        'No-Show' => 'background:#FAEEDA;color:#633806;',
    ];
@endphp

<x-report-layout title="Counseling Report">
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Counselor</th>
                <th>Date &amp; Time</th>
                <th>Status</th>
                <th>Confidentiality</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
                <tr>
                    <td>{{ $session->student->full_name }}</td>
                    <td>{{ $session->counselor->name }}</td>
                    <td>{{ $session->session_datetime->format('M d, Y g:i A') }}</td>
                    <td><span class="badge" style="{{ $statusBadgeStyles[$session->session_status] ?? '' }}">{{ $session->session_status }}</span></td>
                    <td>{{ $session->confidentiality_level }}</td>
                    <td>
                        @if ($session->isRestrictedFor($viewer))
                            <span class="empty">Restricted &mdash; visible only to the creating counselor.</span>
                        @else
                            {{ $session->session_notes }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">No counseling sessions found for the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-report-layout>
