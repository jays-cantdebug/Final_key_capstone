{{-- Step 2: answer the questionnaire below on this device (as before), or send it to a separate student device. --}}
@if ($remoteDraftInProgress)
    <x-alert type="warning" class="mb-6">
        A student device session is in progress for this assessment.
        <a href="{{ route('assessments.create.remote') }}" class="font-semibold underline">Open the student device page</a>
    </x-alert>
@else
    <div class="mb-6 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-body dark:text-slate-100">Where will the student answer?</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Answer the questionnaire below on this device, or send it to a separate student device and follow the answers here.</p>
        </div>
        <form method="POST" action="{{ route('assessments.create.remote.store') }}" class="shrink-0">
            @csrf
            <x-secondary-button type="submit">{{ __('Send to student device') }}</x-secondary-button>
        </form>
    </div>
@endif
