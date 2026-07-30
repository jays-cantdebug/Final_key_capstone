@props(['disabled' => false])

<div class="relative" x-data="{ open: false }">
    <select
        @disabled($disabled)
        x-on:focus="open = true"
        x-on:blur="open = false"
        {{ $attributes->merge(['class' => 'block w-full appearance-none bg-none rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary']) }}
    >
        {{ $slot }}
    </select>
    <svg
        class="pointer-events-none absolute inset-y-0 right-3 my-auto h-4 w-4 text-gray-500 transition-transform duration-200"
        :class="{ 'rotate-180': open }"
        viewBox="0 0 20 20"
        fill="none"
        aria-hidden="true"
    >
        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 8l4 4 4-4" />
    </svg>
</div>
