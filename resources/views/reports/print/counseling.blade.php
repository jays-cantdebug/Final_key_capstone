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
                    <td>{{ $session->session_status }}</td>
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
