<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Student Management</p>
                <h2 class="text-2xl font-semibold text-body">Student Profile</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('assessments.index', ['student_number' => $student->student_number])">
                    View assessment history
                </x-secondary-button>
                <x-primary-button :href="route('students.edit', $student)">
                    Edit student
                </x-primary-button>
                <x-secondary-button :href="route('students.index')">
                    Back to list
                </x-secondary-button>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <x-card>
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-6">
                <div>
                    <p class="text-sm font-semibold text-slate-500">{{ $student->student_number }}</p>
                    <h3 class="mt-2 text-3xl font-semibold text-body">{{ $student->full_name }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ $student->course?->course_name }}</p>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Gender</dt>
                    <dd class="mt-2 text-sm font-medium text-body">{{ $student->gender }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Year Level</dt>
                    <dd class="mt-2 text-sm font-medium text-body">{{ $student->yearLevel?->label }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Section</dt>
                    <dd class="mt-2 text-sm font-medium text-body">{{ $student->section?->section_name }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Course</dt>
                    <dd class="mt-2 text-sm font-medium text-body">{{ $student->course?->course_code }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <h3 class="text-lg font-semibold text-body">Record Summary</h3>
            <div class="mt-4 space-y-4 text-sm text-slate-600">
                <p><span class="font-semibold text-body">Student number:</span> {{ $student->student_number }}</p>
                <p><span class="font-semibold text-body">Full name:</span> {{ $student->full_name }}</p>
                <p><span class="font-semibold text-body">Program:</span> {{ $student->course?->course_name }}</p>
                <p><span class="font-semibold text-body">Section:</span> {{ $student->section?->section_name }}</p>
                <p><span class="font-semibold text-body">Year level:</span> {{ $student->yearLevel?->label }}</p>
            </div>
        </x-card>
    </div>
</x-app-layout>
