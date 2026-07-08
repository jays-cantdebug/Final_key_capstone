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

<x-badge :color="$colors[$type] ?? 'slate'" :title="$title" {{ $attributes }}>
    {{ $labels[$type] ?? 'Normal' }}
    @if ($secondaryCount > 0)
        <span class="opacity-75">+{{ $secondaryCount }} Notification</span>
    @endif
    {{ $slot }}
</x-badge>
