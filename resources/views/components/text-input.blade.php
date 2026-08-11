@props(['disabled' => false, 'invalid' => false])

<input @disabled($disabled) @if ($invalid) data-field-invalid @endif {{ $attributes->merge(['class' => $invalid ? 'border-red-500 focus:border-red-500 focus:ring-red-500 rounded-md shadow-sm dark:border-red-400 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-red-400 dark:focus:ring-red-400' : 'border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-primary-soft dark:focus:ring-primary-soft']) }}>
