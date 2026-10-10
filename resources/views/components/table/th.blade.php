@props(['align' => 'left', 'sticky' => false])

@php
$alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align] ?? 'text-left';
// sticky: see x-table.td (the Actions column stays in view).
$stickyClass = $sticky ? 'sticky right-0 bg-slate-50 dark:bg-slate-900' : '';
@endphp

<th {{ $attributes->merge(['class' => "$stickyClass px-4 py-3 2xl:px-6 $alignClass text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"]) }}>
    {{ $slot }}
</th>
