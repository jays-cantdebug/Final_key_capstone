@props(['disabled' => false, 'invalid' => false])

<input type="checkbox" @disabled($disabled) @if ($invalid) data-field-invalid @endif {{ $attributes->merge(['class' => $invalid ? 'rounded border-red-500 text-primary shadow-sm focus:ring-red-500' : 'rounded border-gray-300 text-primary shadow-sm focus:ring-primary']) }}>
