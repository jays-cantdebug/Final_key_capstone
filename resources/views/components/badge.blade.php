@props(['color' => 'slate'])

@php
$colorClasses = [
    'green' => 'bg-[#EAF3DE] text-[#27500A]',
    'blue' => 'bg-[#E6F1FB] text-[#0C447C]',
    'amber' => 'bg-[#FAEEDA] text-[#633806]',
    'orange' => 'bg-[#FAECE7] text-[#712B13]',
    'red' => 'bg-[#FCEBEB] text-[#791F1F]',
    'teal' => 'bg-[#E3F4F1] text-[#0F5C50]',
    'purple' => 'bg-[#F1E9FB] text-[#4A1E82]',
    'slate' => 'bg-slate-100 text-slate-600',
][$color] ?? 'bg-slate-100 text-slate-600';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold $colorClasses"]) }}>
    {{ $slot }}
</span>
