@php
    $isRetake = $existingStudentId !== null;
    // The student fills in Step 1 on the device too.
    $collectsIdentity = $draft->collects_identity;
    $statusLabels = [
        'pending' => 'Waiting for the student device',
        'consent' => 'Student device connected — waiting for the student to acknowledge the privacy notice',
        'identity' => 'Waiting for the student to enter their details',
        'held' => 'Stopped: the name the student entered matches an existing active student (see below). The student device shows only the generic “not available” message.',
        'answering' => 'Student is answering',
        'locked' => 'The student pressed Done — review the answers, then submit',
        'declined' => 'The student declined the privacy notice. Nothing was saved.',
        'expired' => 'This session has expired. The answers were discarded.',
        'gone' => 'This session is no longer available.',
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
    <x-slot name="header">
        @if ($collectsIdentity)
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Steps 1 and 2 on the student device &mdash; {{ $student?->full_name ?? 'waiting for the student’s details' }}</h2>
        @else
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $isRetake ? 'Retake: Questionnaire' : 'Step 2: Questionnaire' }} &mdash; {{ $student->full_name }}</h2>
        @endif
    </x-slot>

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

    <div
        x-data="remoteMonitor(@js([
            'initial' => $monitor,
            'interval' => $pollIntervalMs,
            'statusUrl' => route('assessments.create.remote.status'),
            'statusLabels' => $statusLabels,
        ]))"
        class="space-y-6"
    >
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- How the student device gets in: shown until the code is used. --}}
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800 lg:col-span-2" x-show="state === 'pending'" {{ $cloakUnless($state === 'pending') }}>
                <p class="text-sm font-semibold text-body dark:text-slate-100">On the student device</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                    <li>Open <span class="font-mono font-semibold text-body dark:text-slate-100" data-student-address>{{ $studentEntryUrl }}</span></li>
                    <li>Type this code:</li>
                </ol>
                <p class="mt-3 font-mono text-4xl font-bold tracking-[0.2em] text-primary dark:text-primary-soft" data-short-code>{{ $shortCode }}</p>
                @if ($qrSvg)
                    <div class="mt-4 flex items-center gap-4">
                        <div class="w-40 shrink-0 rounded-md bg-white p-2" data-qr>{!! $qrSvg !!}</div>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Or scan this QR code on a tablet or phone, then press Begin.</p>
                    </div>
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
            <form method="POST" action="{{ route('assessments.create.remote.submit') }}" class="flex flex-wrap items-center gap-3">
                @csrf
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
                <x-primary-button x-bind:disabled="state !== 'locked' || version_changed" :disabled="$state !== 'locked' || $monitor['version_changed']">{{ __('Submit and continue to review') }}</x-primary-button>
            </form>

            <form method="POST" action="{{ route('assessments.create.remote.return') }}" x-show="state === 'locked'" {{ $cloakUnless($state === 'locked') }}>
                @csrf
                <x-secondary-button type="submit">{{ __('Return to student') }}</x-secondary-button>
            </form>

            <form method="POST" action="{{ route('assessments.create.remote.restart') }}" x-show="version_changed && active" {{ $cloakUnless($monitor['version_changed'] && $isActive) }}>
                @csrf
                <x-secondary-button type="submit">{{ __('Restart on the new version') }}</x-secondary-button>
            </form>

            <form method="POST" action="{{ route('assessments.create.remote.new-code') }}" x-show="active" {{ $cloakUnless($isActive) }}>
                @csrf
                <x-secondary-button type="submit">{{ __('New code') }}</x-secondary-button>
            </form>

            <form method="POST" action="{{ route($collectsIdentity ? 'assessments.create.remote.store-student' : 'assessments.create.remote.store') }}" x-show="!active" {{ $cloakUnless(! $isActive) }}>
                @csrf
                <x-secondary-button type="submit">{{ __('Send to student device again') }}</x-secondary-button>
            </form>

            <form method="POST" action="{{ route('assessments.create.remote.cancel') }}">
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
