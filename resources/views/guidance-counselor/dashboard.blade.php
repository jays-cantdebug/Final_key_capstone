<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Guidance Counselor</p>
                <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Dashboard</h2>
            </div>

            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2" title="{{ __('Edit Profile') }}">
                <x-avatar :user="auth()->user()" size="md" />
                <span class="flex flex-col gap-[3px]">
                    <span class="block text-sm font-semibold text-body dark:text-slate-100">{{ auth()->user()->name }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->role?->display_name ?? 'Unassigned Role' }}</span>
                </span>
            </a>
        </div>
    </x-slot>

    @php
        $maxFlaggedVolume = max(1, collect($flaggedVolumeChart)->max('count'));
        $volumeBarShades = [
            'bg-primary/35 dark:bg-primary-soft/35',
            'bg-primary/50 dark:bg-primary-soft/50',
            'bg-primary/65 dark:bg-primary-soft/65',
            'bg-primary/80 dark:bg-primary-soft/80',
            'bg-primary dark:bg-primary-soft',
            'bg-primary-dark dark:bg-primary-soft',
        ];
        $hasFlagTypeData = array_sum($flagTypeChart) > 0;
        $totalFlagged = max(1, array_sum($flagTypeChart));

        $flagTypeLabels = [
            'counseling_endorsement' => 'Counseling Endorsement',
            'awareness_notification' => 'Awareness Notification',
        ];
        // Vibrant donut-specific palette (see dataviz skill validator):
        // teal/violet, the same hue families as the existing Flag Type
        // badges, just modernized. CVD ΔE 21.0, normal-vision ΔE 30.2,
        // both slots clear 3:1 contrast clean (no relief needed). Badges
        // elsewhere (Flag column, Notifications, Flagged Cases, Assessment
        // Show) keep their existing muted teal/purple — this palette is
        // donut-only.
        $flagTypeColors = [
            'counseling_endorsement' => '#0D9488',
            'awareness_notification' => '#7C3AED',
        ];

        $conicStops = [];
        $cursor = 0;
        foreach ($flagTypeChart as $type => $count) {
            $degrees = ($count / $totalFlagged) * 360;
            $conicStops[] = "{$flagTypeColors[$type]} {$cursor}deg " . ($cursor + $degrees) . 'deg';
            $cursor += $degrees;
        }
        $flagTypeConicGradient = 'conic-gradient(' . implode(', ', $conicStops) . ')';
    @endphp

    <x-card>
        <h3 class="text-lg font-semibold text-body dark:text-slate-100">Welcome, {{ auth()->user()->name }}</h3>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Here's a snapshot of student assessments and flagged cases.</p>
    </x-card>

    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Total Students" :value="$totalStudents" />
        <x-stat-card label="Total Assessments" :value="$totalAssessments" />
        <x-stat-card label="Today's Assessments" :value="$todaysAssessments" />
        <x-stat-card label="Flagged Students" :value="$flaggedStudentsSummary" accent="gold" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="text-lg font-semibold text-body dark:text-slate-100">Flagged Cases Volume</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Flagged assessments per month, last 6 months</p>
            <div class="mt-6 flex h-40 gap-3">
                @foreach ($flaggedVolumeChart as $point)
                    <div class="flex flex-1 flex-col items-center justify-end gap-2">
                        <div
                            class="relative w-full rounded-t-lg {{ $volumeBarShades[$loop->index] ?? 'bg-primary-dark dark:bg-primary-soft' }}"
                            style="height: {{ max(4, ($point['count'] / $maxFlaggedVolume) * 100) }}%"
                            x-data="{ show: false, x: 0, y: 0 }"
                            @mouseenter="show = true"
                            @mouseleave="show = false"
                            @mousemove="x = $event.clientX; y = $event.clientY"
                        >
                            <x-bar-tooltip :message="$point['count'] . ' flagged ' . Str::plural('assessment', $point['count'])" />
                        </div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $point['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card class="grid" :padded="false">
            <div class="flex h-full w-full flex-col p-6">
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Flag Type Distribution</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Counseling Endorsement vs. Awareness Notification, all time</p>
                <div class="mt-6 flex flex-1 items-center justify-center">
                    @if ($hasFlagTypeData)
                        <div
                            class="h-48 w-48 shrink-0 rounded-full"
                            style="background: {{ $flagTypeConicGradient }}"
                            x-data="{ show: false, x: 0, y: 0 }"
                            @mouseenter="show = true"
                            @mouseleave="show = false"
                            @mousemove="x = $event.clientX; y = $event.clientY"
                        >
                            <x-bar-tooltip>
                                <ul class="space-y-1.5">
                                    @foreach ($flagTypeChart as $type => $count)
                                        <li class="flex items-center gap-2">
                                            <span class="h-2 w-2 flex-shrink-0 rounded-full" style="background: {{ $flagTypeColors[$type] }}"></span>
                                            <span>{{ $flagTypeLabels[$type] }}:</span>
                                            <span class="font-semibold">{{ $count }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-bar-tooltip>
                        </div>
                    @else
                        <div class="flex flex-col items-center gap-3">
                            <div class="h-48 w-48 shrink-0 rounded-full bg-slate-100"></div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">No flagged cases to display.</p>
                        </div>
                    @endif
                </div>
            </div>
        </x-card>
    </div>

    <h3 class="mt-8 text-lg font-semibold text-body dark:text-slate-100">Recent Assessments</h3>

    <x-table class="mt-4">
        <x-slot:head>
            <x-table.th>Student</x-table.th>
            <x-table.th>Date</x-table.th>
            <x-table.th>Severity</x-table.th>
            <x-table.th align="right">Actions</x-table.th>
        </x-slot:head>

        @forelse ($recentAssessments as $assessment)
            <tr>
                <x-table.td class="font-medium text-body dark:text-slate-100">{{ $assessment->student->full_name }}</x-table.td>
                <x-table.td>{{ $assessment->submitted_at->format('M d, Y') }}</x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->highestSeverityLevel()" /></x-table.td>
                <x-table.td align="right">
                    <a href="{{ route('assessments.show', $assessment) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">View</a>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="4">No assessments yet.</x-table.empty>
        @endforelse
    </x-table>
</x-app-layout>
