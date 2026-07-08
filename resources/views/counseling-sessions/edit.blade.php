<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Counseling Sessions</p>
                <h2 class="text-2xl font-semibold text-body">Edit Session</h2>
            </div>
            <x-secondary-button :href="route('counseling-sessions.show', $session)">
                View session
            </x-secondary-button>
        </div>
    </x-slot>

    <x-card>
        <div class="border-b border-slate-200 pb-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Student</p>
            <h3 class="mt-2 text-xl font-semibold text-body">{{ $session->student->full_name }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ $session->student->student_number }}</p>
        </div>

        <form method="POST" action="{{ route('counseling-sessions.update', $session) }}" class="mt-6">
            @csrf
            @method('PUT')

            @include('counseling-sessions._form', ['buttonLabel' => __('Update Session')])
        </form>
    </x-card>
</x-app-layout>
