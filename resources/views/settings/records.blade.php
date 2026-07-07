@php
    $statusBadgeClasses = [
        'Active' => 'bg-emerald-100 text-emerald-700',
        'Inactive' => 'bg-slate-200 text-slate-600',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Settings</p>
            <h2 class="text-2xl font-semibold text-slate-900">Records</h2>
        </div>
    </x-slot>

    @include('settings._tabs', ['active' => 'records'])

    @if (session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 p-6">
                <h3 class="text-lg font-semibold text-slate-900">Courses</h3>
                <a href="{{ route('courses.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-700">
                    Add
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Code</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($courses as $course)
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ $course->course_code }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ $course->course_name }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClasses[$course->status] ?? 'bg-slate-200 text-slate-600' }}">{{ $course->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('courses.edit', $course) }}" class="rounded-full border border-slate-300 px-3 py-1 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                                        <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Delete this course? This is blocked if any student references it.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No courses yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 p-6">
                <h3 class="text-lg font-semibold text-slate-900">Year Levels</h3>
                <a href="{{ route('year-levels.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-700">
                    Add
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Label</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Order</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($yearLevels as $yearLevel)
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ $yearLevel->label }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ $yearLevel->display_order }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClasses[$yearLevel->status] ?? 'bg-slate-200 text-slate-600' }}">{{ $yearLevel->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('year-levels.edit', $yearLevel) }}" class="rounded-full border border-slate-300 px-3 py-1 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                                        <form method="POST" action="{{ route('year-levels.destroy', $yearLevel) }}" onsubmit="return confirm('Delete this year level? This is blocked if any student references it.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No year levels yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 p-6">
                <h3 class="text-lg font-semibold text-slate-900">Sections</h3>
                <a href="{{ route('sections.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-700">
                    Add
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Capacity</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($sections as $section)
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ $section->section_name }}</td>
                                <td class="px-4 py-2 text-sm text-slate-700">{{ $section->capacity ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClasses[$section->status] ?? 'bg-slate-200 text-slate-600' }}">{{ $section->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('sections.edit', $section) }}" class="rounded-full border border-slate-300 px-3 py-1 font-medium text-slate-700 transition hover:bg-slate-50">Edit</a>
                                        <form method="POST" action="{{ route('sections.destroy', $section) }}" onsubmit="return confirm('Delete this section? This is blocked if any student references it.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No sections yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
