<x-report-layout :title="'Monthly Assessment Report — ' . \Carbon\Carbon::create()->month($month)->format('F') . ' ' . $year">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Psychometrician</th>
                <th>Overall Severity</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assessments as $assessment)
                <tr>
                    <td>{{ $assessment->submitted_at->format('M d, Y g:i A') }}</td>
                    <td>{{ $assessment->student->full_name }}</td>
                    <td>{{ $assessment->psychometrician->name }}</td>
                    <td>{{ $assessment->result?->overall_status ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">No assessments were submitted in this month.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-report-layout>
