<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Counseling Sessions</p>
            <h2 class="text-2xl font-semibold text-body dark:text-slate-100">Schedule Session</h2>
        </div>
    </x-slot>

    @if ($errors->any() && ! $foundStudent)
        <x-alert type="error" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <x-card>
        <p class="text-sm text-slate-600 dark:text-slate-400">Search for the student this counseling session is for by name. Counseling sessions can only be scheduled for existing student records.</p>

        <form method="GET" action="{{ route('counseling-sessions.create') }}" class="mt-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <x-input-label for="search" :value="__('Student Name')" />
                <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="Search by student name" autofocus />
            </div>
            <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
        </form>
    </x-card>

    @if ($searched)
        <x-card class="mt-6">
            @if ($foundStudent)
                <div class="border-b border-slate-200 pb-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Student</p>
                    <h3 class="mt-2 text-xl font-semibold text-body dark:text-slate-100">{{ $foundStudent->full_name }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $foundStudent->student_number }}</p>
                </div>

                @if ($errors->any())
                    <x-alert type="error" class="mt-6">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('counseling-sessions.store') }}" class="mt-6">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $foundStudent->id }}" />

                    @include('counseling-sessions._form', ['buttonLabel' => __('Schedule Session')])
                </form>
            @elseif ($matches->isNotEmpty())
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $matches->count() }} matching students &mdash; select one</p>
                <ul class="mt-4 divide-y divide-slate-200">
                    @foreach ($matches as $student)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-body dark:text-slate-100">{{ $student->full_name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $student->course?->course_code }} &mdash; {{ $student->yearLevel?->label }} / {{ $student->section?->section_name }}</p>
                            </div>
                            <a href="{{ route('counseling-sessions.create', ['student_id' => $student->id]) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-700">Select</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-400">No student found matching that name.</p>
            @endif
        </x-card>
    @endif
</x-app-layout>
