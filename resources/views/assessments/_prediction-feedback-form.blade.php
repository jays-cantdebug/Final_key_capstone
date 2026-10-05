@php
    $severityOptions = ['Normal', 'Mild', 'Moderate', 'Severe', 'Extremely Severe'];
@endphp

{{--
    Double-submit guard: only the first submit goes through. The clicked
    button's own name/value (is_confirmed) must still be sent, and a
    browser leaves a *disabled* submitter out of the request, so the
    buttons are only made unclickable at once (pointer-events/opacity) and
    get the real `disabled` attribute on the next tick, after the request
    has been built. Re-enabled if the page is restored from the back/forward
    cache so it is never left stuck.
--}}
<form
    method="POST"
    action="{{ $action }}"
    class="space-y-4"
    x-data="{ submitting: false }"
    x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true; setTimeout(() => $el.querySelectorAll('button[type=submit]').forEach((button) => button.disabled = true))"
    x-on:pageshow.window="if ($event.persisted) { submitting = false; $el.querySelectorAll('button[type=submit]').forEach((button) => button.disabled = false) }"
>
    @csrf

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
            <x-input-label for="corrected_depression_level" class="!text-xs !font-semibold uppercase tracking-wider !text-slate-500 dark:!text-slate-400" :value="__('Depression')" />
            <x-select id="corrected_depression_level" name="corrected_depression_level" class="mt-2 block w-full text-sm">
                <option value="">Unchanged</option>
                @foreach ($severityOptions as $level)
                    <option value="{{ $level }}" @selected(old('corrected_depression_level', $feedback?->corrected_depression_level) === $level)>{{ $level }}</option>
                @endforeach
            </x-select>
        </div>
        <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
            <x-input-label for="corrected_anxiety_level" class="!text-xs !font-semibold uppercase tracking-wider !text-slate-500 dark:!text-slate-400" :value="__('Anxiety')" />
            <x-select id="corrected_anxiety_level" name="corrected_anxiety_level" class="mt-2 block w-full text-sm">
                <option value="">Unchanged</option>
                @foreach ($severityOptions as $level)
                    <option value="{{ $level }}" @selected(old('corrected_anxiety_level', $feedback?->corrected_anxiety_level) === $level)>{{ $level }}</option>
                @endforeach
            </x-select>
        </div>
        <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
            <x-input-label for="corrected_stress_level" class="!text-xs !font-semibold uppercase tracking-wider !text-slate-500 dark:!text-slate-400" :value="__('Stress')" />
            <x-select id="corrected_stress_level" name="corrected_stress_level" class="mt-2 block w-full text-sm">
                <option value="">Unchanged</option>
                @foreach ($severityOptions as $level)
                    <option value="{{ $level }}" @selected(old('corrected_stress_level', $feedback?->corrected_stress_level) === $level)>{{ $level }}</option>
                @endforeach
            </x-select>
        </div>
    </div>

    <div>
        <x-input-label for="notes" :value="__('Notes (optional)')" />
        <x-textarea id="notes" name="notes" rows="3" class="mt-1 block w-full text-sm">{{ old('notes', $feedback?->notes) }}</x-textarea>
    </div>

    <x-input-error :messages="$errors->get('corrected_depression_level')" class="mt-2" />
    <x-input-error :messages="$errors->get('corrected_anxiety_level')" class="mt-2" />
    <x-input-error :messages="$errors->get('corrected_stress_level')" class="mt-2" />

    <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
        <button type="submit" name="is_confirmed" value="1" x-bind:aria-disabled="submitting" x-bind:class="submitting && 'pointer-events-none opacity-60'" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark dark:bg-primary-soft dark:hover:bg-primary-soft/90">
            {{ $confirmLabel ?? 'Confirm' }}
        </button>
        <button type="submit" name="is_confirmed" value="0" x-bind:aria-disabled="submitting" x-bind:class="submitting && 'pointer-events-none opacity-60'" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 shadow-sm transition hover:bg-slate-50 dark:hover:bg-slate-700">
            {{ $correctLabel ?? 'Correct' }}
        </button>
        @isset($cancelHref)
            <a href="{{ $cancelHref }}" class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold text-slate-500 dark:text-slate-400 transition hover:bg-slate-50 dark:hover:bg-slate-700">
                {{ $cancelLabel ?? 'Cancel' }}
            </a>
        @endisset
    </div>
</form>
