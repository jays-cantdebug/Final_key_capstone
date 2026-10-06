@php
    $severityBadgeStyles = [
        'Normal' => 'background:#EAF3DE;color:#27500A;',
        'Mild' => 'background:#E6F1FB;color:#0C447C;',
        'Moderate' => 'background:#FAEEDA;color:#633806;',
        'Severe' => 'background:#FAECE7;color:#712B13;',
        'Extremely Severe' => 'background:#FCEBEB;color:#791F1F;',
    ];

    // A Guidance Counselor's copy shows the reviewed (effective) levels,
    // never the AI's raw ones; the Psychometrician's copy is unchanged.
    $counselorView = auth()->user()?->hasRole('guidance_counselor') ?? false;
@endphp

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
                    @php
                        $levels = $counselorView
                            ? [
                                $assessment->effectiveLevel('depression'),
                                $assessment->effectiveLevel('anxiety'),
                                $assessment->effectiveLevel('stress'),
                                $assessment->effectiveHighestSeverityLevel(),
                            ]
                            : [
                                $assessment->result?->depression_level,
                                $assessment->result?->anxiety_level,
                                $assessment->result?->stress_level,
                                $assessment->result?->highestSeverityLevel(),
                            ];
                    @endphp
                    <tr>
                        <td>{{ $assessment->submitted_at->format('M d, Y g:i A') }}</td>
                        @foreach ($levels as $level)
                            <td><span class="badge" style="{{ $severityBadgeStyles[$level] ?? '' }}">{{ $level ?? 'N/A' }}</span></td>
                        @endforeach
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
