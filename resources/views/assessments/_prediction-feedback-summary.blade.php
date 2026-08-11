<div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-4 text-sm text-slate-700 dark:text-slate-300">
    <p class="font-semibold text-body dark:text-slate-100">
        {{ $feedback->is_confirmed ? 'Confirmed' : 'Corrected' }} by {{ $feedback->psychometrician->name }} &mdash; {{ $feedback->updated_at->format('M d, Y g:i A') }}
    </p>
    @if (! $feedback->is_confirmed)
        <ul class="mt-2 space-y-1 text-slate-600 dark:text-slate-400">
            @if ($feedback->corrected_depression_level)
                <li>Depression &rarr; {{ $feedback->corrected_depression_level }}</li>
            @endif
            @if ($feedback->corrected_anxiety_level)
                <li>Anxiety &rarr; {{ $feedback->corrected_anxiety_level }}</li>
            @endif
            @if ($feedback->corrected_stress_level)
                <li>Stress &rarr; {{ $feedback->corrected_stress_level }}</li>
            @endif
        </ul>
    @endif
    @if ($feedback->notes)
        <p class="mt-2 italic text-slate-600 dark:text-slate-400">"{{ $feedback->notes }}"</p>
    @endif
</div>
