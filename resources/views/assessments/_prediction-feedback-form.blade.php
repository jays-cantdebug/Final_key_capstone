@php
    $severityOptions = ['Normal', 'Mild', 'Moderate', 'Severe', 'Extremely Severe'];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf

    <div class="space-y-3">
        <div>
            <x-input-label for="corrected_depression_level" :value="__('Depression')" />
            <x-select id="corrected_depression_level" name="corrected_depression_level" class="mt-1 block w-full text-sm">
                <option value="">Unchanged</option>
                @foreach ($severityOptions as $level)
                    <option value="{{ $level }}" @selected(old('corrected_depression_level', $feedback?->corrected_depression_level) === $level)>{{ $level }}</option>
                @endforeach
            </x-select>
        </div>
        <div>
            <x-input-label for="corrected_anxiety_level" :value="__('Anxiety')" />
            <x-select id="corrected_anxiety_level" name="corrected_anxiety_level" class="mt-1 block w-full text-sm">
                <option value="">Unchanged</option>
                @foreach ($severityOptions as $level)
                    <option value="{{ $level }}" @selected(old('corrected_anxiety_level', $feedback?->corrected_anxiety_level) === $level)>{{ $level }}</option>
                @endforeach
            </x-select>
        </div>
        <div>
            <x-input-label for="corrected_stress_level" :value="__('Stress')" />
            <x-select id="corrected_stress_level" name="corrected_stress_level" class="mt-1 block w-full text-sm">
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

    <div class="flex flex-wrap gap-3">
        <button type="submit" name="is_confirmed" value="1" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark">
            {{ $confirmLabel ?? 'Confirm' }}
        </button>
        <button type="submit" name="is_confirmed" value="0" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            {{ $correctLabel ?? 'Correct' }}
        </button>
        @isset($cancelHref)
            <a href="{{ $cancelHref }}" class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold text-slate-500 transition hover:bg-slate-50">
                {{ $cancelLabel ?? 'Cancel' }}
            </a>
        @endisset
    </div>
</form>
