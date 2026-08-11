@props(['disabled' => false, 'invalid' => false])

<input type="checkbox" @disabled($disabled) @if ($invalid) data-field-invalid @endif {{ $attributes->merge(['class' => $invalid ? 'rounded border-red-500 text-primary shadow-sm focus:ring-red-500 dark:border-red-400 dark:bg-slate-900 dark:text-primary-soft dark:focus:ring-red-400' : 'rounded border-gray-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900 dark:text-primary-soft dark:focus:ring-primary-soft']) }}>
