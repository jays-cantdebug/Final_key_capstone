<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Student Management</p>
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Students</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    <x-table>
        <x-slot:header>
            <div class="flex w-full flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-slate-600 dark:text-slate-400">Every student record originates from New Assessment Step 1 — students cannot be registered here directly.</p>

                <form method="GET" action="{{ route('students.index') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <x-input-label for="search" :value="__('Search')" />
                        <x-text-input id="search" name="search" type="text" class="mt-1 w-72" value="{{ $search }}" placeholder="Search by name" />
                    </div>
                    <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                    @if ($search)
                        <x-secondary-button :href="route('students.index')">
                            {{ __('Clear') }}
                        </x-secondary-button>
                    @endif
                </form>
            </div>
        </x-slot:header>
        <x-slot:head>
            <x-table.th>Student #</x-table.th>
            <x-table.th>Name</x-table.th>
            <x-table.th>Program</x-table.th>
            <x-table.th>Gender</x-table.th>
            <x-table.th align="right">Actions</x-table.th>
        </x-slot:head>

        @forelse ($students as $student)
            <tr>
                <x-table.td class="font-medium text-body dark:text-slate-100">{{ $student->student_number }}</x-table.td>
                <x-table.td>
                    <div class="font-medium text-body dark:text-slate-100">{{ $student->full_name }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $student->section?->section_name ?? 'No section' }}</div>
                </x-table.td>
                <x-table.td>
                    <div>{{ $student->course?->course_code ?? 'N/A' }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $student->yearLevel?->label ?? 'N/A' }}</div>
                </x-table.td>
                <x-table.td>{{ $student->gender }}</x-table.td>
                <x-table.td align="right">
                    <div class="inline-flex flex-wrap justify-end gap-2">
                        <a href="{{ route('students.show', $student) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">View</a>
                        <a href="{{ route('assessments.create.retake', $student) }}" class="rounded-md border border-primary/30 px-3 py-1.5 font-medium text-primary transition hover:bg-tint dark:border-primary-soft/30 dark:text-primary-soft dark:hover:bg-primary-soft/15">Take Again</a>
                        <a href="{{ route('students.edit', $student) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">Edit</a>
                        <form id="delete-student-form-{{ $student->id }}" method="POST" action="{{ route('students.destroy', $student) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                        <button
                            type="button"
                            @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Delete this student record?', message: 'This will archive the student record — it can be reviewed later in Student Information Management.', confirmLabel: 'Delete', formId: 'delete-student-form-{{ $student->id }}' })"
                            class="rounded-md border border-rose-200 px-3 py-1.5 font-medium text-rose-700 transition hover:bg-rose-50"
                        >Delete</button>
                    </div>
                </x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="5">No student records found.</x-table.empty>
        @endforelse

        <x-slot:footer>
            {{ $students->links() }}
        </x-slot:footer>
    </x-table>

    <x-confirm-modal />
</x-app-layout>
