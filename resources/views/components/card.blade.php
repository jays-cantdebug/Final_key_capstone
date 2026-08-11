@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800']) }}>
    @isset($header)
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
            {{ $header }}
        </div>
    @endisset

    <div @class([$padded ? 'p-6' : ''])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-700">
            {{ $footer }}
        </div>
    @endisset
</div>
