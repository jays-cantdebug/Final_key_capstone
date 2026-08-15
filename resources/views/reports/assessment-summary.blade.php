<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Assessment Summary Report</h2>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.assessment-summary.print', ['course_id' => $courseId, 'year_level_id' => $yearLevelId, 'gender' => $gender, 'date_from' => $dateFrom, 'date_to' => $dateTo])" target="_blank">
                    Print
                </x-secondary-button>
                <x-primary-button :href="route('reports.assessment-summary.pdf', ['course_id' => $courseId, 'year_level_id' => $yearLevelId, 'gender' => $gender, 'date_from' => $dateFrom, 'date_to' => $dateTo])">
                    Download PDF
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('reports.assessment-summary') }}" class="flex flex-wrap items-end gap-3">
            <div class="w-36">
                <x-input-label for="course_id" :value="__('Course')" />
                <x-select id="course_id" name="course_id" class="mt-1 block w-full">
                    <option value="">All courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) $courseId === (string) $course->id)>{{ $course->course_code }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="w-36">
                <x-input-label for="year_level_id" :value="__('Year Level')" />
                <x-select id="year_level_id" name="year_level_id" class="mt-1 block w-full">
                    <option value="">All year levels</option>
                    @foreach ($yearLevels as $yearLevel)
                        <option value="{{ $yearLevel->id }}" @selected((string) $yearLevelId === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="w-40">
                <x-input-label for="gender" :value="__('Gender')" />
                <x-select id="gender" name="gender" class="mt-1 block w-full">
                    <option value="">All genders</option>
                    @foreach (['Male', 'Female', 'Prefer not to say'] as $genderOption)
                        <option value="{{ $genderOption }}" @selected($gender === $genderOption)>{{ $genderOption }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <x-input-label for="date_from" :value="__('Date From')" />
                <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full" value="{{ $dateFrom }}" />
            </div>
            <div>
                <x-input-label for="date_to" :value="__('Date To')" />
                <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full" value="{{ $dateTo }}" />
            </div>
            <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
            @if ($courseId || $yearLevelId || $gender || $dateFrom || $dateTo)
                <x-secondary-button :href="route('reports.assessment-summary')">
                    {{ __('Clear') }}
                </x-secondary-button>
            @endif
        </form>
    </x-card>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Total Students" :value="$totalStudents" />
        <x-stat-card label="Total Assessments" :value="$totalAssessments" />
        <x-stat-card label="Total Counseling Endorsements" :value="$counselingEndorsements" accent="gold" />
        <x-stat-card label="Total Awareness Notifications" :value="$awarenessNotifications" accent="gold" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        @foreach ([
            'Depression' => $depressionBySeverity,
            'Anxiety' => $anxietyBySeverity,
            'Stress' => $stressBySeverity,
        ] as $conditionLabel => $conditionCounts)
            <x-card>
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">{{ $conditionLabel }}</h3>

                <x-severity-bar-chart :counts="$conditionCounts" class="mt-4" />

                <x-table class="mt-4">
                    <x-slot:head>
                        <x-table.th>Severity</x-table.th>
                        <x-table.th>Count</x-table.th>
                    </x-slot:head>

                    @foreach ($conditionCounts as $severity => $count)
                        <tr>
                            <x-table.td><x-severity-badge :level="$severity" /></x-table.td>
                            <x-table.td class="font-medium text-body dark:text-slate-100">{{ $count }}</x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
