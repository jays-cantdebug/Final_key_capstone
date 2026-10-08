{{--
    New Assessment with student entry only (REMOTE_ASSESSMENT_STUDENT_ENTRY_ONLY):
    the minimal page GET /assessments/create shows when no live draft is in
    progress — reached by a typed address, a bookmark, Cancel or an error
    redirect, never by the sidebar (its New Assessment is a POST that goes
    straight to the live page). No Step 1 form: the student enters their own
    details on the student device. One POST button starts a new run.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-body dark:text-slate-100">New Assessment</h2>
    </x-slot>

    @error('student')
        <x-toast type="error">{{ $message }}</x-toast>
    @enderror

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($duplicate)
        @include('assessments.create._duplicate-student', ['duplicate' => $duplicate, 'controls' => false])
    @endif

    <x-card>
        <p class="text-sm text-slate-600 dark:text-slate-400" data-start-page>The student enters their own details and answers the questionnaire on the student PC. Start to get the code for the student PC.</p>

        <form method="POST" action="{{ route('assessments.create.start') }}" class="mt-6 flex justify-center">
            @csrf
            <x-primary-button>{{ __('Start a new assessment') }}</x-primary-button>
        </form>
    </x-card>
</x-app-layout>
