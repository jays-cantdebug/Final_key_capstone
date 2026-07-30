@php
    $isRetake = $existingStudentId !== null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $isRetake ? 'Take Again' : 'New Assessment' }} &mdash; {{ $student->full_name }}</p>
            <h2 class="text-2xl font-semibold text-body">{{ $isRetake ? 'Retake: Review & Submit' : 'Step 3: Assessment Result' }}</h2>
        </div>
    </x-slot>

    @unless ($isRetake)
        @include('assessments.create._steps', ['currentStep' => 3])
    @endunless

    @if ($errors->any())
        <x-alert type="error" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <x-card>
        <h3 class="text-lg font-semibold text-body">Ready to Submit</h3>
        <p class="mt-2 text-sm text-slate-600">
            You have answered {{ $responseCount }} of {{ $questionCount }} questions for
            <span class="font-semibold text-body">{{ $student->full_name }}</span>
            using questionnaire version v{{ $version?->version_number }}.
        </p>
        <p class="mt-2 text-sm text-slate-600">
            @if ($isRetake)
                Clicking "Submit &amp; Calculate Score" will add this as a new assessment for {{ $student->full_name }}, alongside their existing history, and compute the official DASS-21 results. The assessment cannot be edited after submission.
            @else
                Clicking "Submit &amp; Calculate Score" will permanently save this assessment and compute the official DASS-21 results. The assessment cannot be edited after submission.
            @endif
        </p>

        <form method="POST" action="{{ route('assessments.create.submit') }}" class="mt-6 flex items-center gap-3">
            @csrf
            <x-primary-button>{{ __('Submit & Calculate Score') }}</x-primary-button>
            <x-secondary-button :href="route('assessments.create.questionnaire')">
                {{ __('Back') }}
            </x-secondary-button>
        </form>
    </x-card>
</x-app-layout>
