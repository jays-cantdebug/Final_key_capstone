@props(['align' => 'left'])

@php
$alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align] ?? 'text-left';
@endphp

<td {{ $attributes->merge(['class' => "whitespace-nowrap px-6 py-4 $alignClass text-sm text-slate-700"]) }}>
    {{ $slot }}
</td>
