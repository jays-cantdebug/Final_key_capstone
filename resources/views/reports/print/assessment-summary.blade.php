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
                    <td>{{ $severity }}</td>
                    <td>{{ $count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-report-layout>
