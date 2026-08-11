@props(['color' => 'slate'])

@php
$colorClasses = [
    'green' => 'bg-[#EAF3DE] text-[#27500A] dark:bg-[#1C3B2A] dark:text-[#8FCB9F]',
    'blue' => 'bg-[#E6F1FB] text-[#0C447C] dark:bg-[#1B3350] dark:text-[#8FBEE8]',
    'amber' => 'bg-[#FAEEDA] text-[#633806] dark:bg-[#3D2F14] dark:text-[#E0BE7C]',
    'orange' => 'bg-[#FAECE7] text-[#712B13] dark:bg-[#3D2417] dark:text-[#E3A17E]',
    'red' => 'bg-[#FCEBEB] text-[#791F1F] dark:bg-[#3B1C1C] dark:text-[#E39A9A]',
    'teal' => 'bg-[#E3F4F1] text-[#0F5C50] dark:bg-[#123832] dark:text-[#82CFC2]',
    'purple' => 'bg-[#F1E9FB] text-[#4A1E82] dark:bg-[#2C1F42] dark:text-[#C6A6EC]',
    'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
][$color] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-md px-3 py-1 text-xs font-semibold $colorClasses"]) }}>
    {{ $slot }}
</span>
