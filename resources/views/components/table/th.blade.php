@props(['align' => 'left'])

@php
$alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align] ?? 'text-left';
@endphp

<th {{ $attributes->merge(['class' => "px-6 py-3 $alignClass text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"]) }}>
    {{ $slot }}
</th>
