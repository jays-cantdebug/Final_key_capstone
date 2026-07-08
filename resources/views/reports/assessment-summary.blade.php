<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Reports</p>
                <h2 class="text-2xl font-semibold text-body">Assessment Summary Report</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.assessment-summary.print', ['date_from' => $dateFrom, 'date_to' => $dateTo])" target="_blank">
                    Print
                </x-secondary-button>
                <x-primary-button :href="route('reports.assessment-summary.pdf', ['date_from' => $dateFrom, 'date_to' => $dateTo])">
                    Download PDF
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('reports.assessment-summary') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="date_from" :value="__('Date From')" />
                <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full" value="{{ $dateFrom }}" />
            </div>
            <div>
                <x-input-label for="date_to" :value="__('Date To')" />
                <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full" value="{{ $dateTo }}" />
            </div>
            <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
            @if ($dateFrom || $dateTo)
                <x-secondary-button :href="route('reports.assessment-summary')">
                    {{ __('Clear') }}
                </x-secondary-button>
            @endif
        </form>
    </x-card>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-card label="Total Assessments" :value="$total" />
        <x-stat-card label="Flagged Assessments" :value="$flaggedCount" accent="gold" />
    </div>

    <h3 class="mt-8 text-lg font-semibold text-body">By Overall Severity</h3>
    <x-table class="mt-4">
        <x-slot:head>
            <x-table.th>Severity</x-table.th>
            <x-table.th>Count</x-table.th>
        </x-slot:head>

        @foreach ($bySeverity as $severity => $count)
            <tr>
                <x-table.td><x-severity-badge :level="$severity" /></x-table.td>
                <x-table.td class="font-medium text-body">{{ $count }}</x-table.td>
            </tr>
        @endforeach
    </x-table>
</x-app-layout>
