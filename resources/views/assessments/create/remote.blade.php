@php
    $isRetake = $existingStudentId !== null;
    // The student fills in Step 1 on the device too.
    $collectsIdentity = $draft->collects_identity;
    $statusLabels = [
        'pending' => 'Waiting for the student device',
        'consent' => 'Student device connected — waiting for the student to acknowledge the privacy notice',
        'identity' => 'Waiting for the student to enter their details',
        'held' => 'Stopped: the name the student entered matches an existing active student (see below). The student device shows only a neutral “One moment, please” message, never the reason.',
        'answering' => 'Student is answering',
        'locked' => 'The student pressed Done — review the answers, then submit',
        'declined' => 'The student declined the privacy notice. Nothing was saved.',
        'expired' => 'This session has expired. The answers were discarded.',
        'gone' => 'This session is no longer available.',
        // Only after Back from Step 3 (remote-monitor.js): Submit already went through.
        'submitted' => 'Already submitted. Continue to the review (Step 3) to finish this assessment.',
    ];
    $state = $monitor['state'];
    $isActive = in_array($state, ['pending', 'consent', 'identity', 'held', 'answering', 'locked'], true);
    $identityValue = fn (string $field): string => (string) old($field, $identity[$field] ?? '');
    // Server-rendered starting state, so the page is right before Alpine runs.
    $cloakUnless = fn (bool $visible): string => $visible ? '' : 'x-cloak';
    $selectedClass = 'border-primary bg-tint text-primary dark:border-primary-soft dark:bg-primary-soft/15 dark:text-primary-soft';
    $unselectedClass = 'border-slate-200 text-slate-400 dark:border-slate-600 dark:text-slate-500';
@endphp

