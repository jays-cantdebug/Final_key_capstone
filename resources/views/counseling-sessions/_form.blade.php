@php
    /** @var \App\Models\CounselingSession|null $session */
    $session = $session ?? null;
    $followUpRequiredChecked = (bool) old('follow_up_required', $session?->follow_up_required ?? false);

    // Server-side errors, one message per field, fed to the sessionForm
    // Alpine component (resources/js/session-form.js), which renders them as
    // per-field tooltips — the single place each error appears.
    $fieldErrors = collect([
        'assessment_id', 'session_date', 'session_time', 'session_notes',
        'session_status', 'confidentiality_level', 'follow_up_required', 'follow_up_date',
    ])->mapWithKeys(fn (string $field) => [$field => $errors->first($field)])->filter();

    // `session_datetime` is derived from Date + Time in the Form Request and
    // has no input of its own; surface its error on the Date field, unless
    // Date/Time already have their own (more precise) error.
    if ($errors->has('session_datetime') && ! $fieldErrors->has('session_date') && ! $fieldErrors->has('session_time')) {
        $fieldErrors->put('session_date', $errors->first('session_datetime'));
    }

    // Messages for the client-side pre-submit check; identical to the
    // server's, so an error reads the same whichever side catches it.
    $clientMessages = [
        'session_date' => __('validation.required', ['attribute' => 'session date']),
        'session_time' => __('validation.required', ['attribute' => 'session time']),
        'session_notes' => __('validation.required', ['attribute' => 'session notes']),
        'follow_up_date' => \App\Http\Requests\CounselingSessionFormRequest::FOLLOW_UP_DATE_REQUIRED_MESSAGE,
    ];

    $invalidClasses = "'!border-red-500 focus:!border-red-500 focus:!ring-red-500 dark:!border-red-400 dark:focus:!border-red-400 dark:focus:!ring-red-400'";
@endphp

<div
    class="grid gap-6 sm:grid-cols-2"
    x-data="sessionForm(@js((object) $fieldErrors->all()), @js($followUpRequiredChecked), @js($clientMessages))"
>
    <div class="relative">
        <x-input-label for="assessment_id" :value="__('Related Assessment (optional)')" />
        <x-select id="assessment_id" name="assessment_id" class="mt-1 block w-full" x-bind:class="hasError('assessment_id') && {{ $invalidClasses }}" x-on:change="hideTooltip('assessment_id')">
            <option value="">No related assessment</option>
            @foreach ($assessments as $assessment)
                <option value="{{ $assessment->id }}" @selected((string) old('assessment_id', $session?->assessment_id) === (string) $assessment->id)>
                    {{ $assessment->submitted_at->format('M d, Y g:i A') }} &mdash; {{ $assessment->effectiveHighestSeverityLevel() ?? 'N/A' }}
                </option>
            @endforeach
        </x-select>
        <x-field-error-tooltip field="assessment_id" :message="$fieldErrors->get('assessment_id')" />
    </div>

    <div>
        <x-input-label for="session_date" :value="__('Session Date & Time')" />
        <div class="mt-1 flex gap-2">
            <div class="relative w-1/2">
                <x-text-input id="session_date" name="session_date" type="date" class="block w-full" :value="old('session_date', $session?->session_datetime?->format('Y-m-d'))" required x-bind:class="hasError('session_date') && {{ $invalidClasses }}" x-on:input="hideTooltip('session_date')" />
                <x-field-error-tooltip field="session_date" :message="$fieldErrors->get('session_date')" />
            </div>
            <div class="relative w-1/2">
                <x-text-input id="session_time" name="session_time" type="time" class="block w-full" :value="old('session_time', $session?->session_datetime?->format('H:i'))" required x-bind:class="hasError('session_time') && {{ $invalidClasses }}" x-on:input="hideTooltip('session_time')" />
                <x-field-error-tooltip field="session_time" :message="$fieldErrors->get('session_time')" align="right" />
            </div>
        </div>
    </div>

    <div class="relative sm:col-span-2">
        <x-input-label for="session_notes" :value="__('Session Notes')" />
        <x-textarea id="session_notes" name="session_notes" rows="5" class="mt-1 block w-full" required x-bind:class="hasError('session_notes') && {{ $invalidClasses }}" x-on:input="hideTooltip('session_notes')">{{ old('session_notes', $session?->session_notes) }}</x-textarea>
        <x-field-error-tooltip field="session_notes" :message="$fieldErrors->get('session_notes')" />
    </div>

    <div class="relative">
        <x-input-label for="session_status" :value="__('Session Status')" />
        <x-select id="session_status" name="session_status" class="mt-1 block w-full" x-bind:class="hasError('session_status') && {{ $invalidClasses }}" x-on:change="hideTooltip('session_status')">
            @foreach (['Scheduled', 'Completed', 'Cancelled', 'No-Show'] as $status)
                <option value="{{ $status }}" @selected(old('session_status', $session?->session_status ?? 'Scheduled') === $status)>{{ $status }}</option>
            @endforeach
        </x-select>
        <x-field-error-tooltip field="session_status" :message="$fieldErrors->get('session_status')" />
    </div>

    <div class="relative">
        <x-input-label for="confidentiality_level" :value="__('Confidentiality Level')" />
        <x-select id="confidentiality_level" name="confidentiality_level" class="mt-1 block w-full" x-bind:class="hasError('confidentiality_level') && {{ $invalidClasses }}" x-on:change="hideTooltip('confidentiality_level')">
            @foreach (['Standard', 'Restricted'] as $level)
                <option value="{{ $level }}" @selected(old('confidentiality_level', $session?->confidentiality_level ?? 'Standard') === $level)>{{ $level }}</option>
            @endforeach
        </x-select>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Restricted notes are visible only to you.</p>
        <x-field-error-tooltip field="confidentiality_level" :message="$fieldErrors->get('confidentiality_level')" />
    </div>

    <div class="sm:col-span-2">
        <div class="relative inline-block">
            <label class="inline-flex items-center gap-2">
                <input type="hidden" name="follow_up_required" value="0" />
                <x-checkbox name="follow_up_required" value="1" x-model="followUpRequired" x-on:change="hideTooltip('follow_up_required'); toggleFollowUp($event.target.checked)" />
                <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('Follow-up required') }}</span>
            </label>
            <x-field-error-tooltip field="follow_up_required" :message="$fieldErrors->get('follow_up_required')" />
        </div>

        {{-- Starting state is rendered on the server (not x-cloak), so the
             field is still correct if the page's JS never loads. --}}
        <div class="relative mt-3 sm:w-1/2" x-show="followUpRequired" @unless ($followUpRequiredChecked) style="display: none;" @endunless>
            <x-input-label for="follow_up_date" :value="__('Follow-Up Date')" />
            <x-text-input id="follow_up_date" name="follow_up_date" type="date" class="mt-1 block w-full" :value="old('follow_up_date', $session?->follow_up_date?->format('Y-m-d'))" x-bind:required="followUpRequired" x-bind:class="hasError('follow_up_date') && {{ $invalidClasses }}" x-on:input="hideTooltip('follow_up_date')" />
            <x-field-error-tooltip field="follow_up_date" :message="$fieldErrors->get('follow_up_date')" />
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
