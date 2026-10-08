{{--
    Live page, for a draft where the student fills in Step 1 on the device:
    the details as the student sent them (or as corrected here), the
    duplicate-student panel when the name matches a student (shown only
    here, never on the device), and the correction form. The answers below
    stay read-only; this form only ever sends the Step 1 fields.
--}}
@php
    $identityErrors = $errors->getBag('identity');
@endphp

<div class="space-y-4" data-remote-identity>
    @if ($identity === null)
        <div class="rounded-lg border border-slate-200 bg-white px-5 py-4 text-sm text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" data-identity-waiting>
            <p class="font-semibold text-body dark:text-slate-100">Student details</p>
            <p class="mt-1">Waiting for the student’s details. The student types them on the device after acknowledging the privacy notice; they appear here as soon as they are sent.</p>
        </div>
    @else
        @if ($duplicate)
            <div data-identity-duplicate="{{ $duplicate['kind'] }}">
                @if ($draft->isHeld())
                    <p class="mb-2 text-sm font-semibold text-red-700 dark:text-red-400" data-held-reason>
                        The student device was stopped because the name the student entered matches an existing active student. The student sees only the generic “not available” message. Use Take Again for that student (the retake needs a new code), or correct the name below if it was mistyped.
                    </p>
                @endif
                @include('assessments.create._duplicate-student', ['duplicate' => $duplicate, 'controls' => false])
            </div>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm font-semibold text-body dark:text-slate-100">Student details</p>
                <p class="text-xs text-slate-500 dark:text-slate-400" data-identity-source>
                    Entered by the student at {{ $draft->identity_submitted_at->format('g:i A') }}@if ($draft->identity_corrected_at) · corrected by you at {{ $draft->identity_corrected_at->format('g:i A') }}@endif
                </p>
            </div>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Check these with the student in person and correct any typo before submitting. The answers can’t be changed here.</p>

            <form method="POST" action="{{ route('assessments.create.remote.identity') }}" class="mt-4" novalidate data-identity-form>
                @csrf
                @method('PUT')

                {{-- The same fields as Step 1 and the student device. --}}
                @include('assessments.create._student-fields', [
                    'fieldValue' => $identityValue,
                    'fieldErrors' => $identityErrors,
                    'idPrefix' => 'identity_',
                    'autofocusField' => null,
                    'autocompleteOff' => true,
                ])

                <div class="mt-6">
                    <x-secondary-button type="submit">{{ __('Save corrections') }}</x-secondary-button>
                </div>
            </form>
        </div>
    @endif
</div>
