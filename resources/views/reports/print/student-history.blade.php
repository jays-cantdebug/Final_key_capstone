<x-report-layout title="Student Assessment History Report">
    @if ($student)
        <dl>
            <dt>Name</dt>
            <dd>{{ $student->full_name }}</dd>
            <dt>Student Number</dt>
            <dd>{{ $student->student_number }}</dd>
            <dt>Course</dt>
            <dd>{{ $student->course?->course_code }}</dd>
            <dt>Year Level / Section</dt>
            <dd>{{ $student->yearLevel?->label }} / {{ $student->section?->section_name }}</dd>
        </dl>

        <h2>Assessment History</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Depression</th>
                    <th>Anxiety</th>
                    <th>Stress</th>
                    <th>Overall</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assessments as $assessment)
                    <tr>
                        <td>{{ $assessment->submitted_at->format('M d, Y g:i A') }}</td>
                        <td>{{ $assessment->result?->depression_level ?? 'N/A' }}</td>
                        <td>{{ $assessment->result?->anxiety_level ?? 'N/A' }}</td>
                        <td>{{ $assessment->result?->stress_level ?? 'N/A' }}</td>
                        <td>{{ $assessment->result?->overall_status ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty">No assessments found for this student.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @else
        <p class="empty">No student found for the given student number.</p>
    @endif
</x-report-layout>
