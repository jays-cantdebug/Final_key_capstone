@props(['href' => null])

@php
$tag = $href ? 'a' : 'button';
$baseClass = 'inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50';
$defaults = $href ? ['class' => $baseClass] : ['type' => 'submit', 'class' => $baseClass];
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge($defaults) }}>{{ $slot }}</{{ $tag }}>
