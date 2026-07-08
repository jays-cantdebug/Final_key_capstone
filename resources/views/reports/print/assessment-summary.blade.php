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
        <dt>Total Assessments</dt>
        <dd>{{ $total }}</dd>
        <dt>Flagged Assessments</dt>
        <dd>{{ $flaggedCount }}</dd>
    </dl>

    <h2>By Overall Severity</h2>
    <table>
        <thead>
            <tr>
                <th>Severity</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bySeverity as $severity => $count)
                <tr>
                    <td><span class="badge" style="{{ $severityBadgeStyles[$severity] ?? '' }}">{{ $severity }}</span></td>
                    <td>{{ $count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-report-layout>
