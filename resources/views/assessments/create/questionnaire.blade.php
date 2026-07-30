@php
    $isRetake = $existingStudentId !== null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $isRetake ? 'Take Again' : 'New Assessment' }} &mdash; {{ $student->full_name }}</p>
            <h2 class="text-2xl font-semibold text-body">{{ $isRetake ? 'Retake: Questionnaire' : 'Step 2: Questionnaire' }}</h2>
        </div>
    </x-slot>

    @unless ($isRetake)
        @include('assessments.create._steps', ['currentStep' => 2])
    @endunless

    @if ($isRetake)
        <x-alert type="success" class="mb-6">
            This will add a new assessment for <span class="font-semibold">{{ $student->full_name }}</span> — their prior assessment history is kept and stays visible in Assessment History.
        </x-alert>
    @endif

    @include('assessments.create._response-scale')

    @if ($errors->any())
        <x-alert type="error" class="mb-6">
            Please answer all required questions before continuing.
        </x-alert>
    @endif

    <form method="POST" action="{{ route('assessments.create.questionnaire.store') }}">
        @csrf

        <div class="space-y-4">
            @foreach ($version->questions as $question)
                <x-dass-response-options :question="$question" :selected="old('responses.' . $question->id, $existingResponses[$question->id] ?? null)" />
            @endforeach
        </div>

        @if ($isRetake)
            <x-card class="mt-4">
                <label class="flex items-start gap-2">
                    <x-checkbox name="privacy_consent" value="1" class="mt-1" required />
                    <span class="text-sm text-slate-700">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('privacy_consent')" />
            </x-card>
        @endif

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button>{{ __('Next: Review & Submit') }}</x-primary-button>
            <x-secondary-button :href="$isRetake ? route('students.show', $existingStudentId) : route('assessments.create')">
                {{ __('Back') }}
            </x-secondary-button>
        </div>
    </form>
</x-app-layout>
