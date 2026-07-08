@props(['disabled' => false])

<input type="checkbox" @disabled($disabled) {{ $attributes->merge(['class' => 'rounded border-gray-300 text-primary shadow-sm focus:ring-primary']) }}>
