@php
    $severityBadgeStyles = [
        'Normal' => 'background:#EAF3DE;color:#27500A;',
        'Mild' => 'background:#E6F1FB;color:#0C447C;',
        'Moderate' => 'background:#FAEEDA;color:#633806;',
        'Severe' => 'background:#FAECE7;color:#712B13;',
        'Extremely Severe' => 'background:#FCEBEB;color:#791F1F;',
    ];
@endphp

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
                    <td><span class="badge" style="{{ $severityBadgeStyles[$assessment->result?->highestSeverityLevel()] ?? '' }}">{{ $assessment->result?->highestSeverityLevel() ?? 'N/A' }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">No assessments were submitted in this month.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-report-layout>
