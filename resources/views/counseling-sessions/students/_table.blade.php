<x-table>
    <x-slot:header>
        <form method="GET" action="{{ route('counseling-sessions.students.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="search" :value="__('Student Name')" />
                <x-text-input id="search" name="search" type="text" class="mt-1 w-72 max-w-full" value="{{ $search }}" placeholder="Search by student name" />
            </div>

            <div class="w-48">
                <x-input-label for="follow_up" :value="__('Follow-Up')" />
                <x-select id="follow_up" name="follow_up" class="mt-1 block w-full" x-on:change="$el.form.requestSubmit()">
                    <option value="">All students</option>
                    <option value="needed" @selected($followUp === 'needed')>Follow-up needed</option>
                    <option value="overdue" @selected($followUp === 'overdue')>Overdue only</option>
                </x-select>
            </div>
        </form>
    </x-slot:header>
    <x-slot:head>
        <x-table.th>Student</x-table.th>
        <x-table.th>Sessions</x-table.th>
        <x-table.th>Last Session</x-table.th>
        <x-table.th>Next Scheduled</x-table.th>
        <x-table.th>Follow-Up</x-table.th>
        <x-table.th sticky align="right">Actions</x-table.th>
    </x-slot:head>

    @forelse ($students as $student)
        <tr>
            <x-table.td wrap class="font-medium text-body dark:text-slate-100">
                {{ $student->full_name }}
                <div class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ $student->student_number }}</div>
            </x-table.td>
            <x-table.td>{{ $student->counseling_sessions_count }}</x-table.td>
            <x-table.td>{{ $student->last_session_at?->format('M d, Y') ?? '—' }}</x-table.td>
            <x-table.td>{{ $student->next_session_at?->format('M d, Y g:i A') ?? '—' }}</x-table.td>
            <x-table.td><x-follow-up-badge :status="$student->follow_up_status" :due-date="$student->last_follow_up_date" /></x-table.td>
            <x-table.td sticky align="right">
                <a href="{{ route('counseling-sessions.students.show', $student) }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">View history</a>
            </x-table.td>
        </tr>
    @empty
        <x-table.empty :colspan="6">No students with counseling sessions found.</x-table.empty>
    @endforelse

    <x-slot:footer>
        {{ $students->links() }}
    </x-slot:footer>
</x-table>
