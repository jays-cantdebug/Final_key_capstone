<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Counseling Sessions</p>
            <h2 class="text-2xl font-semibold text-slate-900">Schedule Session</h2>
        </div>
    </x-slot>

    @if ($errors->any() && ! $foundStudent)
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm text-slate-600">Search for the student this counseling session is for. Counseling sessions can only be scheduled for existing student records.</p>

        <form method="GET" action="{{ route('counseling-sessions.create') }}" class="mt-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <x-input-label for="student_number" :value="__('Student Number')" />
                <x-text-input id="student_number" name="student_number" type="text" class="mt-1 block w-full" value="{{ request('student_number') }}" autofocus />
            </div>
            <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
        </form>
    </div>

    @if ($searched)
        <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            @if ($foundStudent)
                <div class="border-b border-slate-200 pb-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Student</p>
                    <h3 class="mt-2 text-xl font-semibold text-slate-900">{{ $foundStudent->full_name }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $foundStudent->student_number }}</p>
                </div>

                @if ($errors->any())
                    <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('counseling-sessions.store') }}" class="mt-6">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $foundStudent->id }}" />

                    @include('counseling-sessions._form', ['buttonLabel' => __('Schedule Session')])
                </form>
            @else
                <p class="text-sm text-slate-600">No student found with that student number.</p>
            @endif
        </div>
    @endif
</x-app-layout>
