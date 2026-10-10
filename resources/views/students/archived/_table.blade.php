<x-table>
    <x-slot:header>
        <div class="flex w-full flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">Archived students are hidden from the Students list and can’t use Take Again. Nothing was erased: their assessments and counseling history are unchanged.</p>

            <form method="GET" action="{{ route('students.archived.index') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <x-input-label for="search" :value="__('Search')" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 w-72" value="{{ $search }}" placeholder="Search by name" />
                </div>
            </form>
        </div>
    </x-slot:header>
    <x-slot:head>
        <x-table.th>Student #</x-table.th>
        <x-table.th>Name</x-table.th>
        <x-table.th>Program</x-table.th>
        <x-table.th>Assessments</x-table.th>
        <x-table.th>Archived</x-table.th>
        <x-table.th sticky align="right">Actions</x-table.th>
    </x-slot:head>

    @forelse ($students as $student)
        @php($conflicts = $activeMatches[$student->id] ?? collect())
        <tr>
            <x-table.td class="font-medium text-body dark:text-slate-100">{{ $student->student_number }}</x-table.td>
            <x-table.td wrap>
                <div class="font-medium text-body dark:text-slate-100">{{ $student->full_name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $student->section?->section_name ?? 'No section' }}</div>
            </x-table.td>
            <x-table.td>
                <div>{{ $student->course?->course_code ?? 'N/A' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $student->yearLevel?->label ?? 'N/A' }}</div>
            </x-table.td>
            <x-table.td>
                <div>{{ $student->assessments_count }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $student->latest_assessment_at ? 'Latest '.$student->latest_assessment_at->format('M j, Y') : 'None' }}</div>
            </x-table.td>
            <x-table.td>{{ $student->deleted_at->format('M j, Y') }}</x-table.td>
            <x-table.td sticky align="right">
                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                    <a href="{{ route('assessments.index', ['student_number' => $student->student_number]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">Assessments</a>
                    @if ($conflicts->isNotEmpty())
                        <button type="button" disabled class="cursor-not-allowed rounded-md border border-slate-200 px-3 py-1.5 font-medium text-slate-400 dark:border-slate-700 dark:text-slate-500">Restore</button>
                    @else
                        <button
                            type="button"
                            @click="$dispatch('open-restore', { action: @js(route('students.restore', $student)), name: @js($student->full_name.' ('.$student->student_number.')') })"
                            class="rounded-md border border-primary/30 px-3 py-1.5 font-medium text-primary transition hover:bg-tint dark:border-primary-soft/30 dark:text-primary-soft dark:hover:bg-primary-soft/15"
                        >Restore</button>
                    @endif
                </div>
                @if ($conflicts->isNotEmpty())
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Active record exists:
                        @foreach ($conflicts as $active)
                            <a href="{{ route('students.show', $active) }}" class="font-medium text-primary hover:underline dark:text-primary-soft">{{ $active->student_number }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </div>
                @endif
            </x-table.td>
        </tr>
    @empty
        <x-table.empty :colspan="6">No archived students.</x-table.empty>
    @endforelse

    <x-slot:footer>
        {{ $students->links() }}
    </x-slot:footer>
</x-table>
