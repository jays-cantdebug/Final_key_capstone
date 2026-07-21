@php
    $severityBadgeStyles = [
        'Normal' => 'background:#EAF3DE;color:#27500A;',
        'Mild' => 'background:#E6F1FB;color:#0C447C;',
        'Moderate' => 'background:#FAEEDA;color:#633806;',
        'Severe' => 'background:#FAECE7;color:#712B13;',
        'Extremely Severe' => 'background:#FCEBEB;color:#791F1F;',
    ];
@endphp

<x-report-layout title="Assessment Summary Report">
    <dl>
        <dt>Date Range</dt>
        <dd>{{ $dateFrom ?? 'All time' }} &mdash; {{ $dateTo ?? 'Present' }}</dd>
        <dt>Course</dt>
        <dd>{{ $courses->firstWhere('id', (int) $courseId)?->course_code ?? 'All courses' }}</dd>
        <dt>Year Level</dt>
        <dd>{{ $yearLevels->firstWhere('id', (int) $yearLevelId)?->label ?? 'All year levels' }}</dd>
        <dt>Gender</dt>
        <dd>{{ $gender ?? 'All genders' }}</dd>
        <dt>Total Students</dt>
        <dd>{{ $totalStudents }}</dd>
        <dt>Total Assessments</dt>
        <dd>{{ $totalAssessments }}</dd>
        <dt>Total Counseling Endorsements</dt>
        <dd>{{ $counselingEndorsements }}</dd>
        <dt>Total Awareness Notifications</dt>
        <dd>{{ $awarenessNotifications }}</dd>
    </dl>

    @foreach ([
        'Depression' => $depressionBySeverity,
        'Anxiety' => $anxietyBySeverity,
        'Stress' => $stressBySeverity,
    ] as $conditionLabel => $conditionCounts)
        <h2>{{ $conditionLabel }}</h2>

        <x-severity-bar-chart :counts="$conditionCounts" />

        <table>
            <thead>
                <tr>
                    <th>Severity</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($conditionCounts as $severity => $count)
                    <tr>
                        <td><span class="badge" style="{{ $severityBadgeStyles[$severity] ?? '' }}">{{ $severity }}</span></td>
                        <td>{{ $count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</x-report-layout>
