@extends('layouts.student-device')

{{--
    The questionnaire only: instructions and rating scale, each statement
    with its item number and four answer buttons (no subscale label), a
    progress counter, a save status line and Done. Each click is autosaved
    by resources/js/student-device.js.
--}}
@section('content')
    <div
        data-student-device="questionnaire"
        data-answer-url="{{ route('student-device.answer') }}"
        data-done-url="{{ route('student-device.done') }}"
        data-state-url="{{ route('student-device.state') }}"
        data-page-url="{{ route('student-device.show') }}"
        data-version="{{ $version }}"
        data-status-saved="{{ __('student_device.status_saved') }}"
        data-status-saving="{{ __('student_device.status_saving') }}"
        data-status-offline="{{ __('student_device.status_offline') }}"
        data-counter="{{ __('student_device.counter') }}"
        data-total="{{ $questions->count() }}"
        class="pb-32"
    >
        <noscript>
            <p class="mb-6 rounded-md border border-gold bg-white px-4 py-3 text-sm text-body">{{ __('student_device.noscript') }}</p>
        </noscript>

        @include('assessments.create._response-scale')

        <div class="space-y-4">
            @foreach ($questions as $question)
                {{-- data-saved: the answer the server holds, so the script can send a choice tapped before it ran. --}}
                <fieldset
                    data-question="{{ $question->id }}"
                    data-saved="{{ $responses[$question->id] ?? '' }}"
                    data-item="{{ $question->item_number }}"
                    data-required="{{ $question->is_required ? '1' : '0' }}"
                    data-missing="false"
                    class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm data-[missing=true]:border-2 data-[missing=true]:border-red-500"
                >
                    <legend class="text-sm font-medium text-body">{{ $question->item_number }}. {{ $question->question_text }}</legend>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ([0, 1, 2, 3] as $value)
                            <label class="cursor-pointer">
                                <input
                                    type="radio"
                                    name="answer-{{ $question->id }}"
                                    value="{{ $value }}"
                                    class="peer sr-only"
                                    autocomplete="off"
                                    @checked(($responses[$question->id] ?? null) === $value)
                                >
                                <span class="block rounded-md border-2 border-slate-200 px-3 py-2 text-center text-sm font-semibold text-slate-600 transition peer-checked:border-primary peer-checked:bg-tint peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-2">
                                    {{ $value }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>

        <div class="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(44,44,42,0.15)]">
            <div class="mx-auto flex w-full max-w-3xl flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-body" data-counter-text>{{ __('student_device.counter', ['answered' => $answered, 'total' => $questions->count()]) }}</p>
                    <p class="text-xs text-slate-500" data-save-status role="status" aria-live="polite">{{ __('student_device.status_saved') }}</p>
                    <p class="text-xs font-medium text-red-600" data-missing-message hidden>{{ __('student_device.missing') }}</p>
                </div>
                <button
                    type="button"
                    data-done
                    @disabled($answered < $required)
                    class="rounded-md bg-primary px-6 py-3 text-sm font-semibold text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ __('student_device.done') }}
                </button>
            </div>
        </div>

        {{-- Swapped in by the script when the draft is gone (cancelled, expired). --}}
        <template data-unavailable-template>
            @include('student-device._message', ['heading' => __('student_device.unavailable_heading'), 'body' => __('student_device.unavailable_body')])
        </template>
    </div>
@endsection
