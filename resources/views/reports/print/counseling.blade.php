@php
    $statusBadgeStyles = [
        'Scheduled' => 'background:#E6F1FB;color:#0C447C;',
        'Completed' => 'background:#EAF3DE;color:#27500A;',
        'Cancelled' => 'background:#F1F5F9;color:#475569;',
        'No-Show' => 'background:#FAEEDA;color:#633806;',
    ];
    $followUpLabels = [
        'overdue' => ['Overdue', 'background:#FCEBEB;color:#791F1F;'],
        'needed' => ['Follow-up needed', 'background:#FAEEDA;color:#633806;'],
        'none' => ['None', 'background:#F1F5F9;color:#475569;'],
    ];
@endphp

<x-report-layout :title="$student ? 'Student Counseling History Report' : 'Counseling Report'">
    @if ($student)
        @php
            [$followUpLabel, $followUpStyle] = $followUpLabels[$summary?->follow_up_status ?? 'none'];
        @endphp
        <dl>
            <dt>Name</dt>
            <dd>{{ $student->full_name }}</dd>
            <dt>Student Number</dt>
            <dd>{{ $student->student_number }}</dd>
            <dt>Course</dt>
            <dd>{{ $student->course?->course_code }}</dd>
            <dt>Year Level / Section</dt>
            <dd>{{ $student->yearLevel?->label }} / {{ $student->section?->section_name }}</dd>
            <dt>Total Sessions</dt>
            <dd>{{ $summary?->counseling_sessions_count ?? 0 }}</dd>
            <dt>Last Completed Session</dt>
            <dd>{{ $summary?->last_session_at?->format('M d, Y g:i A') ?? 'None' }}</dd>
            <dt>Next Scheduled Session</dt>
            <dd>{{ $summary?->next_session_at?->format('M d, Y g:i A') ?? 'None' }}</dd>
            <dt>Follow-Up</dt>
            <dd>
                <span class="badge" style="{{ $followUpStyle }}">{{ $followUpLabel }}</span>
                @if ($summary?->follow_up_status !== 'none' && $summary?->last_follow_up_date)
                    (due {{ $summary->last_follow_up_date->format('M d, Y') }})
                @endif
            </dd>
        </dl>

        <h2>Session History</h2>
    @endif

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Counselor</th>
                <th>Date &amp; Time</th>
                <th>Status</th>
                <th>Confidentiality</th>
                <th>Follow-Up</th>
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
                    <td>{{ $session->follow_up_required ? 'Required by '.($session->follow_up_date?->format('M d, Y') ?? 'N/A') : 'Not required' }}</td>
                    <td>
                        @if ($session->isRestrictedFor($viewer))
                            <span class="empty">Restricted &mdash; visible only to the creating counselor.</span>
                        @elseif ($session->isUnreadable('session_notes'))
                            <x-unreadable-value />
                        @else
                            {{ $session->session_notes }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">No counseling sessions found for the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-report-layout>
