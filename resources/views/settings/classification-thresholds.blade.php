@php
    $severityBadgeClasses = [
        'Normal' => 'bg-[#EAF3DE] text-[#27500A]',
        'Mild' => 'bg-[#E6F1FB] text-[#0C447C]',
        'Moderate' => 'bg-[#FAEEDA] text-[#633806]',
        'Severe' => 'bg-[#FAECE7] text-[#712B13]',
        'Extremely Severe' => 'bg-[#FCEBEB] text-[#791F1F]',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Settings</p>
            <h2 class="text-2xl font-semibold text-slate-900">Classification Thresholds</h2>
        </div>
    </x-slot>

    @include('settings._tabs', ['active' => 'thresholds'])

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($isOverridden)
        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
            ⚠ Non-official thresholds are in effect. These values differ from the official, published DASS-21 cutoffs.
        </div>
    @else
        <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600">
            Official DASS-21 Values — read-only by default.
        </div>
    @endif

    <div x-data="{ overrideMode: false }">
        <form method="POST" action="{{ route('settings.classification-thresholds.update') }}">
            @csrf
            @method('PUT')

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Subscale</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Severity Level</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Min Score</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Max Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($thresholds as $index => $threshold)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $threshold->subscale }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $severityBadgeClasses[$threshold->severity_level] ?? 'bg-slate-200 text-slate-600' }}">
                                            {{ $threshold->severity_level }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        <input type="hidden" name="thresholds[{{ $index }}][id]" value="{{ $threshold->id }}" />
                                        <input
                                            type="number"
                                            name="thresholds[{{ $index }}][min_score]"
                                            value="{{ old("thresholds.$index.min_score", $threshold->min_score) }}"
                                            min="0"
                                            :disabled="!overrideMode"
                                            class="w-24 rounded-md border-gray-300 text-sm shadow-sm disabled:border-transparent disabled:bg-transparent disabled:text-slate-700 disabled:shadow-none focus:border-[#1F6B3A] focus:ring-[#1F6B3A]"
                                        />
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        <input
                                            type="number"
                                            name="thresholds[{{ $index }}][max_score]"
                                            value="{{ old("thresholds.$index.max_score", $threshold->max_score) }}"
                                            min="0"
                                            :disabled="!overrideMode"
                                            class="w-24 rounded-md border-gray-300 text-sm shadow-sm disabled:border-transparent disabled:bg-transparent disabled:text-slate-700 disabled:shadow-none focus:border-[#1F6B3A] focus:ring-[#1F6B3A]"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 p-6">
                    <button
                        type="button"
                        x-show="!overrideMode"
                        @click="$dispatch('open-modal', 'enable-override-mode')"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Enable Override Mode
                    </button>

                    <button
                        type="submit"
                        x-show="overrideMode"
                        class="inline-flex items-center justify-center rounded-2xl bg-[#1F6B3A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#185828]"
                    >
                        Save Changes
                    </button>

                    <button
                        type="button"
                        x-show="overrideMode"
                        @click="window.location.reload()"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </form>

        <x-modal name="enable-override-mode" :show="false" maxWidth="lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Enable Override Mode?</h3>
                <p class="mt-3 text-sm text-slate-600">
                    Overriding official DASS-21 cutoffs affects clinical validity and should only be done for a documented institutional reason.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="$dispatch('close-modal', 'enable-override-mode')"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="overrideMode = true; $dispatch('close-modal', 'enable-override-mode')"
                        class="inline-flex items-center justify-center rounded-2xl bg-[#791F1F] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#601818]"
                    >
                        Yes, Enable Override Mode
                    </button>
                </div>
            </div>
        </x-modal>
    </div>

    <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900">Restore Official Values</h3>
        <p class="mt-1 text-xs text-slate-500">Resets every threshold above back to the official, published DASS-21 cutoffs in one action.</p>
        <form method="POST" action="{{ route('settings.classification-thresholds.restore') }}" class="mt-4" onsubmit="return confirm('Restore all classification thresholds to their official DASS-21 values? This cannot be undone.');">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Restore Official Values
            </button>
        </form>
    </div>
</x-app-layout>
