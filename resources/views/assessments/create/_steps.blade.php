@php
    $steps = [
        1 => 'Student Information',
        2 => 'Questionnaire',
        3 => 'Assessment Result',
    ];
@endphp

<nav class="mb-6 flex flex-wrap items-center gap-2 text-sm font-medium text-slate-500">
    @foreach ($steps as $number => $label)
        <span class="inline-flex items-center gap-2 {{ $number === $currentStep ? 'text-[#1F6B3A]' : '' }}">
            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold {{ $number === $currentStep ? 'bg-[#1F6B3A] text-white' : ($number < $currentStep ? 'bg-[#EAF3EC] text-[#1F6B3A]' : 'bg-slate-100 text-slate-400') }}">
                {{ $number }}
            </span>
            {{ $label }}
        </span>
        @if (!$loop->last)
            <span class="text-slate-300">&rarr;</span>
        @endif
    @endforeach
</nav>
