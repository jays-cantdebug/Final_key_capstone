<x-report-layout title="Flagged Students Report">
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Student #</th>
                <th>Course / Year / Section</th>
                <th>Assessment Date</th>
                <th>Highest Severity</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($flaggedCases as $flaggedCase)
                <tr>
                    <td>{{ $flaggedCase->assessment->student->full_name }}</td>
                    <td>{{ $flaggedCase->assessment->student->student_number }}</td>
                    <td>
                        {{ $flaggedCase->assessment->student->course?->course_code }} /
                        {{ $flaggedCase->assessment->student->yearLevel?->label }} /
                        {{ $flaggedCase->assessment->student->section?->section_name }}
                    </td>
                    <td>{{ $flaggedCase->assessment->submitted_at->format('M d, Y') }}</td>
                    <td>{{ $flaggedCase->highest_severity }}</td>
                    <td>{{ $flaggedCase->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">No flagged cases found for the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-report-layout>
