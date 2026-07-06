@php
    $severityBadgeClasses = [
        'Normal' => 'bg-[#EAF3DE] text-[#27500A]',
        'Mild' => 'bg-[#E6F1FB] text-[#0C447C]',
        'Moderate' => 'bg-[#FAEEDA] text-[#633806]',
        'Severe' => 'bg-[#FAECE7] text-[#712B13]',
        'Extremely Severe' => 'bg-[#FCEBEB] text-[#791F1F]',
    ];
    $monthOptions = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create()->month($m)->format('F')]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Reports</p>
                <h2 class="text-2xl font-semibold text-slate-900">Monthly Assessment Report</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.monthly-assessments.print', ['year' => $year, 'month' => $month]) }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Print
                </a>
                <a href="{{ route('reports.monthly-assessments.pdf', ['year' => $year, 'month' => $month]) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Download PDF
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('reports.monthly-assessments') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="month" :value="__('Month')" />
                <select id="month" name="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}" @selected($month === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="year" :value="__('Year')" />
                <x-text-input id="year" name="year" type="number" class="mt-1 block w-full" value="{{ $year }}" />
            </div>
            <x-secondary-button type="submit">{{ __('View') }}</x-secondary-button>
        </form>
    </div>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Psychometrician</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Overall Severity</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($assessments as $assessment)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $assessment->submitted_at->format('M d, Y g:i A') }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $assessment->student->full_name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $assessment->psychometrician->name }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $severityBadgeClasses[$assessment->result?->overall_status] ?? 'bg-slate-200 text-slate-600' }}">
                                    {{ $assessment->result?->overall_status ?? 'N/A' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-sm text-slate-500">No assessments were submitted in this month.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $assessments->links() }}
        </div>
    </div>
</x-app-layout>
