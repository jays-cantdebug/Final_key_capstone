<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">New Assessment &mdash; {{ $student->full_name }}</p>
            <h2 class="text-2xl font-semibold text-slate-900">Step 3: Assessment Result</h2>
        </div>
    </x-slot>

    @include('assessments.create._steps', ['currentStep' => 3])

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Ready to Submit</h3>
        <p class="mt-2 text-sm text-slate-600">
            You have answered {{ $responseCount }} of {{ $questionCount }} questions for
            <span class="font-semibold text-slate-900">{{ $student->full_name }}</span>
            ({{ $student->student_number }}) using questionnaire version v{{ $version?->version_number }}.
        </p>
        <p class="mt-2 text-sm text-slate-600">
            Clicking "Submit &amp; Calculate Score" will permanently save this assessment and compute the official DASS-21 results. The assessment cannot be edited after submission.
        </p>

        <form method="POST" action="{{ route('assessments.create.submit') }}" class="mt-6 flex items-center gap-3">
            @csrf
            <x-primary-button>{{ __('Submit & Calculate Score') }}</x-primary-button>
            <a href="{{ route('assessments.create.questionnaire') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                {{ __('Back') }}
            </a>
        </form>
    </div>
</x-app-layout>
