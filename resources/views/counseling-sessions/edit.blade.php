<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#A36C14]">Counseling Sessions</p>
                <h2 class="text-2xl font-semibold text-slate-900">Edit Session</h2>
            </div>
            <a href="{{ route('counseling-sessions.show', $session) }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                View session
            </a>
        </div>
    </x-slot>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="border-b border-slate-200 pb-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Student</p>
            <h3 class="mt-2 text-xl font-semibold text-slate-900">{{ $session->student->full_name }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ $session->student->student_number }}</p>
        </div>

        <form method="POST" action="{{ route('counseling-sessions.update', $session) }}" class="mt-6">
            @csrf
            @method('PUT')

            @include('counseling-sessions._form', ['buttonLabel' => __('Update Session')])
        </form>
    </div>
</x-app-layout>
