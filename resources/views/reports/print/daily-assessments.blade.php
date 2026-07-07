<x-report-layout :title="'Daily Assessment Report — ' . \Carbon\Carbon::parse($date)->format('M d, Y')">
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>Student</th>
                <th>Psychometrician</th>
                <th>Overall Severity</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assessments as $assessment)
                <tr>
                    <td>{{ $assessment->submitted_at->format('g:i A') }}</td>
                    <td>{{ $assessment->student->full_name }}</td>
                    <td>{{ $assessment->psychometrician->name }}</td>
                    <td>{{ $assessment->result?->highestSeverityLevel() ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">No assessments were submitted on this date.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-report-layout>
