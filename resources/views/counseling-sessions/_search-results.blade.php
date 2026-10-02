@if ($searched)
    <x-card class="mt-6">
        @if ($foundStudent)
            <div class="border-b border-slate-200 pb-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Student</p>
                <h3 class="mt-2 text-xl font-semibold text-body dark:text-slate-100">{{ $foundStudent->full_name }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $foundStudent->student_number }}</p>
            </div>

            {{-- Validation errors render as per-field tooltips inside _form (see resources/js/session-form.js). --}}
            <form method="POST" action="{{ route('counseling-sessions.store') }}" class="mt-6" novalidate>
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
