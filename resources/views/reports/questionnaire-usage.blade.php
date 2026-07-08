<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Reports</p>
                <h2 class="text-2xl font-semibold text-body">Questionnaire Usage Report</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('reports.questionnaire-usage.print')" target="_blank">
                    Print
                </x-secondary-button>
                <x-primary-button :href="route('reports.questionnaire-usage.pdf')">
                    Download PDF
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <x-table>
        <x-slot:head>
            <x-table.th>Questionnaire</x-table.th>
            <x-table.th>Version</x-table.th>
            <x-table.th>Status</x-table.th>
            <x-table.th>Questions</x-table.th>
            <x-table.th>Assessments Used</x-table.th>
        </x-slot:head>

        @forelse ($versions as $version)
            <tr>
                <x-table.td class="font-medium text-body">{{ $version->questionnaire->title }}</x-table.td>
                <x-table.td>v{{ $version->version_number }}</x-table.td>
                <x-table.td>{{ $version->status }}</x-table.td>
                <x-table.td>{{ $version->questions_count }}</x-table.td>
                <x-table.td>{{ $version->assessments_count }}</x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="5">No questionnaire versions found.</x-table.empty>
        @endforelse
    </x-table>
</x-app-layout>
