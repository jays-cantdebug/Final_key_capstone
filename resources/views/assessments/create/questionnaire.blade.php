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

    <form
        method="POST"
        action="{{ route('assessments.create.questionnaire.store') }}"
        novalidate
        x-init="$nextTick(() => { const first = $el.querySelector('[data-field-invalid]'); if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); } })"
    >
        @csrf

        <div class="space-y-4">
            @foreach ($version->questions as $question)
                <x-dass-response-options
                    :question="$question"
                    :selected="old('responses.' . $question->id, $existingResponses[$question->id] ?? null)"
                    :invalid="$errors->has('responses.' . $question->id)"
                />
            @endforeach
        </div>

        @if ($isRetake)
            <div class="relative mt-4" x-data="{ show: {{ $errors->has('privacy_consent') ? 'true' : 'false' }} }">
                <label class="flex items-start gap-2">
                    <x-checkbox name="privacy_consent" value="1" class="mt-1" :invalid="$errors->has('privacy_consent')" @change="show = false" />
                    <span class="text-sm text-slate-700">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                </label>
                <x-field-error-tooltip :message="$errors->first('privacy_consent')" />
            </div>
        @endif

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button>{{ __('Next: Review & Submit') }}</x-primary-button>
            <x-secondary-button :href="$isRetake ? route('students.show', $existingStudentId) : route('assessments.create')">
                {{ __('Back') }}
            </x-secondary-button>
        </div>
    </form>
</x-app-layout>
