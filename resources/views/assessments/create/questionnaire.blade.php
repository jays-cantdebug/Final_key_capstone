@php
    $isRetake = $existingStudentId !== null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $isRetake ? 'Retake: Questionnaire' : 'Step 2: Questionnaire' }} &mdash; {{ $student->full_name }}</h2>
    </x-slot>

    @unless ($isRetake)
        @include('assessments.create._steps', ['currentStep' => 2])
    @endunless

    @if ($isRetake)
        <x-alert type="success" class="mb-6">
            This will add a new assessment for <span class="font-semibold">{{ $student->full_name }}</span> — their prior assessment history is kept and stays visible in Assessment History.
        </x-alert>
    @endif

    @if ($errors->has('questionnaire') || $errors->has('questionnaire_version_id'))
        <x-alert type="warning" class="mb-6">
            {{ $errors->first('questionnaire') ?: $errors->first('questionnaire_version_id') }}
        </x-alert>
    @elseif ($questionnaireChanged)
        <x-alert type="warning" class="mb-6">
            The active questionnaire has changed since these questions were answered. Please answer the questions below; your review will use this version.
        </x-alert>
    @endif

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($answeredOnStudentDevice)
        @include('assessments.create._answered-on-device')
    @else
    @include('assessments.create._device-choice')

    {{-- Student entry only: the student answers on the student PC, so no "answer on this PC" form (its POST is refused too). --}}
    @unless (\App\Services\RemoteAssessmentService::studentEntryOnly())
    @include('assessments.create._response-scale')

    @include('assessments.create._analyzing-overlay', ['part' => 'style'])

    <form
        method="POST"
        action="{{ route('assessments.create.questionnaire.store') }}"
        novalidate
        x-data="{ submitting: false }"
        x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true; setTimeout(() => $el.querySelectorAll('button[type=submit]').forEach((button) => button.disabled = true))"
        x-on:pageshow.window="if ($event.persisted) { submitting = false; $el.querySelectorAll('button[type=submit]').forEach((button) => button.disabled = false) }"
        x-effect="document.body.classList.toggle('overflow-y-hidden', submitting)"
        x-init="$nextTick(() => { const first = $el.querySelector('[data-field-invalid]'); if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); } })"
    >
        @csrf
        <input type="hidden" name="questionnaire_version_id" value="{{ $version->id }}" />

        @include('assessments.create._analyzing-overlay', ['part' => 'overlay'])

        <div x-bind:inert="submitting">
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
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                    </label>
                    <x-field-error-tooltip :message="$errors->first('privacy_consent')" />
                </div>
            @endif

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button x-bind:disabled="submitting">
                    <span x-show="!submitting">{{ __('Continue to Review') }}</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2" style="display: none;">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ __('Analyzing…') }}
                    </span>
                </x-primary-button>
                <x-secondary-button :href="$isRetake ? route('students.show', $existingStudentId) : route('assessments.create')">
                    {{ __('Back') }}
                </x-secondary-button>
            </div>
        </div>
    </form>
    @endunless
    {{-- Student entry only: Take Again keeps its Back to the student profile; a new student has no Back (Step 1 happened on the student device). --}}
    @if (\App\Services\RemoteAssessmentService::studentEntryOnly() && $isRetake)
        <div class="mt-6">
            <x-secondary-button :href="route('students.show', $existingStudentId)">{{ __('Back') }}</x-secondary-button>
        </div>
    @endif
    @endif
</x-app-layout>
