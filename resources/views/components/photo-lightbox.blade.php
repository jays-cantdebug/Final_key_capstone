@props(['src' => null, 'alt' => ''])

@if ($src)
    <div x-data="{ open: false }" @keydown.escape.window="open = false">
        <button
            type="button"
            @click="open = true"
            aria-label="{{ __('View larger photo') }}"
            {{ $attributes->merge(['class' => 'block rounded-full transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:focus:ring-primary-soft dark:focus:ring-offset-slate-900']) }}
        >
            {{ $slot }}
        </button>

        <div
            x-show="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="display: none;"
        >
            <div
                x-show="open"
                class="absolute inset-0 bg-black/80"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            ></div>

            <div
                x-show="open"
                class="relative"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
            >
                <button
                    type="button"
                    @click="open = false"
                    aria-label="{{ __('Close') }}"
                    class="absolute -top-3 -right-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-slate-700 shadow-lg transition hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                </button>

                <img
                    src="{{ $src }}"
                    alt="{{ $alt }}"
                    class="max-h-[80vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
                >
            </div>
        </div>
    </div>
@else
    {{ $slot }}
@endif
