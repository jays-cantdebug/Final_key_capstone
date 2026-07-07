@php
    $subscales = [
        ['label' => 'Depression', 'score' => $assessment->result->depression_final_score, 'level' => $assessment->result->depression_level],
        ['label' => 'Anxiety', 'score' => $assessment->result->anxiety_final_score, 'level' => $assessment->result->anxiety_level],
        ['label' => 'Stress', 'score' => $assessment->result->stress_final_score, 'level' => $assessment->result->stress_level],
    ];
@endphp

<x-report-layout title="Assessment Report">
    <h2>Student Information</h2>
    <dl>
        <dt>Name</dt>
        <dd>{{ $assessment->student->full_name }}</dd>
        <dt>Student Number</dt>
        <dd>{{ $assessment->student->student_number }}</dd>
        <dt>Course</dt>
        <dd>{{ $assessment->student->course?->course_code }}</dd>
        <dt>Year Level / Section</dt>
        <dd>{{ $assessment->student->yearLevel?->label }} / {{ $assessment->student->section?->section_name }}</dd>
        <dt>Assessment Date</dt>
        <dd>{{ $assessment->submitted_at->format('M d, Y g:i A') }}</dd>
        <dt>Questionnaire Version</dt>
        <dd>{{ $assessment->questionnaireVersion->questionnaire->title }} v{{ $assessment->questionnaireVersion->version_number }}</dd>
        <dt>Administered By</dt>
        <dd>{{ $assessment->psychometrician->name }}</dd>
    </dl>

    <h2>DASS-21 Scores</h2>
    <table>
        <thead>
            <tr>
                <th>Subscale</th>
                <th>Final Score</th>
                <th>Severity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subscales as $subscale)
                <tr>
                    <td>{{ $subscale['label'] }}</td>
                    <td>{{ $subscale['score'] }}</td>
                    <td>{{ $subscale['level'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Overall</strong></td>
                <td></td>
                <td><strong>{{ $assessment->result->highestSeverityLevel() }}</strong></td>
            </tr>
        </tbody>
    </table>

    <h2>Student Responses</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Question</th>
                <th>Subscale</th>
                <th>Answer</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assessment->responses->sortBy('question.display_order') as $response)
                <tr>
                    <td>{{ $response->question->item_number }}</td>
                    <td>{{ $response->question->question_text }}</td>
                    <td>{{ $response->question->subscale }}</td>
                    <td>{{ $response->answer_value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-report-layout>
