@props(['label', 'value', 'accent' => 'primary', 'href' => null])

@php
$accentColors = [
    'primary' => 'bg-tint text-primary dark:bg-primary-soft/15 dark:text-primary-soft',
    'gold' => 'bg-gold/15 text-[#A36C14] dark:bg-gold-soft/15 dark:text-[#E0BE7C]',
];
$accentClasses = $accentColors[$accent] ?? 'bg-tint text-primary dark:bg-primary-soft/15 dark:text-primary-soft';

$tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'block rounded-lg border border-slate-200 bg-white p-6 shadow-sm transition dark:border-slate-700 dark:bg-slate-800' . ($href ? ' hover:border-primary/40 hover:shadow-md' : '')]) }}>
    @isset($icon)
        <div class="flex items-center gap-3">
            <span @class(['flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg', $accentClasses])>
                {{ $icon }}
            </span>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $label }}</p>
        </div>
    @else
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $label }}</p>
    @endisset

    <p class="mt-4 text-3xl font-semibold text-body dark:text-slate-100">{{ $value }}</p>

    @isset($trend)
        <p class="mt-1 text-xs font-medium dark:text-slate-400">{{ $trend }}</p>
    @endisset
</{{ $tag }}>
