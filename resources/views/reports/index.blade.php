<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Reports</p>
            <h2 class="text-2xl font-semibold text-slate-900">Reports</h2>
        </div>
    </x-slot>

    <div class="grid gap-6 sm:grid-cols-2">
        @if (auth()->user()->hasRole('psychometrician') || auth()->user()->hasRole('guidance_counselor'))
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Assessment Report</h3>
                <p class="mt-2 text-sm text-slate-600">Open any assessment from Assessment History to print or export its full report.</p>
                <a href="{{ route('assessments.index') }}" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Go to Assessment History
                </a>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Student Assessment History</h3>
                <p class="mt-2 text-sm text-slate-600">Search a student in Assessment History, then print or export their full history.</p>
                <a href="{{ route('assessments.index') }}" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Go to Assessment History
                </a>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Daily Assessment Report</h3>
                <p class="mt-2 text-sm text-slate-600">All assessments submitted on a given day.</p>
                <a href="{{ route('reports.daily-assessments') }}" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    View Report
                </a>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Monthly Assessment Report</h3>
                <p class="mt-2 text-sm text-slate-600">All assessments submitted in a given month.</p>
                <a href="{{ route('reports.monthly-assessments') }}" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    View Report
                </a>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Assessment Summary Report</h3>
                <p class="mt-2 text-sm text-slate-600">Aggregate totals and severity breakdown over a date range.</p>
                <a href="{{ route('reports.assessment-summary') }}" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    View Report
                </a>
            </div>
        @endif

        @if (auth()->user()->hasRole('guidance_counselor'))
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Flagged Students Report</h3>
                <p class="mt-2 text-sm text-slate-600">Filter Flagged Students, then print or export the current list.</p>
                <a href="{{ route('flagged-cases.index') }}" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Go to Flagged Students
                </a>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Counseling Report</h3>
                <p class="mt-2 text-sm text-slate-600">Filter Counseling Sessions, then print or export the current list.</p>
                <a href="{{ route('counseling-sessions.index') }}" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Go to Counseling Sessions
                </a>
            </div>
        @endif

        @if (auth()->user()->hasRole('psychometrician'))
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Questionnaire Usage Report</h3>
                <p class="mt-2 text-sm text-slate-600">How many assessments used each questionnaire version.</p>
                <a href="{{ route('reports.questionnaire-usage') }}" class="mt-4 inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    View Report
                </a>
            </div>
        @endif
    </div>
</x-app-layout>
