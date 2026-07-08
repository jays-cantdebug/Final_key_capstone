<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Settings</p>
            <h2 class="text-2xl font-semibold text-body">Classification Thresholds</h2>
        </div>
    </x-slot>

    @include('settings._tabs', ['active' => 'thresholds'])

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @if ($isOverridden)
        <x-alert type="warning" class="mb-6 font-semibold">
            Non-official thresholds are in effect. These values differ from the official, published DASS-21 cutoffs.
        </x-alert>
    @else
        <x-alert type="info" class="mb-6">
            Official DASS-21 Values — read-only by default.
        </x-alert>
    @endif

    <div x-data="{ overrideMode: false }">
        <form method="POST" action="{{ route('settings.classification-thresholds.update') }}">
            @csrf
            @method('PUT')

            <x-table>
                <x-slot:head>
                    <x-table.th>Subscale</x-table.th>
                    <x-table.th>Severity Level</x-table.th>
                    <x-table.th>Min Score</x-table.th>
                    <x-table.th>Max Score</x-table.th>
                </x-slot:head>

                @foreach ($thresholds as $index => $threshold)
                    <tr>
                        <x-table.td class="font-medium text-body">{{ $threshold->subscale }}</x-table.td>
                        <x-table.td><x-severity-badge :level="$threshold->severity_level" /></x-table.td>
                        <x-table.td>
                            <input type="hidden" name="thresholds[{{ $index }}][id]" value="{{ $threshold->id }}" />
                            <input
                                type="number"
                                name="thresholds[{{ $index }}][min_score]"
                                value="{{ old("thresholds.$index.min_score", $threshold->min_score) }}"
                                min="0"
                                :disabled="!overrideMode"
                                class="w-24 rounded-md border-gray-300 text-sm shadow-sm disabled:border-transparent disabled:bg-transparent disabled:text-slate-700 disabled:shadow-none focus:border-primary focus:ring-primary"
                            />
                        </x-table.td>
                        <x-table.td>
                            <input
                                type="number"
                                name="thresholds[{{ $index }}][max_score]"
                                value="{{ old("thresholds.$index.max_score", $threshold->max_score) }}"
                                min="0"
                                :disabled="!overrideMode"
                                class="w-24 rounded-md border-gray-300 text-sm shadow-sm disabled:border-transparent disabled:bg-transparent disabled:text-slate-700 disabled:shadow-none focus:border-primary focus:ring-primary"
                            />
                        </x-table.td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            x-show="!overrideMode"
                            @click="$dispatch('open-modal', 'enable-override-mode')"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            Enable Override Mode
                        </button>

                        <x-primary-button x-show="overrideMode">
                            Save Changes
                        </x-primary-button>

                        <button
                            type="button"
                            x-show="overrideMode"
                            @click="window.location.reload()"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                    </div>
                </x-slot:footer>
            </x-table>
        </form>

        <x-modal name="enable-override-mode" :show="false" maxWidth="lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-body">Enable Override Mode?</h3>
                <p class="mt-3 text-sm text-slate-600">
                    Overriding official DASS-21 cutoffs affects clinical validity and should only be done for a documented institutional reason.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="$dispatch('close-modal', 'enable-override-mode')"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="overrideMode = true; $dispatch('close-modal', 'enable-override-mode')"
                        class="inline-flex items-center justify-center rounded-xl bg-[#791F1F] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#601818]"
                    >
                        Yes, Enable Override Mode
                    </button>
                </div>
            </div>
        </x-modal>
    </div>

    <x-card class="mt-6">
        <h3 class="text-sm font-semibold text-body">Restore Official Values</h3>
        <p class="mt-1 text-xs text-slate-500">Resets every threshold above back to the official, published DASS-21 cutoffs in one action.</p>
        <form method="POST" action="{{ route('settings.classification-thresholds.restore') }}" class="mt-4" onsubmit="return confirm('Restore all classification thresholds to their official DASS-21 values? This cannot be undone.');">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                Restore Official Values
            </button>
        </form>
    </x-card>
</x-app-layout>
