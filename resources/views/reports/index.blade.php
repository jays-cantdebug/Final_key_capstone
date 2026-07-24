<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Reports</p>
            <h2 class="text-2xl font-semibold text-body">Reports</h2>
        </div>
    </x-slot>

    <div class="grid gap-6 sm:grid-cols-2">
        @if (auth()->user()->hasRole('psychometrician') || auth()->user()->hasRole('guidance_counselor'))
            <x-card>
                <h3 class="text-lg font-semibold text-body">Assessment Summary Report</h3>
                <p class="mt-2 text-sm text-slate-600">Institution-wide totals and per-condition severity breakdown, filterable by course, year level, gender, and date range.</p>
                <x-primary-button :href="route('reports.assessment-summary')" class="mt-4">
                    View Report
                </x-primary-button>
            </x-card>
        @endif

        @if (auth()->user()->hasRole('guidance_counselor'))
            <x-card>
                <h3 class="text-lg font-semibold text-body">Flagged Students Report</h3>
                <p class="mt-2 text-sm text-slate-600">Filter by flag type, then print or export.</p>
                <form method="GET" class="mt-4 space-y-3">
                    <x-select name="flag_type">
                        <option value="">All Flag Types</option>
                        <option value="counseling_endorsement">Counseling Endorsement</option>
                        <option value="awareness_notification">Awareness Notification</option>
                    </x-select>
                    <div class="flex gap-2">
                        <x-primary-button type="submit" formaction="{{ route('reports.flagged-students.print') }}">
                            Print
                        </x-primary-button>
                        <x-secondary-button type="submit" formaction="{{ route('reports.flagged-students.pdf') }}">
                            Download PDF
                        </x-secondary-button>
                    </div>
                </form>
            </x-card>
        @endif
    </div>
</x-app-layout>
