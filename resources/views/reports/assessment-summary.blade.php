@php
    $severityBadgeClasses = [
        'Normal' => 'bg-[#EAF3DE] text-[#27500A]',
        'Mild' => 'bg-[#E6F1FB] text-[#0C447C]',
        'Moderate' => 'bg-[#FAEEDA] text-[#633806]',
        'Severe' => 'bg-[#FAECE7] text-[#712B13]',
        'Extremely Severe' => 'bg-[#FCEBEB] text-[#791F1F]',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Reports</p>
                <h2 class="text-2xl font-semibold text-slate-900">Assessment Summary Report</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.assessment-summary.print', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" target="_blank" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Print
                </a>
                <a href="{{ route('reports.assessment-summary.pdf', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Download PDF
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
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
                <a href="{{ route('reports.assessment-summary') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                    {{ __('Clear') }}
                </a>
            @endif
        </form>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Assessments</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $total }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Flagged Assessments</p>
            <p class="mt-2 text-3xl font-semibold text-[#791F1F]">{{ $flaggedCount }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">By Overall Severity</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Severity</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach ($bySeverity as $severity => $count)
                        <tr>
                            <td class="px-4 py-2 text-sm">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $severityBadgeClasses[$severity] ?? 'bg-slate-200 text-slate-600' }}">
                                    {{ $severity }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
