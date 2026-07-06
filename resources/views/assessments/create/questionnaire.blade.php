<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">New Assessment &mdash; {{ $student->full_name }}</p>
            <h2 class="text-2xl font-semibold text-slate-900">Step 2: Questionnaire</h2>
        </div>
    </x-slot>

    @include('assessments.create._steps', ['currentStep' => 2])

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            Please answer all required questions before continuing.
        </div>
    @endif

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
        <span class="font-semibold text-slate-900">Response scale:</span>
        0 = Did not apply to me at all &middot;
        1 = Applied to some degree, or some of the time &middot;
        2 = Applied to a considerable degree, or a good part of time &middot;
        3 = Applied very much, or most of the time
    </div>

    <form method="POST" action="{{ route('assessments.create.questionnaire.store') }}">
        @csrf

        <div class="space-y-4">
            @foreach ($version->questions as $question)
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <p class="text-sm font-medium text-slate-900">{{ $question->item_number }}. {{ $question->question_text }}</p>
                        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $question->subscale }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-4 gap-2">
                        @foreach ([0, 1, 2, 3] as $value)
                            <label class="cursor-pointer">
                                <input
                                    type="radio"
                                    name="responses[{{ $question->id }}]"
                                    value="{{ $value }}"
                                    class="peer sr-only"
                                    @checked((string) old("responses.$question->id", $existingResponses[$question->id] ?? null) === (string) $value)
                                    required
                                />
                                <div class="rounded-xl border-2 border-slate-200 px-3 py-2 text-center text-sm font-semibold text-slate-600 transition peer-checked:border-[#1F6B3A] peer-checked:bg-[#EAF3EC] peer-checked:text-[#1F6B3A]">
                                    {{ $value }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('responses.' . $question->id)" />
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button>{{ __('Next: Review & Submit') }}</x-primary-button>
            <a href="{{ route('assessments.create') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                {{ __('Back') }}
            </a>
        </div>
    </form>
</x-app-layout>
