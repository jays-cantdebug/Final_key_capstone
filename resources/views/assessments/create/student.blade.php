<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Step 1: Student Information</h2>
    </x-slot>

    @include('assessments.create._steps', ['currentStep' => 1])

    @error('student')
        <x-toast type="error">{{ $message }}</x-toast>
    @enderror

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    {{-- Or the student fills in this step (and the questionnaire) on a separate student device. --}}
    @if ($remoteDraftInProgress)
        <x-alert type="warning" class="mb-6" data-remote-in-progress>
            A student device session is in progress: the student is filling in their details there.
            <a href="{{ route('assessments.create.remote') }}" class="font-semibold underline">Open the student device page</a>
        </x-alert>
    @else
        <div class="mb-6 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-body dark:text-slate-100">Who will fill in the details?</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Type them below, or let the student fill in their details and the questionnaire on a separate student device while you follow along here.</p>
            </div>
            <form method="POST" action="{{ route('assessments.create.remote.store-student') }}" class="shrink-0">
                @csrf
                <x-secondary-button type="submit">{{ __('Let the student fill this in on their device') }}</x-secondary-button>
            </form>
        </div>
    @endif

    <x-card>
        <p class="text-sm text-slate-600 dark:text-slate-400">Every assessment begins with the student's information for this encounter.</p>

        <form
            method="POST"
            action="{{ route('assessments.create.student') }}"
            class="mt-6"
            novalidate
            x-init="$nextTick(() => { const first = $el.querySelector('[data-field-invalid]'); if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); } })"
        >
            @csrf

            @if ($duplicate)
                @include('assessments.create._duplicate-student', ['duplicate' => $duplicate])
            @endif

            {{-- The same fields as the student device and the live page's correction form. --}}
            @include('assessments.create._student-fields', [
                'fieldValue' => fn (string $field): mixed => old($field),
                'fieldErrors' => $errors,
                'idPrefix' => '',
                'autofocusField' => 'first_name',
                'autocompleteOff' => false,
            ])

            <div class="relative mt-6" x-data="{ show: {{ $errors->has('privacy_consent') ? 'true' : 'false' }} }">
                <label class="flex items-start gap-2">
                    <x-checkbox name="privacy_consent" value="1" class="mt-1" :checked="(bool) old('privacy_consent')" :invalid="$errors->has('privacy_consent')" @change="show = false" />
                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                </label>
                <x-field-error-tooltip :message="$errors->first('privacy_consent')" />
            </div>

            <div class="mt-6 flex justify-center">
                <x-primary-button>{{ __('Continue to Questionnaire') }}</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
