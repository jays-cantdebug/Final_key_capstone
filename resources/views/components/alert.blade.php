@props(['type' => 'info'])

@php
$variants = [
    'success' => ['bg-tint border-primary/20 text-[#1A5A31]', 'M16.7 5.3a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4 0l-3-3a1 1 0 1 1 1.4-1.4L9 11.6l6.3-6.3a1 1 0 0 1 1.4 0Z'],
    'error' => ['bg-[#FCEBEB] border-red-200 text-[#791F1F]', 'M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm1 11H9v2h2v-2Zm0-7H9v5h2V6Z'],
    'warning' => ['bg-[#FAEEDA] border-gold/30 text-[#633806]', 'M8.485 3.495c.673-1.166 2.357-1.166 3.03 0l6.28 10.875c.673 1.167-.169 2.63-1.515 2.63H3.72c-1.346 0-2.188-1.463-1.515-2.63L8.485 3.495ZM10 7a1 1 0 0 1 1 1v3a1 1 0 1 1-2 0V8a1 1 0 0 1 1-1Zm0 7a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z'],
    'info' => ['bg-slate-100 border-slate-200 text-slate-700', 'M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm1 5H9V5h2v2Zm0 8H9V9h2v6Z'],
][$type] ?? ['bg-slate-100 border-slate-200 text-slate-700', ''];

[$classes, $iconPath] = $variants;
@endphp

<div {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm font-medium $classes"]) }}>
    <svg class="mt-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="{{ $iconPath }}" /></svg>
    <div>{{ $slot }}</div>
</div>
