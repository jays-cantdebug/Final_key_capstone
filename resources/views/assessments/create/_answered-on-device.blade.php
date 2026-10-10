{{-- Step 2 after a student-device Submit: the student's answers, read-only. --}}
<x-alert type="success" class="mb-6">
    These answers were given on the student device. They can’t be changed here; to change them, send the questionnaire to the student device again.
</x-alert>

@include('assessments.create._response-scale')

<div class="space-y-4">
    @foreach ($answeredVersion->questions as $question)
        <x-dass-response-options
            :question="$question"
            :selected="$existingResponses[$question->id] ?? null"
            :disabled="true"
        />
    @endforeach
</div>

<div class="mt-6 flex flex-wrap items-center gap-3">
    <x-primary-button :href="route('assessments.create.result')">{{ __('Continue to Review') }}</x-primary-button>
    <form method="POST" action="{{ route('assessments.create.remote.store') }}">
        @csrf
        <x-secondary-button type="submit">{{ __('Send to student device again') }}</x-secondary-button>
    </form>
</div>
