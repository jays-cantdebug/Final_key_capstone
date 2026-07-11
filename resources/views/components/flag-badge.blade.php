@props(['type', 'secondaryCount' => 0])

@php
$colors = [
    'counseling_endorsement' => 'teal',
    'awareness_notification' => 'purple',
];

$labels = [
    'counseling_endorsement' => 'Endorsement',
    'awareness_notification' => 'Notification',
];

$title = $secondaryCount > 0 ? "Also has {$secondaryCount} additional flag(s)" : null;
@endphp

<div class="inline-flex flex-col items-start gap-1">
    <x-badge :color="$colors[$type] ?? 'slate'" :title="$title" {{ $attributes }}>
        {{ $labels[$type] ?? 'Normal' }}
        {{ $slot }}
    </x-badge>
    @if ($secondaryCount > 0)
        <span class="text-[10px] font-medium text-slate-500">+{{ $secondaryCount }} Notification</span>
    @endif
</div>
