{{--
    The student's own Step 1 details, at the top of the questionnaire page.

    $details 'open': exactly Step 1's fields (the shared
    assessments/create/_student-fields partial: the same order, labels,
    placeholders, options and styling), a "Your details" heading and a
    "Save details" button — and no staff attestation checkbox. A plain HTML
    form: it works without JavaScript (Alpine doesn't run here, so an error
    bubble stays visible until the next save). Its lists are the Active
    lookup rows only, never anything from a student record. $old and
    $fieldErrors are only the input this device just sent, shown back when
    it failed validation; the first invalid field then gets autofocus.
    Autocomplete is off, so a shared PC doesn't offer the previous
    student's name. data-student-device-details="open" marks this form, and
    only this form (the leak tests rely on it).

    $details 'saved': only "Details saved ✓". No field and no value, ever:
    not after a reload, New code, Return to student or a released hold.
--}}
@if ($details === 'saved')
    <section aria-labelledby="details-heading" data-details-saved class="mb-8 rounded-lg border border-slate-200 bg-white px-6 py-5 shadow-sm">
        <h2 id="details-heading" class="text-lg font-semibold text-body">{{ __('student_device.identity_heading') }}</h2>
        <p class="mt-1 text-sm font-medium text-primary">{{ __('student_device.identity_saved') }}</p>
    </section>
@else
    {{-- Step 1's own card, fields and button style. --}}
    <x-card class="mb-8">
        <h2 id="details-heading" class="text-xl font-semibold text-body">{{ __('student_device.identity_heading') }}</h2>

        <form
            method="POST"
            action="{{ route('student-device.identity') }}"
            class="mt-6"
            autocomplete="off"
            novalidate
            aria-labelledby="details-heading"
            data-student-device-details="open"
        >
            @include('assessments.create._student-fields', [
                'fieldValue' => fn (string $field): ?string => $old[$field] ?? null,
                'fieldErrors' => $fieldErrors,
                'idPrefix' => '',
                'autofocusField' => collect(\App\Http\Requests\AssessmentStudentRequest::IDENTITY_FIELDS)->first(fn (string $field): bool => $fieldErrors->has($field)) ?? 'first_name',
                'autocompleteOff' => true,
            ])

            <div class="mt-6 flex justify-center">
                <x-primary-button>{{ __('student_device.identity_submit') }}</x-primary-button>
            </div>
        </form>
    </x-card>
@endif
