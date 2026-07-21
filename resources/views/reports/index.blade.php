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
                <h3 class="text-lg font-semibold text-body">Assessment Report</h3>
                <p class="mt-2 text-sm text-slate-600">Open any assessment from Assessment History to print or export its full report.</p>
                <x-primary-button :href="route('assessments.index')" class="mt-4">
                    Go to Assessment History
                </x-primary-button>
            </x-card>

            <x-card>
                <h3 class="text-lg font-semibold text-body">Student Assessment History</h3>
                <p class="mt-2 text-sm text-slate-600">Search a student in Assessment History, then print or export their full history.</p>
                <x-primary-button :href="route('assessments.index')" class="mt-4">
                    Go to Assessment History
                </x-primary-button>
            </x-card>

            <x-card>
                <h3 class="text-lg font-semibold text-body">Daily Assessment Report</h3>
                <p class="mt-2 text-sm text-slate-600">All assessments submitted on a given day.</p>
                <x-primary-button :href="route('reports.daily-assessments')" class="mt-4">
                    View Report
                </x-primary-button>
            </x-card>

            <x-card>
                <h3 class="text-lg font-semibold text-body">Monthly Assessment Report</h3>
                <p class="mt-2 text-sm text-slate-600">All assessments submitted in a given month.</p>
                <x-primary-button :href="route('reports.monthly-assessments')" class="mt-4">
                    View Report
                </x-primary-button>
            </x-card>

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
                <p class="mt-2 text-sm text-slate-600">Filter Flagged Students, then print or export the current list.</p>
                <x-primary-button :href="route('flagged-cases.index')" class="mt-4">
                    Go to Flagged Students
                </x-primary-button>
            </x-card>

            <x-card>
                <h3 class="text-lg font-semibold text-body">Counseling Report</h3>
                <p class="mt-2 text-sm text-slate-600">Filter Counseling Sessions, then print or export the current list.</p>
                <x-primary-button :href="route('counseling-sessions.index')" class="mt-4">
                    Go to Counseling Sessions
                </x-primary-button>
            </x-card>
        @endif

        @if (auth()->user()->hasRole('psychometrician'))
            <x-card>
                <h3 class="text-lg font-semibold text-body">Questionnaire Usage Report</h3>
                <p class="mt-2 text-sm text-slate-600">How many assessments used each questionnaire version.</p>
                <x-primary-button :href="route('reports.questionnaire-usage')" class="mt-4">
                    View Report
                </x-primary-button>
            </x-card>
        @endif
    </div>
</x-app-layout>
