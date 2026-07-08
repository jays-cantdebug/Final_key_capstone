@php
    $statusColors = ['Active' => 'green', 'Inactive' => 'slate'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Settings</p>
            <h2 class="text-2xl font-semibold text-body">Records</h2>
        </div>
    </x-slot>

    @include('settings._tabs', ['active' => 'records'])

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-table>
            <x-slot:header>
                <h3 class="text-lg font-semibold text-body">Courses</h3>
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
                    <x-table.td class="font-medium text-body">{{ $course->course_code }}</x-table.td>
                    <x-table.td>{{ $course->course_name }}</x-table.td>
                    <x-table.td><x-badge :color="$statusColors[$course->status] ?? 'slate'">{{ $course->status }}</x-badge></x-table.td>
                    <x-table.td align="right">
                        <div class="inline-flex flex-wrap justify-end gap-2">
                            <a href="{{ route('courses.edit', $course) }}" class="rounded-full border border-slate-300 px-3 py-1 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                            <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Delete this course? This is blocked if any student references it.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                            </form>
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="4">No courses yet.</x-table.empty>
            @endforelse
        </x-table>

        <x-table>
            <x-slot:header>
                <h3 class="text-lg font-semibold text-body">Year Levels</h3>
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
                    <x-table.td class="font-medium text-body">{{ $yearLevel->label }}</x-table.td>
                    <x-table.td>{{ $yearLevel->display_order }}</x-table.td>
                    <x-table.td><x-badge :color="$statusColors[$yearLevel->status] ?? 'slate'">{{ $yearLevel->status }}</x-badge></x-table.td>
                    <x-table.td align="right">
                        <div class="inline-flex flex-wrap justify-end gap-2">
                            <a href="{{ route('year-levels.edit', $yearLevel) }}" class="rounded-full border border-slate-300 px-3 py-1 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                            <form method="POST" action="{{ route('year-levels.destroy', $yearLevel) }}" onsubmit="return confirm('Delete this year level? This is blocked if any student references it.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                            </form>
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="4">No year levels yet.</x-table.empty>
            @endforelse
        </x-table>

        <x-table>
            <x-slot:header>
                <h3 class="text-lg font-semibold text-body">Sections</h3>
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
                    <x-table.td class="font-medium text-body">{{ $section->section_name }}</x-table.td>
                    <x-table.td>{{ $section->capacity ?? '—' }}</x-table.td>
                    <x-table.td><x-badge :color="$statusColors[$section->status] ?? 'slate'">{{ $section->status }}</x-badge></x-table.td>
                    <x-table.td align="right">
                        <div class="inline-flex flex-wrap justify-end gap-2">
                            <a href="{{ route('sections.edit', $section) }}" class="rounded-full border border-slate-300 px-3 py-1 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                            <form method="POST" action="{{ route('sections.destroy', $section) }}" onsubmit="return confirm('Delete this section? This is blocked if any student references it.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                            </form>
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="4">No sections yet.</x-table.empty>
            @endforelse
        </x-table>
    </div>
</x-app-layout>
