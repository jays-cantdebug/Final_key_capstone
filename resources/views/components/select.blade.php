@props(['disabled' => false, 'invalid' => false])

<div class="relative" x-data="{ open: false }">
    <select
        @disabled($disabled)
        x-on:focus="open = true"
        x-on:blur="open = false"
        @if ($invalid) data-field-invalid @endif
        {{ $attributes->merge(['class' => $invalid ? 'block w-full appearance-none bg-none rounded-md border-red-500 shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-red-400 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-red-400 dark:focus:ring-red-400' : 'block w-full appearance-none bg-none rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-primary-soft dark:focus:ring-primary-soft']) }}
    >
        {{ $slot }}
    </select>
    <svg
        class="pointer-events-none absolute inset-y-0 right-3 my-auto h-4 w-4 text-gray-500 transition-transform duration-200 dark:text-slate-400"
        :class="{ 'rotate-180': open }"
        viewBox="0 0 20 20"
        fill="none"
        aria-hidden="true"
    >
        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 8l4 4 4-4" />
    </svg>
</div>
