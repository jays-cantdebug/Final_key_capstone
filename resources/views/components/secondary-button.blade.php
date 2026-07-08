@props(['href' => null])

@php
$tag = $href ? 'a' : 'button';
$baseClass = 'inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50';
$defaults = $href ? ['class' => $baseClass] : ['type' => 'button', 'class' => $baseClass];
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge($defaults) }}>{{ $slot }}</{{ $tag }}>
