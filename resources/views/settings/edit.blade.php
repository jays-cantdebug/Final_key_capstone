<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Settings</p>
            <h2 class="text-2xl font-semibold text-slate-900">System Settings</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Active Questionnaire</h3>
        <p class="mt-1 text-sm text-slate-600">The active questionnaire version is managed in Questionnaire Management, not here, to avoid two conflicting sources of truth.</p>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-slate-50 p-4">
            @if ($activeQuestionnaireVersion)
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ $activeQuestionnaireVersion->questionnaire->title }} v{{ $activeQuestionnaireVersion->version_number }}</p>
                    <p class="text-xs text-slate-500">Effective {{ $activeQuestionnaireVersion->effective_date->format('M d, Y') }}</p>
                </div>
            @else
                <p class="text-sm text-slate-500">No questionnaire version is currently active.</p>
            @endif
            <a href="{{ route('questionnaires.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Manage in Questionnaire Management
            </a>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
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
                    <x-input-label for="notification_severity_threshold" :value="__('Notification Severity Threshold')" />
                    <select id="notification_severity_threshold" name="notification_severity_threshold" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (['Moderate', 'Severe', 'Extremely Severe'] as $level)
                            <option value="{{ $level }}" @selected(old('notification_severity_threshold', $settings->get(\App\Models\SystemSetting::KEY_NOTIFICATION_SEVERITY_THRESHOLD)?->value) === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Minimum severity that triggers a Guidance Counselor notification and Flagged Case. Only affects assessments submitted after this change.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('notification_severity_threshold')" />
                </div>

                <div>
                    <x-input-label for="assessment_availability" :value="__('Assessment Availability')" />
                    <select id="assessment_availability" name="assessment_availability" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (['Available', 'Unavailable'] as $availability)
                            <option value="{{ $availability }}" @selected(old('assessment_availability', $settings->get(\App\Models\SystemSetting::KEY_ASSESSMENT_AVAILABILITY)?->value) === $availability)>{{ $availability }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Informational only in this phase &mdash; does not currently restrict the New Assessment workflow.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('assessment_availability')" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="data_retention_period" :value="__('Data Retention Period')" />
                    <x-text-input id="data_retention_period" name="data_retention_period" type="text" class="mt-1 block w-full" :value="old('data_retention_period', $settings->get(\App\Models\SystemSetting::KEY_DATA_RETENTION_PERIOD)?->value)" required />
                    <p class="mt-1 text-xs text-slate-500">Documented retention policy for RA 10173 compliance (informational only).</p>
                    <x-input-error class="mt-2" :messages="$errors->get('data_retention_period')" />
                </div>
            </div>

            <div class="mt-6">
                <x-primary-button>{{ __('Save Settings') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
