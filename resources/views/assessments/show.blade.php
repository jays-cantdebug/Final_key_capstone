@php
    $severityBadgeClasses = [
        'Normal' => 'bg-[#EAF3DE] text-[#27500A]',
        'Mild' => 'bg-[#E6F1FB] text-[#0C447C]',
        'Moderate' => 'bg-[#FAEEDA] text-[#633806]',
        'Severe' => 'bg-[#FAECE7] text-[#712B13]',
        'Extremely Severe' => 'bg-[#FCEBEB] text-[#791F1F]',
    ];

    $flagTypeBadgeClasses = [
        'counseling_endorsement' => 'bg-[#E3F4F1] text-[#0F5C50]',
        'awareness_notification' => 'bg-[#F1E9FB] text-[#4A1E82]',
    ];

    $flagTypeLabels = [
        'counseling_endorsement' => 'Counseling Endorsement Required',
        'awareness_notification' => 'Awareness Notification',
    ];

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
                <h2 class="text-2xl font-semibold text-slate-900">{{ $assessment->student->full_name }}</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
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

    @if ($assessment->flaggedCases->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach ($assessment->flaggedCases as $flaggedCase)
                <span class="inline-flex rounded-full px-4 py-2 text-sm font-semibold {{ $flagTypeBadgeClasses[$flaggedCase->flag_type] ?? 'bg-slate-200 text-slate-600' }}">
                    {{ $flagTypeLabels[$flaggedCase->flag_type] ?? $flaggedCase->flag_type }}
                    <span class="ml-1 font-normal opacity-75">({{ ucfirst($flaggedCase->triggering_subscale) }})</span>
                </span>
            @endforeach
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
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">DASS-21 Scores</h3>
                    <p class="text-xs text-slate-500">Classified by: {{ $assessment->result->ai_provider }}</p>
                </div>
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
                @if ($assessment->result->used_non_official_thresholds)
                    <span class="mt-4 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800" title="This assessment was classified while non-official (overridden) DASS-21 thresholds were in effect.">
                        ⚠ Non-Official Thresholds
                    </span>
                @endif
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

        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Correct Prediction</h3>
                <p class="mt-1 text-xs text-slate-500">Confirm the AI's classification as accurate, or correct it. This does not change the scores or flags already recorded above.</p>

                @if ($feedback)
                    <div class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">
                            {{ $feedback->is_confirmed ? 'Confirmed' : 'Corrected' }} by {{ $feedback->psychometrician->name }}
                        </p>
                        @if (! $feedback->is_confirmed)
                            <ul class="mt-2 space-y-1 text-slate-600">
                                @if ($feedback->corrected_depression_level)
                                    <li>Depression &rarr; {{ $feedback->corrected_depression_level }}</li>
                                @endif
                                @if ($feedback->corrected_anxiety_level)
                                    <li>Anxiety &rarr; {{ $feedback->corrected_anxiety_level }}</li>
                                @endif
                                @if ($feedback->corrected_stress_level)
                                    <li>Stress &rarr; {{ $feedback->corrected_stress_level }}</li>
                                @endif
                            </ul>
                        @endif
                        @if ($feedback->notes)
                            <p class="mt-2 italic text-slate-600">"{{ $feedback->notes }}"</p>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('assessments.feedback.store', $assessment) }}" class="mt-4 space-y-4">
                    @csrf

                    @php
                        $severityOptions = ['Normal', 'Mild', 'Moderate', 'Severe', 'Extremely Severe'];
                    @endphp

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <x-input-label for="corrected_depression_level" :value="__('Depression')" />
                            <select id="corrected_depression_level" name="corrected_depression_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#1F6B3A] focus:ring-[#1F6B3A]">
                                <option value="">Unchanged</option>
                                @foreach ($severityOptions as $level)
                                    <option value="{{ $level }}" @selected(old('corrected_depression_level', $feedback?->corrected_depression_level) === $level)>{{ $level }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="corrected_anxiety_level" :value="__('Anxiety')" />
                            <select id="corrected_anxiety_level" name="corrected_anxiety_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#1F6B3A] focus:ring-[#1F6B3A]">
                                <option value="">Unchanged</option>
                                @foreach ($severityOptions as $level)
                                    <option value="{{ $level }}" @selected(old('corrected_anxiety_level', $feedback?->corrected_anxiety_level) === $level)>{{ $level }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="corrected_stress_level" :value="__('Stress')" />
                            <select id="corrected_stress_level" name="corrected_stress_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#1F6B3A] focus:ring-[#1F6B3A]">
                                <option value="">Unchanged</option>
                                @foreach ($severityOptions as $level)
                                    <option value="{{ $level }}" @selected(old('corrected_stress_level', $feedback?->corrected_stress_level) === $level)>{{ $level }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" :value="__('Notes (optional)')" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#1F6B3A] focus:ring-[#1F6B3A]">{{ old('notes', $feedback?->notes) }}</textarea>
                    </div>

                    <x-input-error :messages="$errors->get('corrected_depression_level')" class="mt-2" />
                    <x-input-error :messages="$errors->get('corrected_anxiety_level')" class="mt-2" />
                    <x-input-error :messages="$errors->get('corrected_stress_level')" class="mt-2" />

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" name="is_confirmed" value="1" class="inline-flex items-center justify-center rounded-2xl bg-[#1F6B3A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#185828]">
                            Confirm
                        </button>
                        <button type="submit" name="is_confirmed" value="0" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Correct
                        </button>
                        <a href="{{ route('assessments.show', $assessment) }}" class="inline-flex items-center justify-center rounded-2xl px-4 py-2 text-sm font-semibold text-slate-500 transition hover:bg-slate-50">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
