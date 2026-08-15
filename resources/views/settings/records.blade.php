@php
    $statusColors = ['Active' => 'green', 'Inactive' => 'slate'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Records</h2>
    </x-slot>

    @include('settings._tabs', ['active' => 'records'])

    @if (session('status'))
        <x-toast type="success">{{ session('status') }}</x-toast>
    @endif

    @if ($errors->any())
        <x-toast type="error">{{ $errors->first() }}</x-toast>
    @endif

    <style>
        /* Scoped to Settings > Records only: keep these narrow 3-up tables from
           needing horizontal scroll inside their card (few columns, tight width). */
        .records-grid .records-table td,
        .records-grid .records-table th {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        .records-grid .records-table td {
            white-space: normal;
        }
    </style>

    <div class="records-grid grid gap-6 lg:grid-cols-3">
        <x-table class="records-table">
            <x-slot:header>
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Courses</h3>
                <x-primary-button :href="route('courses.create')" class="!text-xs">Add</x-primary-button>
            </x-slot:header>
            <x-slot:head>
                <x-table.th>Code</x-table.th>
                <x-table.th>Name</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right">Actions</x-table.th>
            </x-slot:head>

            @forelse ($courses as $course)
                <tr>
                    <x-table.td class="font-medium text-body dark:text-slate-100">{{ $course->course_code }}</x-table.td>
                    <x-table.td>{{ $course->course_name }}</x-table.td>
                    <x-table.td><x-badge :color="$statusColors[$course->status] ?? 'slate'">{{ $course->status }}</x-badge></x-table.td>
                    <x-table.td align="right">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            <a href="{{ route('courses.edit', $course) }}" title="Edit" aria-label="Edit course" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" /></svg>
                            </a>
                            <form id="delete-course-form-{{ $course->id }}" method="POST" action="{{ route('courses.destroy', $course) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button
                                type="button"
                                title="Delete" aria-label="Delete course"
                                @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Delete this course?', message: 'This is blocked if any student references it.', confirmLabel: 'Delete', formId: 'delete-course-form-{{ $course->id }}' })"
                                class="ml-2 inline-flex h-8 w-8 items-center justify-center rounded-md border border-rose-200 text-rose-700 transition hover:bg-rose-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="4">No courses yet.</x-table.empty>
            @endforelse
        </x-table>

        <x-table class="records-table">
            <x-slot:header>
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Year Levels</h3>
                <x-primary-button :href="route('year-levels.create')" class="!text-xs">Add</x-primary-button>
            </x-slot:header>
            <x-slot:head>
                <x-table.th>Label</x-table.th>
                <x-table.th>Order</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right">Actions</x-table.th>
            </x-slot:head>

            @forelse ($yearLevels as $yearLevel)
                <tr>
                    <x-table.td class="font-medium text-body dark:text-slate-100">{{ $yearLevel->label }}</x-table.td>
                    <x-table.td>{{ $yearLevel->display_order }}</x-table.td>
                    <x-table.td><x-badge :color="$statusColors[$yearLevel->status] ?? 'slate'">{{ $yearLevel->status }}</x-badge></x-table.td>
                    <x-table.td align="right">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            <a href="{{ route('year-levels.edit', $yearLevel) }}" title="Edit" aria-label="Edit year level" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" /></svg>
                            </a>
                            <form id="delete-year-level-form-{{ $yearLevel->id }}" method="POST" action="{{ route('year-levels.destroy', $yearLevel) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button
                                type="button"
                                title="Delete" aria-label="Delete year level"
                                @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Delete this year level?', message: 'This is blocked if any student references it.', confirmLabel: 'Delete', formId: 'delete-year-level-form-{{ $yearLevel->id }}' })"
                                class="ml-2 inline-flex h-8 w-8 items-center justify-center rounded-md border border-rose-200 text-rose-700 transition hover:bg-rose-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="4">No year levels yet.</x-table.empty>
            @endforelse
        </x-table>

        <x-table class="records-table">
            <x-slot:header>
                <h3 class="text-lg font-semibold text-body dark:text-slate-100">Sections</h3>
                <x-primary-button :href="route('sections.create')" class="!text-xs">Add</x-primary-button>
            </x-slot:header>
            <x-slot:head>
                <x-table.th>Name</x-table.th>
                <x-table.th>Capacity</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right">Actions</x-table.th>
            </x-slot:head>

            @forelse ($sections as $section)
                <tr>
                    <x-table.td class="font-medium text-body dark:text-slate-100">{{ $section->section_name }}</x-table.td>
                    <x-table.td>{{ $section->capacity ?? '—' }}</x-table.td>
                    <x-table.td><x-badge :color="$statusColors[$section->status] ?? 'slate'">{{ $section->status }}</x-badge></x-table.td>
                    <x-table.td align="right">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            <a href="{{ route('sections.edit', $section) }}" title="Edit" aria-label="Edit section" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" /></svg>
                            </a>
                            <form id="delete-section-form-{{ $section->id }}" method="POST" action="{{ route('sections.destroy', $section) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <button
                                type="button"
                                title="Delete" aria-label="Delete section"
                                @click="$dispatch('open-confirm', { name: 'confirm-modal', title: 'Delete this section?', message: 'This is blocked if any student references it.', confirmLabel: 'Delete', formId: 'delete-section-form-{{ $section->id }}' })"
                                class="ml-2 inline-flex h-8 w-8 items-center justify-center rounded-md border border-rose-200 text-rose-700 transition hover:bg-rose-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>
                            </button>
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="4">No sections yet.</x-table.empty>
            @endforelse
        </x-table>
    </div>

    <x-confirm-modal />
</x-app-layout>
