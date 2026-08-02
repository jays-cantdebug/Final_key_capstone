@php
    $subscales = [
        ['label' => 'Depression', 'score' => $assessment->result->depression_final_score, 'level' => $assessment->result->depression_level],
        ['label' => 'Anxiety', 'score' => $assessment->result->anxiety_final_score, 'level' => $assessment->result->anxiety_level],
        ['label' => 'Stress', 'score' => $assessment->result->stress_final_score, 'level' => $assessment->result->stress_level],
    ];

    $feedback = $assessment->predictionFeedback;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Assessment Result</p>
                <h2 class="text-2xl font-semibold text-body">{{ $assessment->student->full_name }}</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($backToCounselingSession)
                    <x-secondary-button :href="route('counseling-sessions.show', $backToCounselingSession)">
                        Back to Counseling Session
                    </x-secondary-button>
                @endif
                <x-secondary-button :href="route('reports.assessment.print', $assessment)" target="_blank">
                    Print Report
                </x-secondary-button>
                <x-primary-button :href="route('reports.assessment.pdf', $assessment)">
                    Download PDF
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($assessment->flaggedCases->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach ($assessment->flaggedCases as $flaggedCase)
                <x-flag-badge :type="$flaggedCase->flag_type" class="!px-4 !py-2 !text-sm">
                    <span class="ml-1 font-normal opacity-75">({{ ucfirst($flaggedCase->triggering_subscale) }})</span>
                </x-flag-badge>
            @endforeach
        </div>
    @endif

    <div @class(['grid gap-6', 'lg:grid-cols-[1.4fr_1fr]' => $feedback])>
        <div class="min-w-0 space-y-6">
            <x-card>
                <h3 class="text-lg font-semibold text-body">Student Information</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Student Number</dt>
                        <dd class="mt-1 text-sm font-medium text-body">{{ $assessment->student->student_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Gender</dt>
                        <dd class="mt-1 text-sm font-medium text-body">{{ $assessment->student->gender }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Course</dt>
                        <dd class="mt-1 text-sm font-medium text-body">{{ $assessment->student->course?->course_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Year Level / Section</dt>
                        <dd class="mt-1 text-sm font-medium text-body">{{ $assessment->student->yearLevel?->label }} &mdash; {{ $assessment->student->section?->section_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Assessment Date</dt>
                        <dd class="mt-1 text-sm font-medium text-body">{{ $assessment->submitted_at->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Questionnaire Version</dt>
                        <dd class="mt-1 text-sm font-medium text-body">{{ $assessment->questionnaireVersion->questionnaire->title }} v{{ $assessment->questionnaireVersion->version_number }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-body">DASS-21 Scores</h3>
                    <p class="text-xs text-slate-500">Classified by: {{ $assessment->result->ai_provider }}</p>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ($subscales as $subscale)
                        <div class="rounded-lg bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $subscale['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold text-body">{{ $subscale['score'] }}</p>
                            <x-severity-badge :level="$subscale['level']" class="mt-2" />
                        </div>
                    @endforeach
                </div>
                @if ($assessment->result->used_non_official_thresholds)
                    <x-badge color="amber" class="mt-4" title="This assessment was classified while non-official (overridden) DASS-21 thresholds were in effect.">
                        ⚠ Non-Official Thresholds
                    </x-badge>
                @endif
            </x-card>

        </div>

        @if ($feedback)
        <div class="min-w-0 space-y-6">
            <x-card>
                <h3 class="text-lg font-semibold text-body">Prediction Feedback</h3>
                <p class="mt-1 text-xs text-slate-500">The Psychometrician's review of this assessment's AI classification, recorded before saving. This does not change the scores or flags recorded above.</p>

                <div class="mt-4">
                    @include('assessments._prediction-feedback-summary', ['feedback' => $feedback])
                </div>
            </x-card>
        </div>
        @endif
    </div>

    <x-table class="mt-6">
        <x-slot:header>
            <h3 class="text-lg font-semibold text-body">Student Responses</h3>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>#</x-table.th>
            <x-table.th>Question</x-table.th>
            <x-table.th>Subscale</x-table.th>
            <x-table.th>Answer</x-table.th>
        </x-slot:head>

        @foreach ($assessment->responses->sortBy('question.display_order') as $response)
            <tr>
                <x-table.td>{{ $response->question->item_number }}</x-table.td>
                <x-table.td class="!whitespace-normal">{{ $response->question->question_text }}</x-table.td>
                <x-table.td>{{ $response->question->subscale }}</x-table.td>
                <x-table.td class="font-semibold text-body">{{ $response->answer_value }}</x-table.td>
            </tr>
        @endforeach
    </x-table>
</x-app-layout>
