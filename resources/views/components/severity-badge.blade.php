@props(['level'])

@php
$colors = [
    'Normal' => 'green',
    'Mild' => 'blue',
    'Moderate' => 'amber',
    'Severe' => 'orange',
    'Extremely Severe' => 'red',
];
@endphp

<x-badge :color="$colors[$level] ?? 'slate'" {{ $attributes }}>
    {{ $level ?? 'N/A' }}
</x-badge>
