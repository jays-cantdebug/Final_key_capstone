@props(['status' => 'none', 'dueDate' => null])

{{-- Derived follow-up status from StudentCounselingHistoryService (none / needed / overdue). --}}
@php
    [$color, $label] = match ($status) {
        'overdue' => ['red', 'Overdue'],
        'needed' => ['amber', 'Follow-up needed'],
        default => ['slate', 'None'],
    };
@endphp

<x-badge :color="$color" {{ $attributes }}>
    {{ $label }}@if ($status !== 'none' && $dueDate) &middot; due {{ $dueDate->format('M d, Y') }}@endif
</x-badge>
