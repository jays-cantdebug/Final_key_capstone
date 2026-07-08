<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">New Assessment &mdash; {{ $student->full_name }}</p>
            <h2 class="text-2xl font-semibold text-body">Step 2: Questionnaire</h2>
        </div>
    </x-slot>

    @include('assessments.create._steps', ['currentStep' => 2])

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

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button>{{ __('Next: Review & Submit') }}</x-primary-button>
            <x-secondary-button :href="route('assessments.create')">
                {{ __('Back') }}
            </x-secondary-button>
        </div>
    </form>
</x-app-layout>
