<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Student Management</p>
                <h2 class="text-2xl font-semibold text-slate-900">Student Profile</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('assessments.index', ['student_number' => $student->student_number]) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    View assessment history
                </a>
                <a href="{{ route('students.edit', $student) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Edit student
                </a>
                <a href="{{ route('students.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Back to list
                </a>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-6">
                <div>
                    <p class="text-sm font-semibold text-slate-500">{{ $student->student_number }}</p>
                    <h3 class="mt-2 text-3xl font-semibold text-slate-900">{{ $student->full_name }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ $student->course?->course_name }}</p>
                </div>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $student->status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                    {{ $student->status }}
                </span>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sex</dt>
                    <dd class="mt-2 text-sm font-medium text-slate-900">{{ $student->sex }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Year Level</dt>
                    <dd class="mt-2 text-sm font-medium text-slate-900">{{ $student->yearLevel?->label }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Section</dt>
                    <dd class="mt-2 text-sm font-medium text-slate-900">{{ $student->section?->section_name }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Course</dt>
                    <dd class="mt-2 text-sm font-medium text-slate-900">{{ $student->course?->course_code }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Record Summary</h3>
            <div class="mt-4 space-y-4 text-sm text-slate-600">
                <p><span class="font-semibold text-slate-900">Student number:</span> {{ $student->student_number }}</p>
                <p><span class="font-semibold text-slate-900">Full name:</span> {{ $student->full_name }}</p>
                <p><span class="font-semibold text-slate-900">Program:</span> {{ $student->course?->course_name }}</p>
                <p><span class="font-semibold text-slate-900">Section:</span> {{ $student->section?->section_name }}</p>
                <p><span class="font-semibold text-slate-900">Year level:</span> {{ $student->yearLevel?->label }}</p>
            </div>
        </div>
    </div>
</x-app-layout>