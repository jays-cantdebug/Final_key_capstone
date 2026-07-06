@php
    $severityBadgeClasses = [
        'Normal' => 'bg-[#EAF3DE] text-[#27500A]',
        'Mild' => 'bg-[#E6F1FB] text-[#0C447C]',
        'Moderate' => 'bg-[#FAEEDA] text-[#633806]',
        'Severe' => 'bg-[#FAECE7] text-[#712B13]',
        'Extremely Severe' => 'bg-[#FCEBEB] text-[#791F1F]',
    ];

    $subscales = [
        ['label' => 'Depression', 'score' => $assessment->result->depression_final_score, 'level' => $assessment->result->depression_level],
        ['label' => 'Anxiety', 'score' => $assessment->result->anxiety_final_score, 'level' => $assessment->result->anxiety_level],
        ['label' => 'Stress', 'score' => $assessment->result->stress_final_score, 'level' => $assessment->result->stress_level],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Assessment Result</p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $assessment->student->full_name }}</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $severityBadgeClasses[$assessment->result->overall_status] ?? 'bg-slate-200 text-slate-600' }}">
                    Overall: {{ $assessment->result->overall_status }}
                </span>
                <a href="{{ route('reports.assessment.print', $assessment) }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Print Report
                </a>
                <a href="{{ route('reports.assessment.pdf', $assessment) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Download PDF
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($assessment->result->overall_flag)
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            This assessment meets the notification severity threshold and has been flagged for Guidance Counselor follow-up.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Student Information</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Student Number</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $assessment->student->student_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Gender</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $assessment->student->gender }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Course</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $assessment->student->course?->course_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Year Level / Section</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $assessment->student->yearLevel?->label }} &mdash; {{ $assessment->student->section?->section_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Assessment Date</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $assessment->submitted_at->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Questionnaire Version</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $assessment->questionnaireVersion->questionnaire->title }} v{{ $assessment->questionnaireVersion->version_number }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">DASS-21 Scores</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ($subscales as $subscale)
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $subscale['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $subscale['score'] }}</p>
                            <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $severityBadgeClasses[$subscale['level']] ?? 'bg-slate-200 text-slate-600' }}">
                                {{ $subscale['level'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Student Responses</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Question</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Subscale</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Answer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($assessment->responses->sortBy('question.display_order') as $response)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-slate-700">{{ $response->question->item_number }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-700">{{ $response->question->question_text }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-700">{{ $response->question->subscale }}</td>
                                    <td class="px-4 py-2 text-sm font-semibold text-slate-900">{{ $response->answer_value }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">AI Prediction</h3>
            <p class="mt-1 text-xs text-slate-500">Powered by the AI Prediction Module (not yet integrated)</p>

            <dl class="mt-4 space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mental Health Status</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $aiPrediction['mentalHealthStatus'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Risk Level</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $aiPrediction['riskLevel'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Interpretation</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $aiPrediction['interpretation'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Recommendation</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $aiPrediction['recommendation'] }}</dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>
