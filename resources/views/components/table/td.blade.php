@props(['align' => 'left', 'wrap' => false, 'sticky' => false])

@php
$alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align] ?? 'text-left';
// wrap: long text (names, question text) may break onto more lines, so the
// Actions column stays in view at 1366/1024; "wide" keeps a wider minimum.
$whitespaceClass = match ($wrap) {
    'wide' => 'min-w-[16rem] whitespace-normal break-words',
    false, null => 'whitespace-nowrap',
    default => 'min-w-[9rem] whitespace-normal break-words',
};
// sticky: the Actions column stays at the right edge while the rest of a
// wide table scrolls sideways under it (1024 px, zoomed screens).
$stickyClass = $sticky ? 'sticky right-0 bg-white dark:bg-slate-800' : '';
@endphp

<td {{ $attributes->merge(['class' => "$whitespaceClass $stickyClass px-4 py-4 2xl:px-6 $alignClass text-sm text-slate-700 dark:text-slate-300"]) }}>
    {{ $slot }}
</td>
