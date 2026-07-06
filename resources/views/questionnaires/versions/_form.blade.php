@php
    /** @var \App\Models\QuestionnaireVersion|null $version */
    $version = $version ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="version_number" :value="__('Version Number')" />
        <x-text-input id="version_number" name="version_number" type="number" min="1" class="mt-1 block w-full" :value="old('version_number', $version?->version_number)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('version_number')" />
    </div>

    <div>
        <x-input-label for="effective_date" :value="__('Effective Date')" />
        <x-text-input id="effective_date" name="effective_date" type="date" class="mt-1 block w-full" :value="old('effective_date', $version?->effective_date?->format('Y-m-d'))" required />
        <x-input-error class="mt-2" :messages="$errors->get('effective_date')" />
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Version') }}
    </x-primary-button>

    <a href="{{ route('questionnaires.show', $questionnaire) }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
        {{ __('Cancel') }}
    </a>
</div>
