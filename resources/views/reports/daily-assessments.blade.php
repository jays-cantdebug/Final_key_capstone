<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Reports</p>
                <h2 class="text-2xl font-semibold text-body">Daily Assessment Report</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.daily-assessments.print', ['date' => $date])" target="_blank">
                    Print
                </x-secondary-button>
                <x-primary-button :href="route('reports.daily-assessments.pdf', ['date' => $date])">
                    Download PDF
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('reports.daily-assessments') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="date" :value="__('Date')" />
                <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" value="{{ $date }}" />
            </div>
            <x-secondary-button type="submit">{{ __('View') }}</x-secondary-button>
        </form>
    </x-card>

    <x-table>
        <x-slot:head>
            <x-table.th>Time</x-table.th>
            <x-table.th>Student</x-table.th>
            <x-table.th>Psychometrician</x-table.th>
            <x-table.th>Overall Severity</x-table.th>
        </x-slot:head>

        @forelse ($assessments as $assessment)
            <tr>
                <x-table.td>{{ $assessment->submitted_at->format('g:i A') }}</x-table.td>
                <x-table.td class="font-medium text-body">{{ $assessment->student->full_name }}</x-table.td>
                <x-table.td>{{ $assessment->psychometrician->name }}</x-table.td>
                <x-table.td><x-severity-badge :level="$assessment->result?->highestSeverityLevel()" /></x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="4">No assessments were submitted on this date.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $assessments->links() }}
        </x-slot:footer>
    </x-table>
</x-app-layout>
