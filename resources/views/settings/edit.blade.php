<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Settings</p>
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">System Settings</h2>
        </div>
    </x-slot>

    @include('settings._tabs', ['active' => 'general'])

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    <x-card class="mb-6">
        <h3 class="text-lg font-semibold text-body dark:text-slate-100">Active Questionnaire</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">The active questionnaire version is managed in Questionnaire Management, not here, to avoid two conflicting sources of truth.</p>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-lg bg-slate-50 dark:bg-slate-800 p-4">
            @if ($activeQuestionnaireVersion)
                <div>
                    <p class="text-sm font-medium text-body dark:text-slate-100">{{ $activeQuestionnaireVersion->questionnaire->title }} v{{ $activeQuestionnaireVersion->version_number }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Effective {{ $activeQuestionnaireVersion->effective_date->format('M d, Y') }}</p>
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400">No questionnaire version is currently active.</p>
            @endif
            <x-secondary-button :href="route('questionnaires.index')">
                Manage in Questionnaire Management
            </x-secondary-button>
        </div>
    </x-card>

    <x-card>
        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            @method('PUT')

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="system_name" :value="__('System Name')" />
                    <x-text-input id="system_name" name="system_name" type="text" class="mt-1 block w-full" :value="old('system_name', $settings->get(\App\Models\SystemSetting::KEY_SYSTEM_NAME)?->value)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('system_name')" />
                </div>

                <div>
                    <x-input-label for="school_name" :value="__('School Name')" />
                    <x-text-input id="school_name" name="school_name" type="text" class="mt-1 block w-full" :value="old('school_name', $settings->get(\App\Models\SystemSetting::KEY_SCHOOL_NAME)?->value)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('school_name')" />
                </div>

                <div>
                    <x-input-label for="assessment_availability" :value="__('Assessment Availability')" />
                    <x-select id="assessment_availability" name="assessment_availability" class="mt-1 block w-full">
                        @foreach (['Available', 'Unavailable'] as $availability)
                            <option value="{{ $availability }}" @selected(old('assessment_availability', $settings->get(\App\Models\SystemSetting::KEY_ASSESSMENT_AVAILABILITY)?->value) === $availability)>{{ $availability }}</option>
                        @endforeach
                    </x-select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Informational only in this phase &mdash; does not currently restrict the New Assessment workflow.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('assessment_availability')" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="data_retention_period" :value="__('Data Retention Period')" />
                    <x-text-input id="data_retention_period" name="data_retention_period" type="text" class="mt-1 block w-full" :value="old('data_retention_period', $settings->get(\App\Models\SystemSetting::KEY_DATA_RETENTION_PERIOD)?->value)" required />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Documented retention policy for RA 10173 compliance (informational only).</p>
                    <x-input-error class="mt-2" :messages="$errors->get('data_retention_period')" />
                </div>
            </div>

            <div class="mt-6">
                <x-primary-button>{{ __('Save Settings') }}</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
