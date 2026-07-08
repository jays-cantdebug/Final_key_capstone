@props(['label', 'value', 'accent' => 'primary', 'href' => null])

@php
$accentColors = [
    'primary' => 'bg-tint text-primary',
    'gold' => 'bg-gold/15 text-[#A36C14]',
];
$accentClasses = $accentColors[$accent] ?? 'bg-tint text-primary';

$tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'block rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition' . ($href ? ' hover:border-primary/40 hover:shadow-md' : '')]) }}>
    @isset($icon)
        <div class="flex items-center gap-3">
            <span @class(['flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-2xl', $accentClasses])>
                {{ $icon }}
            </span>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
        </div>
    @else
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
    @endisset

    <p class="mt-4 text-3xl font-semibold text-body">{{ $value }}</p>

    @isset($trend)
        <p class="mt-1 text-xs font-medium">{{ $trend }}</p>
    @endisset
</{{ $tag }}>
