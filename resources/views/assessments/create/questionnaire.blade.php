@php
    $isRetake = $existingStudentId !== null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">{{ $isRetake ? 'Take Again' : 'New Assessment' }} &mdash; {{ $student->full_name }}</p>
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">{{ $isRetake ? 'Retake: Questionnaire' : 'Step 2: Questionnaire' }}</h2>
        </div>
    </x-slot>

    @unless ($isRetake)
        @include('assessments.create._steps', ['currentStep' => 2])
    @endunless

    @if ($isRetake)
        <x-alert type="success" class="mb-6">
            This will add a new assessment for <span class="font-semibold">{{ $student->full_name }}</span> — their prior assessment history is kept and stays visible in Assessment History.
        </x-alert>
    @endif

    @include('assessments.create._response-scale')

    <style>
        /* "Analyzing responses" loading overlay (Step 2 -> Step 3): a
           breathing halo, a drawing pulse/EKG line, a fading ellipsis, and
           an indeterminate shimmer bar. Plain CSS keyframes rather than
           Tailwind utilities, since none of these are in Tailwind's
           default animation set; scoped to this page via the loading-
           prefix, matching the <style>-block precedent already used on
           the Dashboard for page-specific styling Tailwind can't express.
        */
        .loading-badge-wrap {
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .loading-halo {
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(31, 107, 58, 0.28) 0%, rgba(31, 107, 58, 0) 70%);
            animation: loading-breathe 2.2s ease-out infinite;
        }
        .loading-halo--delay {
            animation-delay: 1.1s;
        }
        .loading-badge {
            position: relative;
            width: 36px;
            height: 36px;
            border-radius: 9999px;
            background: linear-gradient(150deg, #EAF3EC 0%, #D9EBDE 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 0 1px rgba(31, 107, 58, 0.12);
        }
        .loading-pulse-line {
            stroke: #1F6B3A;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            stroke-dasharray: 40;
            stroke-dashoffset: 40;
            animation: loading-draw-pulse 1.8s ease-in-out infinite;
        }
        .loading-dot {
            display: inline-block;
            opacity: 0.25;
            animation: loading-dot-fade 1.4s ease-in-out infinite;
        }
        .loading-dot:nth-child(2) { animation-delay: 0.2s; }
        .loading-dot:nth-child(3) { animation-delay: 0.4s; }
        .loading-shimmer-track {
            position: relative;
            height: 5px;
            border-radius: 9999px;
            background: #EAF3EC;
            overflow: hidden;
        }
        .loading-shimmer-fill {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 40%;
            border-radius: 9999px;
            background: linear-gradient(90deg, #EAF3EC, #1F6B3A, #EAF3EC);
            animation: loading-sweep 1.6s ease-in-out infinite;
        }

        /* Dark-mode equivalents (softened primary-soft accent on slate panels). */
        .dark .loading-halo {
            background: radial-gradient(circle, rgba(78, 155, 113, 0.28) 0%, rgba(78, 155, 113, 0) 70%);
        }
        .dark .loading-badge {
            background: linear-gradient(150deg, #1E293B 0%, #16302A 100%);
            box-shadow: inset 0 0 0 1px rgba(78, 155, 113, 0.25);
        }
        .dark .loading-pulse-line {
            stroke: #4E9B71;
        }
        .dark .loading-shimmer-track {
            background: #334155;
        }
        .dark .loading-shimmer-fill {
            background: linear-gradient(90deg, #334155, #4E9B71, #334155);
        }

        @keyframes loading-breathe {
            0% { transform: scale(0.7); opacity: 0.9; }
            70% { transform: scale(1.55); opacity: 0; }
            100% { transform: scale(1.55); opacity: 0; }
        }
        @keyframes loading-draw-pulse {
            0% { stroke-dashoffset: 40; }
            45% { stroke-dashoffset: 0; }
            80% { stroke-dashoffset: 0; opacity: 1; }
            100% { stroke-dashoffset: -40; opacity: 0.4; }
        }
        @keyframes loading-dot-fade {
            0%, 60%, 100% { opacity: 0.25; }
            30% { opacity: 1; }
        }
        @keyframes loading-sweep {
            0% { left: -40%; }
            100% { left: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .loading-halo,
            .loading-pulse-line,
            .loading-dot,
            .loading-shimmer-fill {
                animation: none !important;
            }
            .loading-pulse-line { stroke-dashoffset: 0; opacity: 1; }
            .loading-shimmer-fill { left: 30%; }
        }
    </style>

    <form
        method="POST"
        action="{{ route('assessments.create.questionnaire.store') }}"
        novalidate
        x-data="{ submitting: false }"
        x-on:submit="submitting = true"
        x-effect="document.body.classList.toggle('overflow-y-hidden', submitting)"
        x-init="$nextTick(() => { const first = $el.querySelector('[data-field-invalid]'); if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); } })"
    >
        @csrf

        <div
            x-show="submitting"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/35 px-4 py-8 backdrop-blur-sm"
            style="display: none;"
        >
            <div class="w-full max-w-sm rounded-[20px] bg-white dark:bg-slate-800 px-10 pb-4 pt-5 text-center shadow-[0_24px_48px_-16px_rgba(31,107,58,0.28),0_8px_20px_-8px_rgba(44,44,42,0.18)]">
                <div class="loading-badge-wrap mx-auto mb-2">
                    <span class="loading-halo"></span>
                    <span class="loading-halo loading-halo--delay"></span>
                    <div class="loading-badge">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
                            <polyline class="loading-pulse-line" points="1,13 7,13 9,7 13,19 15,13 23,13" />
                        </svg>
                    </div>
                </div>
                <p class="text-lg font-semibold tracking-tight text-body dark:text-slate-100">
                    Analyzing responses<span class="loading-dot">.</span><span class="loading-dot">.</span><span class="loading-dot">.</span>
                </p>
                <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">This may take a few seconds while the AI classifies the results.</p>
                <div class="loading-shimmer-track mt-2">
                    <div class="loading-shimmer-fill"></div>
                </div>
            </div>
        </div>

        <div x-bind:inert="submitting">
            <div class="space-y-4">
                @foreach ($version->questions as $question)
                    <x-dass-response-options
                        :question="$question"
                        :selected="old('responses.' . $question->id, $existingResponses[$question->id] ?? null)"
                        :invalid="$errors->has('responses.' . $question->id)"
                    />
                @endforeach
            </div>

            @if ($isRetake)
                <div class="relative mt-4" x-data="{ show: {{ $errors->has('privacy_consent') ? 'true' : 'false' }} }">
                    <label class="flex items-start gap-2">
                        <x-checkbox name="privacy_consent" value="1" class="mt-1" :invalid="$errors->has('privacy_consent')" @change="show = false" />
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('The student has acknowledged the data privacy consent notice for this assessment.') }}</span>
                    </label>
                    <x-field-error-tooltip :message="$errors->first('privacy_consent')" />
                </div>
            @endif

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button x-bind:disabled="submitting">
                    <span x-show="!submitting">{{ __('Next: Review & Submit') }}</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2" style="display: none;">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ __('Analyzing…') }}
                    </span>
                </x-primary-button>
                <x-secondary-button :href="$isRetake ? route('students.show', $existingStudentId) : route('assessments.create')">
                    {{ __('Back') }}
                </x-secondary-button>
            </div>
        </div>
    </form>
</x-app-layout>
