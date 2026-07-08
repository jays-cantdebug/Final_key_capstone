@php
    $periodLabels = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'all' => 'All Time'];

    $maxVolume = max(1, collect($volumeChart)->max('count'));
    $totalSeverity = max(1, array_sum($severityChart));

    $severityChartColors = [
        'Normal' => '#8FBE6E',
        'Mild' => '#6FA8DC',
        'Moderate' => '#E8B75C',
        'Severe' => '#E08E6D',
        'Extremely Severe' => '#C0574F',
    ];

    $conicStops = [];
    $cursor = 0;
    foreach ($severityChart as $level => $count) {
        $degrees = ($count / $totalSeverity) * 360;
        $conicStops[] = "{$severityChartColors[$level]} {$cursor}deg " . ($cursor + $degrees) . 'deg';
        $cursor += $degrees;
    }
    $conicGradient = 'conic-gradient(' . implode(', ', $conicStops) . ')';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Psychometrician</p>
                <h2 class="text-2xl font-semibold text-body">Dashboard</h2>
            </div>

            <form method="GET" action="{{ route('psychometrician.dashboard') }}">
                @foreach (array_filter(['course_id' => $filters['course_id'] ?? null, 'year_level_id' => $filters['year_level_id'] ?? null]) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                @endforeach
                <x-select name="period" onchange="this.form.submit()" class="!w-auto rounded-2xl text-sm font-semibold text-slate-700">
                    @foreach ($periodLabels as $value => $label)
                        <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                    @endforeach
                </x-select>
            </form>
        </div>
    </x-slot>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $cardIcons = [
                'total' => '<path d="M4 3a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7.414a1 1 0 0 0-.293-.707l-3.414-3.414A1 1 0 0 0 12.586 3H4Zm2 9a1 1 0 1 1 0-2h8a1 1 0 1 1 0 2H6Zm0-4a1 1 0 0 1 0-2h4a1 1 0 1 1 0 2H6Z" />',
                'stress' => '<path fill-rule="evenodd" d="M11.983 1.907a.75.75 0 0 0-1.292-.657L4.204 9.907a.75.75 0 0 0 .58 1.226h4.152l-1.858 6.507a.75.75 0 0 0 1.292.657l6.487-8.657a.75.75 0 0 0-.58-1.226h-4.152l1.858-6.507Z" clip-rule="evenodd" />',
                'anxiety' => '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.63-1.516 2.63H3.72c-1.347 0-2.189-1.463-1.515-2.63L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />',
                'depression' => '<path d="M10 2a6 6 0 0 0-6 6c0 2.5-1 4-1 4h14s-1-1.5-1-4a6 6 0 0 0-6-6Zm0 16a2 2 0 0 0 2-2H8a2 2 0 0 0 2 2Z" />',
            ];
        @endphp
        @foreach ($cards as $key => $card)
            @php
                $isSubscaleCard = in_array($key, ['stress', 'anxiety', 'depression'], true);
                $cardHref = $isSubscaleCard
                    ? route('psychometrician.dashboard', array_merge(array_filter(['course_id' => $filters['course_id'] ?? null, 'year_level_id' => $filters['year_level_id'] ?? null]), ['period' => $period, 'severity_subscale' => $key]))
                    : null;
            @endphp
            <x-stat-card :label="$card['label']" :value="$card['count']" :href="$cardHref">
                <x-slot:icon>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">{!! $cardIcons[$key] !!}</svg>
                </x-slot:icon>
                <x-slot:trend>
                    @if ($card['trend'] === null)
                        <span class="text-slate-400">No prior-period data</span>
                    @else
                        <span class="{{ $card['trend'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $card['trend'] >= 0 ? '▲' : '▼' }} {{ abs($card['trend']) }}% vs last {{ $period === 'all' ? 'period' : $period }}
                        </span>
                    @endif
                </x-slot:trend>
            </x-stat-card>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="text-lg font-semibold text-body">Assessment Volume</h3>
            <p class="text-xs text-slate-500">Assessments submitted per month, last 6 months</p>
            <div class="mt-6 flex h-40 items-end gap-3">
                @foreach ($volumeChart as $point)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <div class="w-full rounded-t-lg bg-primary" style="height: {{ max(4, ($point['count'] / $maxVolume) * 100) }}%"></div>
                        <p class="text-xs font-medium text-slate-500">{{ $point['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-body">Severity Distribution</h3>
            <p class="text-xs text-slate-500">Highest severity tier across all 3 subscales, {{ $periodLabels[$period] }}</p>
            <div class="mt-6 flex items-center gap-6">
                <div class="h-32 w-32 shrink-0 rounded-full" style="background: {{ $conicGradient }}"></div>
                <ul class="space-y-2 text-sm">
                    @foreach ($severityChart as $level => $count)
                        <li class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $severityChartColors[$level] }}"></span>
                            <span class="text-slate-600">{{ $level }}</span>
                            <span class="font-semibold text-body">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-card>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
        <h3 class="text-lg font-semibold text-body">All Assessments</h3>
        <form method="GET" action="{{ route('psychometrician.dashboard') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="period" value="{{ $period }}" />
            <x-select name="course_id" onchange="this.form.submit()" class="!w-auto text-sm">
                <option value="">All Course</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected((string) ($filters['course_id'] ?? '') === (string) $course->id)>{{ $course->course_code }}</option>
                @endforeach
            </x-select>
            <x-select name="year_level_id" onchange="this.form.submit()" class="!w-auto text-sm">
                <option value="">All Year Level</option>
                @foreach ($yearLevels as $yearLevel)
                    <option value="{{ $yearLevel->id }}" @selected((string) ($filters['year_level_id'] ?? '') === (string) $yearLevel->id)>{{ $yearLevel->label }}</option>
                @endforeach
            </x-select>
            @if (($filters['course_id'] ?? null) || ($filters['year_level_id'] ?? null) || ($filters['severity_subscale'] ?? null))
                <a href="{{ route('psychometrician.dashboard', ['period' => $period]) }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <x-table class="mt-4">
        <x-slot:head>
            <x-table.th>Student</x-table.th>
            <x-table.th>Course</x-table.th>
            <x-table.th>Stress</x-table.th>
            <x-table.th>Anxiety</x-table.th>
            <x-table.th>Depression</x-table.th>
            <x-table.th>Flag</x-table.th>
            <x-table.th>Date</x-table.th>
            <x-table.th align="right">Action</x-table.th>
        </x-slot:head>

        @forelse ($assessments as $assessment)
            @php
                $priorityFlag = $assessment->priorityFlag();
                $secondaryCount = $assessment->secondaryFlagCount();
            @endphp
            <tr>
                <x-table.td class="font-medium text-body">{{ $assessment->student->full_name }}</x-table.td>
                <x-table.td>{{ $assessment->student->course?->course_code }}</x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->stress_level" /></x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->anxiety_level" /></x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->depression_level" /></x-table.td>
                <x-table.td>
                    @if ($priorityFlag)
                        <x-flag-badge :type="$priorityFlag->flag_type" :secondary-count="$secondaryCount" />
                    @else
                        <span class="text-slate-400">&mdash;</span>
                    @endif
                </x-table.td>
                <x-table.td>{{ $assessment->submitted_at->format('M d, Y') }}</x-table.td>
                <x-table.td align="right">
                    <a href="{{ route('assessments.show', $assessment) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="8">No assessments found for the selected filters.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $assessments->links() }}
        </x-slot:footer>
    </x-table>
</x-app-layout>