<x-app-layout>
    {{-- No page heading when the student fills in Step 1 too: the step indicator says it. --}}
    @unless ($collectsIdentity)
        <x-slot name="header">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $isRetake ? 'Retake: Questionnaire' : 'Step 2: Questionnaire' }} &mdash; {{ $student->full_name }}</h2>
        </x-slot>
    @endunless

    @unless ($isRetake)
        @include('assessments.create._steps', ['currentStep' => $collectsIdentity && $identity === null ? 1 : 2])
    @endunless

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->has('remote') || $errors->has('privacy_consent') || $errors->has('confirm_archived_match'))
        <x-alert type="warning" class="mb-6">{{ $errors->first('remote') ?: ($errors->first('privacy_consent') ?: $errors->first('confirm_archived_match')) }}</x-alert>
    @endif

    @if ($loopback)
        <x-alert type="warning" class="mb-6" data-loopback-warning>
            The student address below is a loopback address ({{ $studentEntryUrl }}). A separate student PC can’t reach it — it would point at the student PC itself. Set REMOTE_ASSESSMENT_URL to this server’s LAN address or domain.
        </x-alert>
    @endif

    @if ($duplicateWarning)
        <x-alert type="warning" class="mb-6" data-duplicate-warning>
            An active student with this name has been registered since Step 1. The final save will refuse to register a second record; you may want to cancel and use Take Again instead.
        </x-alert>
    @endif

    @include('assessments.create._analyzing-overlay', ['part' => 'style'])

    <div
        x-data="remoteMonitor(@js([
            'initial' => $monitor,
            'interval' => $pollIntervalMs,
            'statusUrl' => route('assessments.create.remote.status'),
            'statusLabels' => $statusLabels,
        ]))"
        x-bind:inert="submitting"
        class="space-y-6"
    >
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- How the student device gets in: shown until the code is used. --}}
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800 lg:col-span-2" x-show="state === 'pending'" {{ $cloakUnless($state === 'pending') }}>
                <p class="text-sm font-semibold text-body dark:text-slate-100">On the student PC</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" data-student-instructions>On the student PC, open this address in Chrome (or use the desktop shortcut), then type the code. Or copy the link below and open it on the student PC.</p>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Address</p>
                <p class="mt-1 break-all font-mono text-2xl font-semibold text-body dark:text-slate-100" data-student-address>{{ $studentEntryUrl }}</p>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Code</p>
                <p class="mt-1 font-mono text-5xl font-bold tracking-[0.2em] text-primary dark:text-primary-soft" data-short-code>{{ $shortCode }}</p>

                @if ($studentLink)
                    {{-- The link with the token: only in this button's data-link (staff only), never shown as text or as a link. --}}
                    <div class="mt-5" x-data="copyLink" data-copy-link-panel>
                        <div class="flex flex-wrap items-center gap-3">
                            <x-secondary-button type="button" x-ref="button" data-link="{{ $studentLink }}" @click="copy()">{{ __('Copy link') }}</x-secondary-button>
                            <span class="text-sm font-medium text-primary dark:text-primary-soft" x-show="copied" x-cloak role="status">Link copied.</span>
                        </div>
                        <div x-show="failed" x-cloak class="mt-2">
                            <p class="text-xs text-slate-600 dark:text-slate-400">Couldn’t copy automatically — select the link and press Ctrl+C.</p>
                            <input type="text" readonly x-ref="manual" :value="manualLink" @focus="$event.target.select()" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-xs shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" aria-label="Link to the student device">
                        </div>
                        <p class="mt-2 text-xs text-gold" data-copy-link-warning>
                            Don’t paste the link into a public chat. It works once and expires with this session. Typing the code avoids passing the link through a messaging service.
                        </p>
                    </div>
                @endif

                @if ($allowedIps !== [])
                    {{-- Staff only: never shown on the student device. --}}
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400" data-allowed-ips>
                        Only these student PCs can open the address: <span class="font-mono">{{ implode(', ', $allowedIps) }}</span>. Any other PC sees “This page is not available.”
                    </p>
                @endif
                @if ($invalidAllowedIps !== [])
                    <p class="mt-2 text-xs font-medium text-red-600 dark:text-red-400" data-invalid-allowed-ips>
                        Not valid IP addresses or ranges, so they match no PC: <span class="font-mono">{{ implode(', ', $invalidAllowedIps) }}</span>. Check REMOTE_ASSESSMENT_ALLOWED_IPS.
                    </p>
                @endif

                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400" data-browser-advice>
                    On a shared student PC, open the address in a guest or private browser window, so nothing the student types is kept in the browser. Outside a private LAN demo, serve the app over HTTPS: otherwise the student’s answers{{ $collectsIdentity ? ' and details' : '' }} cross the network unencrypted.
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800" :class="state === 'pending' ? '' : 'lg:col-span-3'">
                <p class="text-sm font-semibold text-body dark:text-slate-100">Student device</p>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-300" x-text="deviceStatus" data-device-status>{{ $statusLabels[$state] }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="lastSeenText"></p>
                <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400" x-show="refused_device_attempts > 0" {{ $cloakUnless($monitor['refused_device_attempts'] > 0) }} data-refused>
                    Another device tried to open this code (<span x-text="refused_device_attempts">{{ $monitor['refused_device_attempts'] }}</span>×).
                </p>
                <p class="mt-2 text-sm font-medium text-gold" x-show="offline" x-cloak>Connection to the server lost — retrying…</p>

                <p class="mt-4 text-sm font-semibold text-body dark:text-slate-100">Privacy notice</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300" x-text="consentText" data-consent-status></p>

                <div x-show="active" {{ $cloakUnless($isActive) }}>
                    <p class="mt-4 text-sm font-semibold text-body dark:text-slate-100">Time left</p>
                    <p class="mt-1 font-mono text-lg text-slate-700 dark:text-slate-300" x-text="countdown" data-countdown>{{ intdiv($monitor['seconds_left'], 60) }}:{{ str_pad((string) ($monitor['seconds_left'] % 60), 2, '0', STR_PAD_LEFT) }}</p>
                </div>

                <p class="mt-4 text-sm font-semibold text-body dark:text-slate-100">Progress</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300"><span x-text="answered">{{ $monitor['answered'] }}</span> of <span x-text="total">{{ $monitor['total'] }}</span> answered</p>
            </div>
        </div>

        <div class="rounded-lg border border-gold bg-white px-5 py-4 text-sm text-body dark:bg-slate-800 dark:text-slate-100" x-show="version_changed && active" {{ $cloakUnless($monitor['version_changed'] && $isActive) }} data-version-changed>
            The active questionnaire changed while the student was answering. Restart on the new version to have it answered again; the current answers will be cleared.
        </div>

        @if ($collectsIdentity)
            @include('assessments.create._remote-identity')
        @endif

        {{-- Actions --}}
        <div class="flex flex-wrap items-center gap-3">
            {{-- Step 2's "Analyzing responses" overlay while Step 3 classifies; Submit only (resources/js/remote-monitor.js). --}}
            <form
                method="POST"
                action="{{ route('assessments.create.remote.submit') }}"
                class="flex flex-wrap items-center gap-3"
                data-remote-submit
                x-on:submit="submitForReview($event)"
                x-on:pageshow.window="restoreAfterBack($event)"
                x-effect="document.body.classList.toggle('overflow-y-hidden', submitting)"
            >
                @csrf

                @include('assessments.create._analyzing-overlay', ['part' => 'overlay'])

                @if ($isRetake || $collectsIdentity)
                    {{-- The staff attestation (Step 1's checkbox), next to Submit. --}}
                    <label class="flex items-start gap-2">
                        <x-checkbox name="privacy_consent" value="1" class="mt-1" :invalid="$errors->has('privacy_consent')" />
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                    </label>
                @endif
                @if (($duplicate['kind'] ?? null) === 'archived')
                    <label class="flex items-start gap-2" data-confirm-archived>
                        <x-checkbox name="confirm_archived_match" value="1" class="mt-1" :invalid="$errors->has('confirm_archived_match')" />
                        <span class="text-sm text-slate-700 dark:text-slate-300">I understand. Create a new student record.</span>
                    </label>
                @endif
                <x-primary-button x-bind:disabled="state !== 'locked' || version_changed || submitting" :disabled="$state !== 'locked' || $monitor['version_changed']">{{ __('Submit and continue to review') }}</x-primary-button>
            </form>

            <form method="POST" action="{{ route('assessments.create.remote.return') }}" x-show="state === 'locked'" {{ $cloakUnless($state === 'locked') }}>
                @csrf
                <x-secondary-button type="submit">{{ __('Return to student') }}</x-secondary-button>
            </form>

            {{-- Add time: not after Done (locked), never past the maximum lifetime ($canExtend, re-rendered after each press). stop(): no poll in between, so the confirmation flash survives the redirect. --}}
            @if ($canExtend)
                <form method="POST" action="{{ route('assessments.create.remote.extend') }}" x-show="active && state !== 'locked'" x-on:submit="stop()" {{ $cloakUnless($isActive && $state !== 'locked') }} data-extend>
                    @csrf
                    <x-secondary-button type="submit">{{ __('Add :minutes minutes', ['minutes' => $extendMinutes]) }}</x-secondary-button>
                </form>
            @endif

            <form method="POST" action="{{ route('assessments.create.remote.restart') }}" x-show="version_changed && active" {{ $cloakUnless($monitor['version_changed'] && $isActive) }}>
                @csrf
                <x-secondary-button type="submit">{{ __('Restart on the new version') }}</x-secondary-button>
            </form>

            <form method="POST" action="{{ route('assessments.create.remote.new-code') }}" x-show="active" {{ $cloakUnless($isActive) }}>
                @csrf
                <x-secondary-button type="submit">{{ __('New code') }}</x-secondary-button>
            </form>

            {{-- After Back from Step 3 once Submit went through: only the way forward (sending again or Cancel would drop the submitted answers). --}}
            <x-primary-button :href="route('assessments.create.result')" x-show="state === 'submitted'" x-cloak data-continue-review>{{ __('Continue to the review') }}</x-primary-button>

            <form method="POST" action="{{ route($collectsIdentity ? 'assessments.create.remote.store-student' : 'assessments.create.remote.store') }}" x-show="!active && state !== 'submitted'" {{ $cloakUnless(! $isActive) }}>
                @csrf
                <x-secondary-button type="submit">{{ __('Send to student device again') }}</x-secondary-button>
            </form>

            <form method="POST" action="{{ route('assessments.create.remote.cancel') }}" x-show="state !== 'submitted'">
                @csrf
                @method('DELETE')
                <x-secondary-button type="submit">{{ __('Cancel') }}</x-secondary-button>
            </form>
        </div>

        {{-- The student's answers, read-only, as they arrive. --}}
        <div>
            @include('assessments.create._response-scale')

            <div class="space-y-3">
                @foreach ($questions as $question)
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800" data-question-row="{{ $question->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <p class="text-sm font-medium text-body dark:text-slate-100">{{ $question->item_number }}. {{ $question->question_text }}</p>
                            <span class="shrink-0 rounded-md bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $question->subscale }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-4 gap-2">
                            @foreach ([0, 1, 2, 3] as $value)
                                @php($selected = ($draft->responses[$question->id] ?? null) === $value)
                                {{-- Object syntax, so Alpine also removes the server-rendered classes when the answer changes. --}}
                                <div
                                    class="rounded-md border-2 px-3 py-1.5 text-center text-sm font-semibold {{ $selected ? $selectedClass : $unselectedClass }}"
                                    :class="{ @js($selectedClass): isSelected({{ $question->id }}, {{ $value }}), @js($unselectedClass): ! isSelected({{ $question->id }}, {{ $value }}) }"
                                    data-answer-box="{{ $value }}"
                                    @if ($selected) data-selected @endif
                                >{{ $value }}</div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
