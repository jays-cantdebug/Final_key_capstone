@php
    /** @var \App\Models\CounselingSession|null $session */
    $session = $session ?? null;
    $followUpRequiredChecked = (bool) old('follow_up_required', $session?->follow_up_required ?? false);
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="assessment_id" :value="__('Related Assessment (optional)')" />
        <x-select id="assessment_id" name="assessment_id" class="mt-1 block w-full">
            <option value="">No related assessment</option>
            @foreach ($assessments as $assessment)
                <option value="{{ $assessment->id }}" @selected((string) old('assessment_id', $session?->assessment_id) === (string) $assessment->id)>
                    {{ $assessment->submitted_at->format('M d, Y g:i A') }} &mdash; {{ $assessment->result?->highestSeverityLevel() ?? 'N/A' }}
                </option>
            @endforeach
        </x-select>
        <x-input-error class="mt-2" :messages="$errors->get('assessment_id')" />
    </div>

    <div>
        <x-input-label for="session_date" :value="__('Session Date & Time')" />
        <div class="mt-1 flex gap-2">
            <x-text-input id="session_date" name="session_date" type="date" class="block w-1/2" :value="old('session_date', $session?->session_datetime?->format('Y-m-d'))" required />
            <x-text-input id="session_time" name="session_time" type="time" class="block w-1/2" :value="old('session_time', $session?->session_datetime?->format('H:i'))" required />
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('session_date')" />
        <x-input-error class="mt-1" :messages="$errors->get('session_time')" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="session_notes" :value="__('Session Notes')" />
        <x-textarea id="session_notes" name="session_notes" rows="5" class="mt-1 block w-full" required>{{ old('session_notes', $session?->session_notes) }}</x-textarea>
        <x-input-error class="mt-2" :messages="$errors->get('session_notes')" />
    </div>

    <div>
        <x-input-label for="session_status" :value="__('Session Status')" />
        <x-select id="session_status" name="session_status" class="mt-1 block w-full">
            @foreach (['Scheduled', 'Completed', 'Cancelled', 'No-Show'] as $status)
                <option value="{{ $status }}" @selected(old('session_status', $session?->session_status ?? 'Scheduled') === $status)>{{ $status }}</option>
            @endforeach
        </x-select>
        <x-input-error class="mt-2" :messages="$errors->get('session_status')" />
    </div>

    <div>
        <x-input-label for="confidentiality_level" :value="__('Confidentiality Level')" />
        <x-select id="confidentiality_level" name="confidentiality_level" class="mt-1 block w-full">
            @foreach (['Standard', 'Restricted'] as $level)
                <option value="{{ $level }}" @selected(old('confidentiality_level', $session?->confidentiality_level ?? 'Standard') === $level)>{{ $level }}</option>
            @endforeach
        </x-select>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Restricted notes are visible only to you.</p>
        <x-input-error class="mt-2" :messages="$errors->get('confidentiality_level')" />
    </div>

    <div class="sm:col-span-2" x-data="{ followUpRequired: {{ $followUpRequiredChecked ? 'true' : 'false' }} }">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="follow_up_required" value="0" />
            <x-checkbox name="follow_up_required" value="1" x-model="followUpRequired" />
            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('Follow-up required') }}</span>
        </label>
        <x-input-error class="mt-2" :messages="$errors->get('follow_up_required')" />

        <div class="mt-3" x-show="followUpRequired">
            <x-input-label for="follow_up_date" :value="__('Follow-Up Date')" />
            <x-text-input id="follow_up_date" name="follow_up_date" type="date" class="mt-1 block w-full sm:w-1/2" :value="old('follow_up_date', $session?->follow_up_date?->format('Y-m-d'))" />
            <x-input-error class="mt-2" :messages="$errors->get('follow_up_date')" />
        </div>
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <x-primary-button>
        {{ $buttonLabel ?? __('Save Session') }}
    </x-primary-button>

    <x-secondary-button :href="route('counseling-sessions.index')">
        {{ __('Cancel') }}
    </x-secondary-button>
</div>
