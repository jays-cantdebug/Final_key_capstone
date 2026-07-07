<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Guidance Counselor</p>
            <h2 class="text-2xl font-semibold text-slate-900">Dashboard</h2>
        </div>
    </x-slot>

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Welcome, {{ auth()->user()->name }}</h3>
        <p class="mt-2 text-sm text-slate-600">Here's a snapshot of student assessments and flagged cases.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Students</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalStudents }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Assessments</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalAssessments }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Today's Assessments</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $todaysAssessments }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Flagged Students</p>
            <p class="mt-2 text-3xl font-semibold text-[#791F1F]">{{ $flaggedStudentsSummary }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Recent Assessments</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Student</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Severity</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($recentAssessments as $assessment)
                        <tr>
                            <td class="px-4 py-2 text-sm text-slate-700">{{ $assessment->student->full_name }}</td>
                            <td class="px-4 py-2 text-sm text-slate-700">{{ $assessment->submitted_at->format('M d, Y') }}</td>
                            <td class="px-4 py-2 text-sm text-slate-700">{{ $assessment->result?->highestSeverityLevel() ?? 'N/A' }}</td>
                            <td class="px-4 py-2 text-right text-sm">
                                <a href="{{ route('assessments.show', $assessment) }}" class="rounded-full border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-50">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No assessments yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
