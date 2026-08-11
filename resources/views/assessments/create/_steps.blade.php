@php
    $steps = [
        1 => 'Student Information',
        2 => 'Questionnaire',
        3 => 'Assessment Result',
    ];
@endphp

<nav class="mb-6 flex flex-wrap items-center gap-2 text-sm font-medium text-slate-500 dark:text-slate-400">
    @foreach ($steps as $number => $label)
        <span class="inline-flex items-center gap-2 {{ $number === $currentStep ? 'text-primary dark:text-primary-soft' : '' }}">
            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold {{ $number === $currentStep ? 'bg-primary text-white dark:bg-primary-soft' : ($number < $currentStep ? 'bg-tint text-primary dark:bg-primary-soft/15 dark:text-primary-soft' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500') }}">
                {{ $number }}
            </span>
            {{ $label }}
        </span>
        @if (!$loop->last)
            <span class="text-slate-300 dark:text-slate-600">&rarr;</span>
        @endif
    @endforeach
</nav>
