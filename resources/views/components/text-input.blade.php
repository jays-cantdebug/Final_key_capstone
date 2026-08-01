@props(['disabled' => false, 'invalid' => false])

<input @disabled($disabled) @if ($invalid) data-field-invalid @endif {{ $attributes->merge(['class' => $invalid ? 'border-red-500 focus:border-red-500 focus:ring-red-500 rounded-md shadow-sm' : 'border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm']) }}>
