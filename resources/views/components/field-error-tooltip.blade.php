@props(['message', 'align' => 'left'])

@if ($message)
    <div
        x-show="show"
        x-transition
        class="pointer-events-none absolute top-full z-20 mt-2 w-max max-w-[220px] sm:max-w-xs {{ $align === 'right' ? 'right-0' : 'left-0' }}"
    >
        <div class="absolute -top-1 h-2 w-2 rotate-45 bg-red-600 dark:bg-red-500 {{ $align === 'right' ? 'right-4' : 'left-4' }}"></div>
        <div class="relative rounded-md bg-red-600 px-3 py-2 text-xs font-medium text-white shadow-lg dark:bg-red-500">
            {{ $message }}
        </div>
    </div>
@endif
