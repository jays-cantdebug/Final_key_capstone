@php
    $flagBadgeStyles = [
        'counseling_endorsement' => 'background:#E3F4F1;color:#0F5C50;',
        'awareness_notification' => 'background:#F1E9FB;color:#4A1E82;',
    ];
@endphp

<x-report-layout title="Flagged Students Report">
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Student #</th>
                <th>Course / Year / Section</th>
                <th>Assessment Date</th>
                <th>Flag</th>
                <th>Triggering Subscale</th>
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
                    <td><span class="badge" style="{{ $flagBadgeStyles[$flaggedCase->flag_type] ?? '' }}">{{ $flaggedCase->flag_type === 'counseling_endorsement' ? 'Counseling Endorsement' : 'Awareness Notification' }}</span></td>
                    <td>{{ ucfirst($flaggedCase->triggering_subscale) }}</td>
                    <td>{{ $flaggedCase->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">No flagged cases found for the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-report-layout>
