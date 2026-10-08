@extends('layouts.student-device')

{{--
    The questionnaire: instructions and rating scale, each statement with
    its item number and four answer buttons (no subscale label), a progress
    counter, a save status line and Done. Each click is autosaved by
    resources/js/student-device.js.

    When the student fills in their own details ($details not null), they
    come first on the same page (student-device/_details): the open form
    with the questions locked below it (one <fieldset disabled>; the script
    doesn't start autosave, and the server refuses answers anyway) until the
    details are saved, then only "Details saved ✓".
--}}
@section('content')
    @php
        $locked = $details === 'open';
    @endphp

    <div
        data-student-device="questionnaire"
        data-locked="{{ $locked ? '1' : '0' }}"
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

        @if ($details !== null)
            <h1 class="sr-only">{{ __('student_device.combined_heading') }}</h1>

            @include('student-device._details')

            <h2 id="questions" tabindex="-1" class="mb-4 scroll-mt-6 text-xl font-semibold text-body focus:outline-none">{{ __('student_device.questions_heading') }}</h2>
        @endif

        @if ($locked)
            <p id="questions-locked" class="mb-4 rounded-md border border-gold bg-white px-4 py-3 text-sm font-medium text-body" data-questions-locked>{{ __('student_device.questions_locked') }}</p>
        @endif

        @include('assessments.create._response-scale')

        <fieldset
            @disabled($locked)
            @if ($locked) aria-describedby="questions-locked" @endif
            data-questions
            class="space-y-4 disabled:opacity-60"
        >
            <legend class="sr-only">{{ __('student_device.statements_legend') }}</legend>

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
                            <label @class(['cursor-pointer' => ! $locked, 'cursor-not-allowed' => $locked])>
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
        </fieldset>

        <div class="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-4px_12px_-6px_rgba(44,44,42,0.15)]">
            <div class="mx-auto flex w-full max-w-3xl flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-body" data-counter-text>{{ __('student_device.counter', ['answered' => $answered, 'total' => $questions->count()]) }}</p>
                    @if ($details !== null)
                        <p @class(['text-xs font-medium', 'text-primary' => ! $locked, 'text-gold' => $locked]) data-details-indicator>{{ $locked ? __('student_device.identity_not_saved') : __('student_device.identity_saved') }}</p>
                    @endif
                    <p class="text-xs text-slate-500" data-save-status role="status" aria-live="polite">{{ $locked ? '' : __('student_device.status_saved') }}</p>
                    <p class="text-xs font-medium text-red-600" data-missing-message hidden>{{ __('student_device.missing') }}</p>
                </div>
                <button
                    type="button"
                    data-done
                    @disabled($locked || $answered < $required)
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
