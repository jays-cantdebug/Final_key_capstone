@php
    $isRetake = $existingStudentId !== null;

    $subscales = [
        ['label' => 'Depression', 'score' => $review['scores']['depression_final_score'], 'level' => $review['depression_level']],
        ['label' => 'Anxiety', 'score' => $review['scores']['anxiety_final_score'], 'level' => $review['anxiety_level']],
        ['label' => 'Stress', 'score' => $review['scores']['stress_final_score'], 'level' => $review['stress_level']],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $isRetake ? 'Take Again' : 'New Assessment' }} &mdash; {{ $student->full_name }}</p>
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $isRetake ? 'Retake: Review AI Classification' : 'Step 3: Review AI Classification' }}</h2>
        </div>
    </x-slot>

    @unless ($isRetake)
        @include('assessments.create._steps', ['currentStep' => 3])
    @endunless

    @if ($errors->any())
        <x-alert type="error" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <x-card class="mb-6">
        <h3 class="text-lg font-semibold text-body dark:text-slate-100">Student Information</h3>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
            <span class="font-semibold text-body dark:text-slate-100">{{ $student->full_name }}</span>
            &mdash; questionnaire version v{{ $version->version_number }}.
        </p>
    </x-card>

    <x-card>
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-body dark:text-slate-100">DASS-21 Scores &mdash; AI Proposed Classification</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Classified by: {{ $review['ai_provider'] }}</p>
        </div>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
            Nothing has been saved yet. Review the AI's classification below, then Confirm it as accurate or Correct it before saving.
        </p>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach ($subscales as $subscale)
                <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $subscale['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-body dark:text-slate-100">{{ $subscale['score'] }}</p>
                    <x-severity-badge :level="$subscale['level']" class="mt-2" />
                </div>
            @endforeach
        </div>
        @if ($review['used_non_official_thresholds'])
            <x-badge color="amber" class="mt-4" title="This classification was computed while non-official (overridden) DASS-21 thresholds were in effect.">
                ⚠ Non-Official Thresholds
            </x-badge>
        @endif

        <hr class="my-6 border-slate-200 dark:border-slate-700">

        <h3 class="text-lg font-semibold text-body dark:text-slate-100">Confirm or Correct</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            @if ($isRetake)
                Saving will add this as a new assessment for {{ $student->full_name }}, alongside their existing history. The assessment cannot be edited after saving.
            @else
                Saving will permanently record this assessment using the classification confirmed or corrected below. A Guidance Counselor is only notified about this final, reviewed classification &mdash; never the AI's raw proposal shown above.
            @endif
        </p>

        <div class="mt-4">
            @include('assessments._prediction-feedback-form', [
                'action' => route('assessments.create.submit'),
                'feedback' => null,
                'confirmLabel' => 'Confirm & Save',
                'correctLabel' => 'Correct & Save',
                'cancelHref' => route('assessments.create.questionnaire'),
                'cancelLabel' => 'Back',
            ])
        </div>
    </x-card>

    <x-table class="mt-6">
        <x-slot:header>
            <h3 class="text-lg font-semibold text-body dark:text-slate-100">Student Responses</h3>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>#</x-table.th>
            <x-table.th>Question</x-table.th>
            <x-table.th>Subscale</x-table.th>
            <x-table.th>Answer</x-table.th>
        </x-slot:head>

        @foreach ($version->questions as $question)
            <tr>
                <x-table.td>{{ $question->item_number }}</x-table.td>
                <x-table.td class="!whitespace-normal">{{ $question->question_text }}</x-table.td>
                <x-table.td>{{ $question->subscale }}</x-table.td>
                <x-table.td class="font-semibold text-body dark:text-slate-100">{{ $responses[$question->id] ?? '—' }}</x-table.td>
            </tr>
        @endforeach
    </x-table>
</x-app-layout>
